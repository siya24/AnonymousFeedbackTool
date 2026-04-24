<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

try {
    $result = App\Core\Container::get('feedbackService')->processScheduledNotifications();
    echo json_encode([
        'success' => true,
        'result' => $result,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
