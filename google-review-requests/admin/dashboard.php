<?php
if (! defined('ABSPATH')) {
    exit;
}

function grr_status_ui($status) {
    if (in_array($status, ['completed', 'afgerond', 'review_placed'], true)) {
        return '⭐ Review geplaatst';
    }
    if (in_array($status, ['review_sent', 'review_verzonden'], true)) {
        return '✅ Review verzonden';
    }
    return '⚪ Gepland';
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

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:20px;">
        <?php wp_nonce_field('grr_send_test_mail'); ?>
        <input type="hidden" name="action" value="grr_send_test_mail" />
        <button class="button button-secondary">Testmail versturen</button>
    </form>

    <h2>Klantenoverzicht</h2>
    <table class="widefat striped">
        <thead><tr><th>Naam</th><th>E-mail</th><th>Telefoon</th><th>Event datum</th><th>Event type</th><th>Foto status</th><th>Status</th><th>Acties</th></tr></thead>
        <tbody>
        <?php if (empty($customers)) : ?>
            <tr><td colspan="8">Nog geen klanten.</td></tr>
        <?php else : foreach ($customers as $customer) : ?>
            <?php
            $status = (string) $customer->status;
            $normalized_status = $status;
            if (in_array($status, ['completed', 'afgerond'], true)) {
                $normalized_status = 'review_placed';
            }
            $photo_missing = empty($customer->photo_link);
            ?>
            <tr>
                <td><?php echo esc_html($customer->name); ?></td>
                <td><?php echo esc_html($customer->email); ?></td>
                <td><?php echo esc_html($customer->phone); ?></td>
                <td><?php echo esc_html($customer->event_date); ?></td>
                <td><?php echo esc_html($customer->event_type ?: 'algemeen'); ?></td>
                <td><?php echo $photo_missing ? 'Ontbreekt' : 'Klaar'; ?></td>
                <td><?php echo esc_html(grr_status_ui($normalized_status)); ?></td>
                <td>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:6px;">
                        <?php wp_nonce_field('grr_update_status'); ?>
                        <input type="hidden" name="action" value="grr_update_status" />
                        <input type="hidden" name="customer_id" value="<?php echo (int) $customer->id; ?>" />
                        <select name="status">
                            <option value="planned" <?php selected($normalized_status, 'planned'); ?>>Gepland</option>
                            <option value="review_sent" <?php selected($normalized_status, 'review_sent'); ?>>Review verzonden</option>
                            <option value="review_placed" <?php selected($normalized_status, 'review_placed'); ?>>Review geplaatst</option>
                        </select>
                        <button class="button button-small">Opslaan</button>
                    </form>

                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="<?php echo $photo_missing ? "return confirm('Er is nog geen foto link ingevuld. Toch reviewmail versturen?');" : ''; ?>">
                        <?php wp_nonce_field('grr_send_now'); ?>
                        <input type="hidden" name="action" value="grr_send_now" />
                        <input type="hidden" name="customer_id" value="<?php echo (int) $customer->id; ?>" />
                        <button class="button button-primary button-small">Review nu versturen</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
