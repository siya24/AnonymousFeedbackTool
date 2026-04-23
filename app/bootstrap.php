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
App\Core\Container::set('feedbackService', new App\Services\FeedbackService(
    App\Core\Container::get('feedbackRepository')
));
