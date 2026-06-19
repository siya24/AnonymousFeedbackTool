<?php
declare(strict_types=1);

return [
    'driver' => getenv('DB_DRIVER') ?: 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'anonymous_feedback_tool',
    'username' => getenv('DB_USERNAME') ?: '',
    'password' => getenv('DB_PASSWORD') ?: '',
    'trusted_connection' => filter_var(getenv('DB_TRUSTED_CONNECTION') ?: 'false', FILTER_VALIDATE_BOOL),
    'trust_server_certificate' => filter_var(getenv('DB_TRUST_SERVER_CERTIFICATE') ?: 'false', FILTER_VALIDATE_BOOL),
    'multiple_active_result_sets' => filter_var(getenv('DB_MULTIPLE_ACTIVE_RESULT_SETS') ?: 'true', FILTER_VALIDATE_BOOL),
    'charset' => 'utf8mb4',
];
