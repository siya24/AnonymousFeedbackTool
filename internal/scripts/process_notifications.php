<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

$timestamp = (new DateTimeImmutable('now'))->format(DATE_ATOM);

try {
    $result = App\Core\Container::get('feedbackService')->processScheduledNotifications();
    echo json_encode([
        'success' => true,
        'timestamp' => $timestamp,
        'result' => $result,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $e) {
    error_log(sprintf('[%s] process_notifications failed: %s', $timestamp, $e->getMessage()));
    echo json_encode([
        'success' => false,
        'timestamp' => $timestamp,
        'error' => $e->getMessage(),
    ], JSON_PRETTY_PRINT) . PHP_EOL;
    exit(1);
}
