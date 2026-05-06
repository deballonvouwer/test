<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Nieuwe klant toevoegen</h1>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:700px; background:#fff; padding:16px; border:1px solid #ddd;">
        <?php wp_nonce_field('grr_save_customer'); ?>
        <input type="hidden" name="action" value="grr_save_customer" />

        <table class="form-table">
            <tr>
                <th><label for="name">Naam</label></th>
                <td><input required type="text" name="name" id="name" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="email">E-mail</label></th>
                <td><input required type="email" name="email" id="email" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="phone">Telefoon</label></th>
                <td><input type="text" name="phone" id="phone" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label for="event_date">Event datum</label></th>
                <td><input type="date" name="event_date" id="event_date" /></td>
            </tr>
        </table>

        <?php submit_button('Klant opslaan'); ?>
    </form>
</div>
