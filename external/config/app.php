<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Anonymous Feedback Tool',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8084',
    'attachments_storage_path' => getenv('ATTACHMENTS_STORAGE_PATH') ?: (dirname(__DIR__) . '/anonymous_feedback_private_uploads'),
    'malware_scanner' => getenv('MALWARE_SCANNER') ?: 'noop',
];
