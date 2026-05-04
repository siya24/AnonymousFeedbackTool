<?php
declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Anonymous Feedback Tool',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8000',
    'attachments_storage_path' => getenv('ATTACHMENTS_STORAGE_PATH') ?: (dirname(__DIR__) . '/../anonymous_feedback_private_uploads'),
    'mailer_from' => getenv('MAIL_FROM') ?: 'noreply@localhost',
    'mailer_from_name' => getenv('MAIL_FROM_NAME') ?: 'Voice Without Fear',
    'smtp_host' => getenv('SMTP_HOST') ?: 'localhost',
    'smtp_port' => (int) (getenv('SMTP_PORT') ?: 587),
    'smtp_username' => getenv('SMTP_USERNAME') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'hr_notification_email' => getenv('HR_NOTIFICATION_EMAIL') ?: '',
    'ethics_notification_email' => getenv('ETHICS_NOTIFICATION_EMAIL') ?: '',
    'notifications_immediate_enabled' => filter_var(getenv('NOTIFICATIONS_IMMEDIATE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'notifications_scheduled_enabled' => filter_var(getenv('NOTIFICATIONS_SCHEDULED_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'malware_scanner' => getenv('MALWARE_SCANNER') ?: 'noop',
];
