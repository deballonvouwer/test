<?php

if (! defined('ABSPATH')) {
    exit;
}

class GRR_Plugin {
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
    }

    public static function activate() {
        self::create_tables();
    }

    public static function deactivate() {
        // Intentionally empty. Cron is no longer used.
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
            event_type VARCHAR(30) NOT NULL DEFAULT 'algemeen',
            photo_link TEXT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'planned',
            completed_at DATETIME DEFAULT NULL,
            review_sent_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) {$charset_collate};";

        $sql_logs = "CREATE TABLE {$logs_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            customer_name VARCHAR(190) NOT NULL DEFAULT '',
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
        add_menu_page(__('Google Reviews', 'grr'), __('Google Reviews', 'grr'), 'manage_options', 'grr-dashboard', [$this, 'render_dashboard'], 'dashicons-star-filled', 30);
        add_submenu_page('grr-dashboard', __('Nieuwe klant', 'grr'), __('Nieuwe klant', 'grr'), 'manage_options', 'grr-new-customer', [$this, 'render_new_customer_form']);
        add_submenu_page('grr-dashboard', __('Logs', 'grr'), __('Logs', 'grr'), 'manage_options', 'grr-logs', [$this, 'render_logs_page']);
    }

    private function normalize_status($status) {
        $status = sanitize_text_field((string) $status);
        if (in_array($status, ['completed', 'afgerond'], true)) {
            return 'review_placed';
        }
        if ('review_verzonden' === $status || 'review_sent' === $status) {
            return 'review_sent';
        }
        if ('review_geplaatst' === $status || 'review_placed' === $status) {
            return 'review_placed';
        }
        return 'planned';
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
        $event_type = sanitize_text_field(wp_unslash($_POST['event_type'] ?? 'algemeen'));
        $photo_link = esc_url_raw(wp_unslash($_POST['photo_link'] ?? ''));

        if (empty($name) || empty($email)) {
            wp_safe_redirect(admin_url('admin.php?page=grr-new-customer'));
            exit;
        }

        $allowed_types = ['algemeen', 'huwelijk', 'verjaardag', 'bedrijfsevent', 'feest', 'overig'];
        if (! in_array($event_type, $allowed_types, true)) {
            $event_type = 'algemeen';
        }

        $now = current_time('mysql');
        $wpdb->insert(
            $this->customers_table,
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'event_date' => $event_date ?: null,
                'event_type' => $event_type,
                'photo_link' => $photo_link ?: null,
                'status' => 'planned',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
        exit;
    }

    public function handle_update_status() {
        if (! current_user_can('manage_options')) {
            wp_die(__('Geen toegang.', 'grr'));
        }
        check_admin_referer('grr_update_status');

        global $wpdb;
        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        if ($customer_id < 1) {
            wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
            exit;
        }

        $status = $this->normalize_status(wp_unslash($_POST['status'] ?? 'planned'));
        $data = ['status' => $status, 'updated_at' => current_time('mysql')];
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
            $wpdb->update($this->customers_table, [
                'status' => 'review_sent',
                'review_sent_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['id' => $customer->id]);
        }

        wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
        exit;
    }

    public function handle_send_test_mail() {
        if (! current_user_can('manage_options')) {
            wp_die(__('Geen toegang.', 'grr'));
        }
        check_admin_referer('grr_send_test_mail');

        $admin_email = sanitize_email(get_option('admin_email'));
        $review_link = esc_url_raw(get_option('grr_google_review_link', ''));
        $subject = __('Testmail: Google review verzoek', 'grr');
        $body = sprintf("Dit is een testmail voor Google review verzoeken.\n\nReview link: %s", $review_link ?: __('Niet ingesteld', 'grr'));
        $sent = wp_mail($admin_email, $subject, $body);

        $this->log_message(0, 'Testmail', $admin_email, $subject, $body, $sent ? 'sent' : 'failed', $sent ? null : 'wp_mail returned false');
        wp_safe_redirect(admin_url('admin.php?page=grr-dashboard'));
        exit;
    }

    private function send_review_email($customer) {
        $review_link = esc_url_raw(get_option('grr_google_review_link', ''));
        [$subject, $body] = $this->build_email_content($customer, $review_link);

        if (empty($review_link)) {
            $this->log_message((int) $customer->id, $customer->name, $customer->email, $subject, $body, 'failed', 'Google review link is not configured');
            return false;
        }

        $sent = wp_mail($customer->email, $subject, $body);
        $this->log_message((int) $customer->id, $customer->name, $customer->email, $subject, $body, $sent ? 'sent' : 'failed', $sent ? null : 'wp_mail returned false');
        return $sent;
    }

    private function build_email_content($customer, $review_link) {
        $type = sanitize_text_field((string) ($customer->event_type ?? 'algemeen'));
        if (empty($type) || 'overig' === $type) {
            $type = 'algemeen';
        }

        $subjects = [
            'algemeen' => 'Bedankt namens The Photobooth Company',
            'huwelijk' => 'Bedankt dat we erbij mochten zijn op jullie bruiloft',
            'verjaardag' => 'Bedankt voor het leuke feest',
            'bedrijfsevent' => 'Bedankt voor het vertrouwen',
            'feest' => 'Bedankt voor het leuke feest',
        ];

        $photo_block = '';
        if (! empty($customer->photo_link)) {
            $line = 'Bekijk en download hier de foto’s:';
            if ('huwelijk' === $type) {
                $line = 'Bekijk en download hier jullie foto’s:';
            }
            $photo_block = "\n{$line}\n" . $customer->photo_link . "\n";
        }

        $templates = [
            'algemeen' => "Hoi [naam],\n\nBedankt dat we erbij mochten zijn!\nWe hopen dat alles naar wens was en dat jullie genoten hebben van de photobooth 😊\n[foto]\nAls je nog een minuutje hebt, zouden we het enorm waarderen als je een Google review achterlaat:\n[review_link]\n\nAlvast bedankt!\n\nGroet,\nAndré\nThe Photobooth Company",
            'huwelijk' => "Hoi [naam],\n\nWat leuk dat jullie de photobooth hebben gebruikt tijdens jullie bruiloft! 💍\nWe hopen dat jullie een fantastische dag hebben gehad en dat de foto’s mooie herinneringen opleveren.\n[foto]\nAls jullie nog een minuutje hebben, zouden jullie ons enorm helpen met een Google review:\n[review_link]\n\nAlvast heel erg bedankt!\n\nGroet,\nAndré\nThe Photobooth Company",
            'verjaardag' => "Hoi [naam],\n\nBedankt dat we erbij mochten zijn op je verjaardag! 🎉\nWe hopen dat het een topfeest was en dat iedereen heeft genoten van de photobooth.\n[foto]\nAls je nog een minuutje hebt, zou je ons enorm helpen met een Google review:\n[review_link]\n\nDankjewel!\n\nGroet,\nAndré\nThe Photobooth Company",
            'bedrijfsevent' => "Hoi [naam],\n\nBedankt voor het vertrouwen in The Photobooth Company tijdens jullie event.\nWe hopen dat de photobooth een leuke toevoeging was en dat alles naar wens is verlopen.\n[foto]\nWe horen graag jullie ervaring via een Google review:\n[review_link]\n\nMet vriendelijke groet,\nAndré\nThe Photobooth Company",
            'feest' => "Hoi [naam],\n\nBedankt dat we erbij mochten zijn op het feest! 🎊\nHopelijk hebben jullie genoten van de photobooth en is er flink gelachen om de foto’s.\n[foto]\nAls je nog een minuutje hebt, zouden we het enorm waarderen als je een Google review achterlaat:\n[review_link]\n\nDankjewel!\n\nGroet,\nAndré\nThe Photobooth Company",
        ];

        $template = $templates[$type] ?? $templates['algemeen'];
        $body = str_replace(['[naam]', '[review_link]', '[foto]'], [$customer->name, $review_link, $photo_block], $template);

        return [$subjects[$type] ?? $subjects['algemeen'], $body];
    }

    private function log_message($customer_id, $customer_name, $recipient, $subject, $body, $status, $error_message = null) {
        global $wpdb;
        $wpdb->insert($this->logs_table, [
            'customer_id' => $customer_id,
            'customer_name' => sanitize_text_field((string) $customer_name),
            'message_type' => 'review_request',
            'recipient' => sanitize_email((string) $recipient),
            'subject' => sanitize_text_field((string) $subject),
            'body' => wp_kses_post((string) $body),
            'sent_at' => current_time('mysql'),
            'status' => sanitize_text_field((string) $status),
            'error_message' => $error_message ? sanitize_text_field((string) $error_message) : null,
        ]);
    }

    public function render_dashboard() {
        global $wpdb;
        $customers = $wpdb->get_results("SELECT * FROM {$this->customers_table} ORDER BY created_at DESC LIMIT 200");
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
