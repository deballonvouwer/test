<?php
if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1>Verzendlogs</h1>

    <table class="widefat striped">
        <thead>
            <tr>
                <th>Tijd</th>
                <th>Klant ID</th>
                <th>Ontvanger</th>
                <th>Onderwerp</th>
                <th>Status</th>
                <th>Fout</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($logs)) : ?>
            <tr><td colspan="6">Nog geen logs.</td></tr>
        <?php else : ?>
            <?php foreach ($logs as $log) : ?>
                <tr>
                    <td><?php echo esc_html($log->sent_at); ?></td>
                    <td><?php echo (int) $log->customer_id; ?></td>
                    <td><?php echo esc_html($log->recipient); ?></td>
                    <td><?php echo esc_html($log->subject); ?></td>
                    <td><?php echo esc_html($log->status); ?></td>
                    <td><?php echo esc_html($log->error_message); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
