<?php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Security headers — set on every response before any output
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$config = [
    'app' => require __DIR__ . '/../config/app.php',
    'database' => require __DIR__ . '/../config/database.php',
];

App\Core\Container::set('config', $config);
App\Core\Container::set('db', App\Core\Database::connect($config['database']));
App\Core\Migration::run(App\Core\Container::get('db'));

// Initialize JWT and Authorization services
$jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production';
App\Core\Container::set('jwt', new App\Core\JwtService($jwtSecret));
App\Core\Container::set('auth', new App\Core\Authorization(App\Core\Container::get('jwt')));

// Initialize Repository and Service layers
$db = App\Core\Container::get('db');
App\Core\Container::set('feedbackRepository', new App\Repositories\FeedbackRepository($db));
App\Core\Container::set('categoryRepository', new App\Repositories\CategoryRepository($db));
App\Core\Container::set('statusRepository', new App\Repositories\StatusRepository($db));
App\Core\Container::set('stageRepository', new App\Repositories\StageRepository($db));
App\Core\Container::set('ldapAuthService', new App\Services\LdapAuthService($config['app']));
App\Core\Container::set('emailTemplateRenderer', new App\Services\EmailTemplateRenderer(
    __DIR__ . '/Views/emails'
));
App\Core\Container::set('smtpMailer', new App\Core\SmtpMailer(
    $config['app']['smtp_host'],
    $config['app']['smtp_port'],
    $config['app']['smtp_username'],
    $config['app']['smtp_password'],
    $config['app']['mailer_from'],
    $config['app']['mailer_from_name'] ?? 'Voice Without Fear',
));
App\Core\Container::set('notificationService', new App\Services\NotificationService(
    App\Core\Container::get('feedbackRepository'),
    App\Core\Container::get('smtpMailer'),
    App\Core\Container::get('emailTemplateRenderer'),
    $config['app']['hr_notification_email'] ?? '',
    $config['app']['ethics_notification_email'] ?? '',
    $config['app']['base_url'] ?? 'http://localhost:8000',
));
App\Core\Container::set('feedbackService', new App\Services\FeedbackService(
    App\Core\Container::get('feedbackRepository'),
    App\Core\Container::get('notificationService')
));

// Process due notification reminders/escalations; duplicates are prevented in SQL.
try {
    App\Core\Container::get('feedbackService')->processScheduledNotifications();
} catch (\Throwable $e) {
    // Non-blocking by design: app functionality should continue even if mail transport fails.
}
