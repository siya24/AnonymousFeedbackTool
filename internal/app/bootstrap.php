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


header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

$config = [
    'app' => require_once __DIR__ . '/../config/app.php',
    'database' => require_once __DIR__ . '/../config/database.php',
];

App\Core\Container::set('config', $config);
App\Core\Container::set('db', App\Core\Database::connect($config['database']));
if (strtolower((string) ($config['database']['driver'] ?? 'mysql')) !== 'sqlsrv') {
    App\Core\Migration::run(App\Core\Container::get('db'));
} else {
    try {
        App\Core\Container::get('db')->exec(
            "IF OBJECT_ID('dbo.provinces', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.provinces (
        id CHAR(36) NOT NULL PRIMARY KEY,
        name VARCHAR(120) NOT NULL UNIQUE,
        is_active BIT NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME2 NOT NULL,
        updated_at DATETIME2 NOT NULL
    );

    CREATE INDEX idx_provinces_is_active ON dbo.provinces (is_active);
    CREATE INDEX idx_provinces_sort_order ON dbo.provinces (sort_order);
END"
        );
        App\Core\Container::get('db')->exec(
            "IF COL_LENGTH('dbo.feedbacks', 'province_id') IS NULL BEGIN ALTER TABLE dbo.feedbacks ADD province_id CHAR(36) NULL; END"
        );
        App\Core\Container::get('db')->exec(
            "IF COL_LENGTH('dbo.feedbacks', 'province_id') IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM sys.foreign_keys
    WHERE name = 'fk_feedbacks__province_id__provinces'
)
BEGIN
    ALTER TABLE dbo.feedbacks
    ADD CONSTRAINT fk_feedbacks__province_id__provinces FOREIGN KEY (province_id) REFERENCES dbo.provinces(id);
END"
        );
        App\Core\Container::get('db')->exec(
            "IF COL_LENGTH('dbo.feedbacks', 'province_id') IS NOT NULL
AND NOT EXISTS (
    SELECT 1 FROM sys.indexes
    WHERE name = 'idx_province_id'
      AND object_id = OBJECT_ID('dbo.feedbacks')
)
BEGIN
    CREATE INDEX idx_province_id ON dbo.feedbacks (province_id);
END"
        );
        App\Core\Container::get('db')->exec(
            "MERGE dbo.provinces AS target
USING (
    VALUES
        ('Eastern Cape', 1),
        ('Free State', 2),
        ('Gauteng', 3),
        ('KwaZulu-Natal', 4),
        ('Limpopo', 5),
        ('Mpumalanga', 6),
        ('Northern Cape', 7),
        ('North West', 8),
        ('Western Cape', 9)
) AS source(name, sort_order)
ON target.name = source.name
WHEN NOT MATCHED BY TARGET THEN
    INSERT (id, name, is_active, sort_order, created_at, updated_at)
    VALUES (CONVERT(CHAR(36), NEWID()), source.name, 1, source.sort_order, SYSDATETIME(), SYSDATETIME());"
        );
        App\Core\Container::get('db')->exec(
            "IF COL_LENGTH('dbo.feedbacks', 'reporter_feedback') IS NULL BEGIN ALTER TABLE dbo.feedbacks ADD reporter_feedback NVARCHAR(MAX) NULL; END"
        );
        App\Core\Container::get('db')->exec(
            "IF OBJECT_ID('dbo.user_roles', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.user_roles (
        id CHAR(36) NOT NULL PRIMARY KEY,
        user_id CHAR(36) NOT NULL,
        role_id CHAR(36) NOT NULL,
        created_at DATETIME2 NOT NULL,
        CONSTRAINT fk_user_roles__user_id__users FOREIGN KEY (user_id) REFERENCES dbo.users(id) ON DELETE CASCADE,
        CONSTRAINT fk_user_roles__role_id__assignment_roles FOREIGN KEY (role_id) REFERENCES dbo.assignment_roles(id) ON DELETE CASCADE,
        CONSTRAINT uk_user_roles_user_role UNIQUE (user_id, role_id)
    );

    CREATE INDEX idx_user_roles_user_id ON dbo.user_roles (user_id);
    CREATE INDEX idx_user_roles_role_id ON dbo.user_roles (role_id);
