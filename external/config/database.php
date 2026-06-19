<?php
declare(strict_types=1);

$driver = getenv('DB_DRIVER') ?: 'mysql';
$port = getenv('DB_PORT');
if ($port === false || $port === '') {
    $port = strtolower((string) $driver) === 'sqlsrv' ? '' : '3306';
}

return [
    'driver' => $driver,
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => $port,
    'database' => getenv('DB_DATABASE') ?: 'anonymous_feedback_tool',
    'username' => getenv('DB_USERNAME') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
    'trusted_connection' => filter_var(getenv('DB_TRUSTED_CONNECTION') ?: 'false', FILTER_VALIDATE_BOOL),
    'trust_server_certificate' => filter_var(getenv('DB_TRUST_SERVER_CERTIFICATE') ?: 'false', FILTER_VALIDATE_BOOL),
    'multiple_active_result_sets' => filter_var(getenv('DB_MULTIPLE_ACTIVE_RESULT_SETS') ?: 'true', FILTER_VALIDATE_BOOL),
    'charset' => 'utf8mb4',
];
