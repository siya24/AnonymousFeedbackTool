<?php
declare(strict_types=1);

echo json_encode([
    'success' => true,
    'result' => [
        'notifications_disabled' => true,
        'message' => 'External app does not process notifications. Run internal/scripts/process_notifications.php instead.',
    ],
], JSON_PRETTY_PRINT) . PHP_EOL;
