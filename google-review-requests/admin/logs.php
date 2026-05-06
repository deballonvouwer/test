<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Verzendlogs</h1>
    <table class="widefat striped">
        <thead><tr><th>Datum/tijd</th><th>Klant</th><th>Ontvanger</th><th>Onderwerp</th><th>Resultaat</th><th>Foutmelding</th></tr></thead>
        <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="6">Nog geen logs.</td></tr>
        <?php else : foreach ($logs as $log) : ?>
            <tr>
                <td><?php echo esc_html($log->sent_at); ?></td>
                <td><?php echo esc_html($log->customer_name ?: ('#' . (int) $log->customer_id)); ?></td>
                <td><?php echo esc_html($log->recipient); ?></td>
                <td><?php echo esc_html($log->subject); ?></td>
                <td><?php echo esc_html($log->status); ?></td>
                <td><?php echo esc_html($log->error_message); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