END"
        );
        App\Core\Container::get('db')->exec(
            "IF OBJECT_ID('dbo.user_roles', 'U') IS NOT NULL
BEGIN
    IF EXISTS (
        SELECT 1
        FROM sys.key_constraints
        WHERE [name] = 'uk_user_roles_user'
          AND [parent_object_id] = OBJECT_ID('dbo.user_roles')
    )
    BEGIN
        ALTER TABLE dbo.user_roles DROP CONSTRAINT uk_user_roles_user;
    END;

    IF NOT EXISTS (
        SELECT 1
        FROM sys.key_constraints
        WHERE [name] = 'uk_user_roles_user_role'
          AND [parent_object_id] = OBJECT_ID('dbo.user_roles')
    )
    BEGIN
        ALTER TABLE dbo.user_roles
        ADD CONSTRAINT uk_user_roles_user_role UNIQUE (user_id, role_id);
    END;
END"
        );
    } catch (\Throwable $e) {
        error_log('SQL Server bootstrap migration warning: ' . $e->getMessage());
    }
}


$jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-super-secret-jwt-key-change-in-production';
$jwtExpiresIn = (int) ($_ENV['JWT_EXPIRES_IN'] ?? 600);
App\Core\Container::set('jwt', new App\Core\JwtService($jwtSecret, $jwtExpiresIn));
App\Core\Container::set('auth', new App\Core\Authorization(App\Core\Container::get('jwt')));


$db = App\Core\Container::get('db');
App\Core\Container::set('categoryRepository', new App\Repositories\CategoryRepository($db));
App\Core\Container::set('provinceRepository', new App\Repositories\ProvinceRepository($db));
App\Core\Container::set('statusRepository', new App\Repositories\StatusRepository($db));
App\Core\Container::set('stageRepository', new App\Repositories\StageRepository($db));
App\Core\Container::set('coInvestigatorRepository', new App\Repositories\CoInvestigatorRepository($db));
App\Core\Container::set('auditRepository', new App\Repositories\AuditRepository($db));
App\Core\Container::set('attachmentRepository', new App\Repositories\AttachmentRepository($db));
App\Core\Container::set('feedbackInsightsRepository', new App\Repositories\FeedbackInsightsRepository($db));
App\Core\Container::set('feedbackRepository', new App\Repositories\FeedbackRepository(
    $db,
    App\Core\Container::get('auditRepository'),
    App\Core\Container::get('coInvestigatorRepository'),
    App\Core\Container::get('attachmentRepository'),
));
App\Core\Container::set('assignmentRoleRepository', new App\Repositories\AssignmentRoleRepository($db));
App\Core\Container::set('ldapAuthService', new App\Services\LdapAuthService($config['app']));
App\Core\Container::set('hrLdapUserService', new App\Services\HrLdapUserService(
    App\Core\Container::get('ldapAuthService'),
    $db,
    $config['app']
));
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
    $config['app']['smtp_tls_server_name'] ?? '',
    (bool) ($config['app']['smtp_tls_verify'] ?? true),
    $config['app']['smtp_auth_mode'] ?? 'auto',
    (bool) ($config['app']['smtp_auth_optional'] ?? false),
));
App\Core\Container::set('notificationService', new App\Services\NotificationService(
    App\Core\Container::get('auditRepository'),
    App\Core\Container::get('smtpMailer'),
    App\Core\Container::get('emailTemplateRenderer'),
    $config['app']['base_url'] ?? 'http://localhost:8083',
    (bool) ($config['app']['notifications_immediate_enabled'] ?? true),
    (bool) ($config['app']['notifications_scheduled_enabled'] ?? true),
));

// Malware scanner: use ClamAV if available, otherwise no-op
$scannerMode = strtolower((string) ($config['app']['malware_scanner'] ?? 'noop'));
if ($scannerMode === 'clamav') {
    App\Core\Container::set('malwareScanner', new App\Services\ClamAvMalwareScanner());
} else {
    App\Core\Container::set('malwareScanner', new App\Services\NoOpMalwareScanner());
}

App\Core\Container::set('feedbackService', new App\Services\FeedbackService(
    App\Core\Container::get('feedbackRepository'),
    App\Core\Container::get('feedbackInsightsRepository'),
    App\Core\Container::get('attachmentRepository'),
    App\Core\Container::get('auditRepository'),
    App\Core\Container::get('notificationService'),
    App\Core\Container::get('malwareScanner'),
    $config['app']['attachments_storage_path'] ?? null
));

App\Core\Container::set('coInvestigatorService', new App\Services\CoInvestigatorService(
    App\Core\Container::get('feedbackRepository'),
    App\Core\Container::get('coInvestigatorRepository'),
    App\Core\Container::get('notificationService'),
));


