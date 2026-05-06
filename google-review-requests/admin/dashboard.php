<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Google Review Requests</h1>

    <form method="post" action="options.php" style="max-width:700px; background:#fff; padding:16px; border:1px solid #ddd; margin-bottom:20px;">
        <?php settings_fields('general'); ?>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="grr_google_review_link">Google Review Link</label></th>
                <td><input type="url" class="regular-text" name="grr_google_review_link" id="grr_google_review_link" value="<?php echo esc_attr(get_option('grr_google_review_link', '')); ?>" /></td>
            </tr>
        </table>
        <?php submit_button('Opslaan'); ?>
    </form>

    <h2>Klanten</h2>
    <table class="widefat striped">
        <thead>
            <tr>
                <th>Naam</th>
                <th>E-mail</th>
                <th>Telefoon</th>
                <th>Event datum</th>
                <th>Status</th>
                <th>Actie</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($customers)) : ?>
                <tr><td colspan="6">Nog geen klanten.</td></tr>
            <?php else : ?>
                <?php foreach ($customers as $customer) : ?>
                    <tr>
                        <td><?php echo esc_html($customer->name); ?></td>
                        <td><?php echo esc_html($customer->email); ?></td>
                        <td><?php echo esc_html($customer->phone); ?></td>
                        <td><?php echo esc_html($customer->event_date); ?></td>
                        <td><?php echo esc_html($customer->status); ?></td>
                        <td>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <?php wp_nonce_field('grr_update_status'); ?>
                                <input type="hidden" name="action" value="grr_update_status" />
                                <input type="hidden" name="customer_id" value="<?php echo (int) $customer->id; ?>" />
                                <select name="status">
                                    <option value="planned" <?php selected($customer->status, 'planned'); ?>>Gepland</option>
                                    <option value="completed" <?php selected($customer->status, 'completed'); ?>>Afgerond</option>
                                    <option value="review_sent" <?php selected($customer->status, 'review_sent'); ?>>Review verzonden</option>
                                </select>
                                <button class="button button-small">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
