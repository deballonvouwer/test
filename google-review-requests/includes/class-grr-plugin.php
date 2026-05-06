<?php

if (! defined('ABSPATH')) {
    exit;
}

class GRR_Plugin {
    const CRON_HOOK = 'grr_send_scheduled_reviews';

    private static $instance = null;
    private $customers_table;
    private $logs_table;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->customers_table = $wpdb->prefix . 'grr_customers';
        $this->logs_table = $wpdb->prefix . 'grr_logs';

        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_grr_save_customer', [$this, 'handle_save_customer']);
        add_action('admin_post_grr_update_status', [$this, 'handle_update_status']);
        add_action('admin_post_grr_send_now', [$this, 'handle_send_now']);
        add_action('admin_post_grr_send_test_mail', [$this, 'handle_send_test_mail']);
        add_action(self::CRON_HOOK, [$this, 'process_scheduled_emails']);
    }

    public static function activate() {
        self::create_tables();

        if (! wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'hourly', self::CRON_HOOK);
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function create_tables() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $customers_table = $wpdb->prefix . 'grr_customers';
        $logs_table = $wpdb->prefix . 'grr_logs';

        $sql_customers = "CREATE TABLE {$customers_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            email VARCHAR(190) NOT NULL,
            phone VARCHAR(40) DEFAULT '',
            event_date DATE DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'planned',
            completed_at DATETIME DEFAULT NULL,
            review_sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY completed_at (completed_at)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            message_type VARCHAR(50) NOT NULL,
            recipient VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body LONGTEXT NOT NULL,
            sent_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL,
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY sent_at (sent_at)
        ) {$charset_collate};";

        dbDelta($sql_customers);
        dbDelta($sql_logs);
    }



    public function register_settings() {
        register_setting('general', 'grr_google_review_link', [
            'type' => 'string',
            'sanitize_callback' => 'esc_url_raw',
            'default' => '',
        ]);
    }

    public function register_admin_menu() {
        add_menu_page(
            __('Google Reviews', 'grr'),
            __('Google Reviews', 'grr'),
            'manage_options',
            'grr-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-star-filled',
            30
        );

        add_submenu_page(
            'grr-dashboard',
            __('Nieuwe klant', 'grr'),
            __('Nieuwe klant', 'grr'),
            'manage_options',
            'grr-new-customer',
            [$this, 'render_new_customer_form']
        );

        add_submenu_page(
            'grr-dashboard',
            __('Logs', 'grr'),
            __('Logs', 'grr'),
            'manage_options',
            'grr-logs',
            [$this, 'render_logs_page']
        );
    }

    public function handle_save_customer() {
        if (! current_user_can('manage_options')) {
            wp_die(__('Geen toegang.', 'grr'));
        }

        check_admin_referer('grr_save_customer');

        global $wpdb;

        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
        $phone = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
        $event_date = sanitize_text_field(wp_unslash($_POST['event_date'] ?? ''));

        if (empty($name) || empty($email)) {
            wp_safe_redirect(add_query_arg('grr_error', 'missing_fields', admin_url('admin.php?page=grr-new-customer')));
            exit;
        }

        $now = current_time('mysql');
        $wpdb->insert(
            $this->customers_table,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'event_date' => $event_date ?: null,
                'status' => 'planned',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        wp_safe_redirect(add_query_arg('grr_success', 'customer_saved', admin_url('admin.php?page=grr-dashboard')));
        exit;
    }

    public function handle_update_status() {
        if (! current_user_can('manage_options')) {
            wp_die(__('Geen toegang.', 'grr'));
        }

        check_admin_referer('grr_update_status');

        global $wpdb;

        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $status = sanitize_text_field(wp_unslash($_POST['status'] ?? ''));
        $allowed = ['planned', 'completed', 'review_sent'];

        if (! in_array($status, $allowed, true) || $customer_id < 1) {
            wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
            exit;
        }

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql'),
        ];

        if ('completed' === $status) {
            $data['completed_at'] = current_time('mysql');
        }

        if ('review_sent' === $status) {
            $data['review_sent_at'] = current_time('mysql');
        }

        $wpdb->update($this->customers_table, $data, ['id' => $customer_id]);

        wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
        exit;
    }



    public function handle_send_now() {
        if (! current_user_can('manage_options')) {
            wp_die(__('Geen toegang.', 'grr'));
        }

        check_admin_referer('grr_send_now');

        global $wpdb;
        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;

        if ($customer_id < 1) {
            wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
            exit;
        }

        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->customers_table} WHERE id = %d", $customer_id));

        if (! $customer) {
            wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
            exit;
        }

        $sent = $this->send_review_email($customer);

        if ($sent) {
            $wpdb->update(
                $this->customers_table,
                [
                    'status' => 'review_sent',
                    'review_sent_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ],
                ['id' => $customer->id]
            );
        }

        wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
        exit;
    }

    public function handle_send_test_mail() {
        if (! current_user_can('manage_options')) {
            wp_die(__('Geen toegang.', 'grr'));
        }

        check_admin_referer('grr_send_test_mail');

        $admin_email = get_option('admin_email');
        $review_link = esc_url_raw(get_option('grr_google_review_link', ''));

        $subject = __('Testmail: Google review verzoek', 'grr');
        $body = sprintf(
            "Dit is een testmail voor Google review verzoeken.\n\nReview link: %s",
            $review_link ?: __('Niet ingesteld', 'grr')
        );

        $sent = wp_mail($admin_email, $subject, $body);

        $this->log_message(
            0,
            'test_mail',
            $admin_email,
            $subject,
            $body,
            $sent ? 'sent' : 'failed',
            $sent ? null : 'wp_mail returned false'
        );

        wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
        exit;
    }

    public function process_scheduled_emails() {
        global $wpdb;

        $customers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->customers_table}
                 WHERE status = %s
                 AND completed_at IS NOT NULL
                 AND completed_at <= DATE_SUB(%s, INTERVAL 1 DAY)",
                'completed',
                current_time('mysql')
            )
        );

        if (empty($customers)) {
            return;
        }

        foreach ($customers as $customer) {
            $sent = $this->send_review_email($customer);

            if ($sent) {
                $wpdb->update(
                    $this->customers_table,
                    [
                        'status' => 'review_sent',
                        'review_sent_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ],
                    ['id' => $customer->id]
                );
            }
        }
    }

    private function send_review_email($customer) {
        $review_link = esc_url_raw(get_option('grr_google_review_link', ''));
        if (empty($review_link)) {
            $this->log_message(
                (int) $customer->id,
                'review_request',
                $customer->email,
                __('Google review link ontbreekt', 'grr'),
                '',
                'failed',
                'Google review link is not configured'
            );
            return false;
        }

        $subject = sprintf(__('Hoe was je ervaring, %s?', 'grr'), $customer->name);
        $body = sprintf(
            "Hallo %s,\n\nBedankt voor je bezoek. We horen graag je mening!\nLaat je review achter via deze link:\n%s\n\nAlvast bedankt!",
            $customer->name,
            $review_link
        );

        $sent = wp_mail($customer->email, $subject, $body);

        $this->log_message(
            (int) $customer->id,
            'review_request',
            $customer->email,
            $subject,
            $body,
            $sent ? 'sent' : 'failed',
            $sent ? null : 'wp_mail returned false'
        );

        return $sent;
    }

    private function log_message($customer_id, $message_type, $recipient, $subject, $body, $status, $error_message = null) {
        global $wpdb;

        $wpdb->insert(
            $this->logs_table,
            [
                'customer_id' => $customer_id,
                'message_type' => $message_type,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'sent_at' => current_time('mysql'),
                'status' => $status,
                'error_message' => $error_message,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function render_dashboard() {
        global $wpdb;
        $customers = $wpdb->get_results("SELECT * FROM {$this->customers_table} ORDER BY created_at DESC LIMIT 100");
        include GRR_PLUGIN_PATH . 'admin/dashboard.php';
    }

    public function render_new_customer_form() {
        include GRR_PLUGIN_PATH . 'admin/new-customer.php';
    }

    public function render_logs_page() {
        global $wpdb;
        $logs = $wpdb->get_results("SELECT * FROM {$this->logs_table} ORDER BY sent_at DESC LIMIT 200");
        include GRR_PLUGIN_PATH . 'admin/logs.php';
    }
}
