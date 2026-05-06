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
            <tr><th><label for="name">Naam</label></th><td><input required type="text" name="name" id="name" class="regular-text" /></td></tr>
            <tr><th><label for="email">E-mail</label></th><td><input required type="email" name="email" id="email" class="regular-text" /></td></tr>
            <tr><th><label for="phone">Telefoon</label></th><td><input type="text" name="phone" id="phone" class="regular-text" /></td></tr>
            <tr><th><label for="event_date">Event datum</label></th><td><input type="date" name="event_date" id="event_date" /></td></tr>
            <tr>
                <th><label for="event_type">Event type</label></th>
                <td>
                    <select name="event_type" id="event_type">
                        <option value="algemeen">algemeen</option>
                        <option value="huwelijk">huwelijk</option>
                        <option value="verjaardag">verjaardag</option>
                        <option value="bedrijfsevent">bedrijfsevent</option>
                        <option value="feest">feest</option>
                        <option value="overig">overig</option>
                    </select>
                </td>
            </tr>
            <tr><th><label for="photo_link">Foto download link (optioneel)</label></th><td><input type="url" name="photo_link" id="photo_link" class="regular-text" placeholder="https://..." /></td></tr>
        </table>

        <?php submit_button('Klant opslaan'); ?>
    </form>
</div>
