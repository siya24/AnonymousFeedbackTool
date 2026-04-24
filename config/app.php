<?php
declare(strict_types=1);

return [
    'name' => 'Anonymous Feedback Tool',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost:8000',
    'hr_auth_mode' => getenv('HR_AUTH_MODE') ?: 'hybrid',
    'ldap_host' => getenv('LDAP_HOST') ?: 'legal-aid.co.za',
    'ldap_port' => (int) (getenv('LDAP_PORT') ?: 389),
    'ldap_base_dn' => getenv('LDAP_BASE_DN') ?: 'DC=legal-aid,DC=co,DC=za',
    'ldap_domain' => getenv('LDAP_DOMAIN') ?: 'LEGAL-AID',
    'ldap_bind_pattern' => getenv('LDAP_BIND_PATTERN') ?: 'LEGAL-AID\\%s',
    'ldap_service_user' => getenv('LDAP_SERVICE_USER') ?: 'LEGAL-AID\\elaa-k2service',
    'ldap_service_password' => getenv('LDAP_SERVICE_PASSWORD') ?: '$k2!!@sv@jI8Y8p',
    'ldap_use_tls' => filter_var(getenv('LDAP_USE_TLS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'developer_override_users' => getenv('DEVELOPER_OVERRIDE_USERS') ?: 'SiyabulelaG,siyabulelag@legal-aid.co.za',
    'mailer_from' => getenv('MAIL_FROM') ?: 'noreply@organization.com',
    'hr_notification_email' => getenv('HR_NOTIFICATION_EMAIL') ?: 'hr@organization.com',
    'ethics_notification_email' => getenv('ETHICS_NOTIFICATION_EMAIL') ?: 'ethics@organization.com',
];
