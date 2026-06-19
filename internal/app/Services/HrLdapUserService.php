<?php declare(strict_types=1);

namespace App\Services;

use App\Core\Authorization;
use App\Exceptions\ForbiddenException;
use App\Exceptions\ServerException;
use PDO;

class HrLdapUserService
{
    public function __construct(
        private LdapAuthService $ldapAuthService,
        private PDO $db,
        private array $appConfig = []
    ) {}

    public function authenticate(string $identifier, string $password): ?array
    {
        $profile = $this->ldapAuthService->authenticate($identifier, $password);
        if ($profile === null) {
            return null;
        }

        $groupsConfigured = $this->isLdapAccessConfigured();

        $user = null;
        if ($groupsConfigured) {
            $user = $this->resolveAuthorizedLdapUser($identifier, $profile);
        }

        ['emails' => $emailCandidates, 'usernames' => $usernameCandidates] =
            $this->buildProvisioningCandidates($identifier, $profile);

        if ($user === null) {
            $user = $this->findProvisionedConsoleUser($emailCandidates, $usernameCandidates);
        }

        if ($user === null && $this->isDeveloperOverrideUser($identifier, $profile)) {
            $user = $this->upsertLdapUser($identifier, $profile, Authorization::ROLE_HR);
        }

        if ($user === null) {
            if ($groupsConfigured && $this->hasLdapAccessSignals($profile)) {
                throw new ForbiddenException(
                    'Access denied: your account is not a member of an authorised group (HR or Information Systems).'
                );
            }

            throw new ForbiddenException('LDAP authenticated, but user is not provisioned for HR Console');
        }

        return $user;
    }

    private function isLdapAccessConfigured(): bool
    {
        $hrGroupsRaw = trim((string) ($this->appConfig['ldap_hr_groups'] ?? ''));
        $isGroupsRaw = trim((string) ($this->appConfig['ldap_is_groups'] ?? ''));
        $hrOusRaw = trim((string) ($this->appConfig['ldap_hr_ous'] ?? ''));
        $isOusRaw = trim((string) ($this->appConfig['ldap_is_ous'] ?? ''));
        $hrDeptsRaw = trim((string) ($this->appConfig['ldap_hr_departments'] ?? ''));
        $isDeptsRaw = trim((string) ($this->appConfig['ldap_is_departments'] ?? ''));

        return $hrGroupsRaw !== '' || $isGroupsRaw !== ''
            || $hrOusRaw !== '' || $isOusRaw !== ''
            || $hrDeptsRaw !== '' || $isDeptsRaw !== '';
    }

    private function buildProvisioningCandidates(string $identifier, array $profile): array
    {
        $profileEmail = strtolower(trim((string) ($profile['email'] ?? '')));
        $profileUpn = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        $inputEmail = strtolower(trim($identifier));
        $profileUsername = strtolower(trim((string) ($profile['username'] ?? '')));
        $inputUsername = strtolower(trim($identifier));

        if (str_contains($inputUsername, '\\')) {
            $parts = explode('\\', $inputUsername);
            $inputUsername = strtolower(trim((string) end($parts)));
        }
        if (str_contains($inputUsername, '@')) {
            $parts = explode('@', $inputUsername, 2);
            $inputUsername = strtolower(trim((string) $parts[0]));
        }

        $emails = array_values(array_unique(array_filter([
            $profileEmail,
            $profileUpn,
            filter_var($inputEmail, FILTER_VALIDATE_EMAIL) ? $inputEmail : '',
        ])));

        $usernames = array_values(array_unique(array_filter([
            $profileUsername,
            $inputUsername,
        ])));

        return ['emails' => $emails, 'usernames' => $usernames];
    }

    private static function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    private function resolveAuthorizedLdapUser(string $identifier, array $profile): ?array
    {
        if ($this->isDeveloperOverrideUser($identifier, $profile)) {
            return $this->upsertLdapUser($identifier, $profile, Authorization::ROLE_HR);
        }

        $role = $this->resolveRoleFromLdapProfile($profile);
        if ($role === null) {
            return null;
        }

        return $this->upsertLdapUser($identifier, $profile, $role);
    }

    private function hasLdapAccessSignals(array $profile): bool
    {
        $groups = $profile['groups'] ?? [];
        if (is_array($groups) && $groups !== []) {
            return true;
        }

        $distinguishedName = trim((string) ($profile['distinguished_name'] ?? ''));
        if ($distinguishedName !== '') {
            return true;
        }

        $department = trim((string) ($profile['department'] ?? ''));
        return $department !== '';
    }

    private function resolveRoleFromLdapProfile(array $profile): ?string
    {
        $config = $this->getLdapAccessConfig();
        if ($config['configured'] === false) {
            return null;
        }

        $matchesRole = $this->matchesConfiguredGroup($profile, $config['groups'])
            || $this->matchesConfiguredOu($profile, $config['ous'])
            || $this->matchesConfiguredDepartment($profile, $config['departments']);

        return $matchesRole ? Authorization::ROLE_HR : null;
    }

    private function isDeveloperOverrideUser(string $identifier, array $profile): bool
    {
        $raw = (string) ($this->appConfig['developer_override_users'] ?? '');
        $allowed = $raw === '' ? [] : array_values(array_filter(array_map(
            static fn ($item): string => strtolower(trim((string) $item)),
            explode(',', $raw)
        )));

        $username = strtolower(trim((string) ($profile['username'] ?? '')));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $upn = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        $input = strtolower(trim($identifier));
        $candidates = array_values(array_unique(array_filter([$username, $email, $upn, $input])));

        return $allowed !== [] && count(array_intersect($candidates, $allowed)) > 0;
    }

    private function upsertLdapUser(string $identifier, array $profile, string $role): array
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email === '') {
            $email = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        }
        if ($email === '' && filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $email = strtolower(trim($identifier));
        }
        if ($email === '') {
            $username = strtolower(trim((string) ($profile['username'] ?? $identifier)));
            if ($username !== '') {
                $domain = $this->resolveLdapEmailDomain();
                if ($domain !== '') {
                    $email = $username . '@' . $domain;
                }
            }
        }
        if ($email === '') {
            throw new ForbiddenException('LDAP user requires a resolvable email address');
        }

        $name = trim((string) ($profile['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($profile['username'] ?? $identifier));
        }

        $adUsername = trim((string) ($profile['username'] ?? ''));
        $firstName = trim((string) ($profile['first_name'] ?? ''));
        $lastName = trim((string) ($profile['last_name'] ?? ''));
        $employeeNumber = trim((string) ($profile['employee_number'] ?? ''));
        $departmentName = trim((string) ($profile['department'] ?? ''));
        $positionTitle = trim((string) ($profile['title'] ?? ''));
        $officeLocation = trim((string) ($profile['location'] ?? ''));
        $placeholderHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $existing = $this->db->prepare('SELECT id FROM users WHERE email = ?');
        $existing->execute([$email]);
        $existingId = (string) ($existing->fetchColumn() ?: '');

        if ($existingId !== '') {
            $update = $this->db->prepare(
                'UPDATE users
                 SET name = ?,
                     first_name = ?,
                     last_name = ?,
                     ad_username = ?,
                     role = ?,
                     employee_number = ?,
                     department_name = ?,
                     position_title = ?,
                     office_location = ?,
                     is_active = 1,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $update->execute([
                $name,
                $firstName,
                $lastName,
                $adUsername,
                $role,
                $employeeNumber,
                $departmentName,
                $positionTitle,
                $officeLocation,
                $existingId,
            ]);
        } else {
            $insert = $this->db->prepare(
                'INSERT INTO users (
                    id,
                    name,
                    first_name,
                    last_name,
                    email,
                    ad_username,
                    password_hash,
                    role,
                    employee_number,
                    department_name,
                    position_title,
                    office_location,
                    is_active,
                    created_at,
                    updated_at
                )
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
            $insert->execute([
                self::generateUuid(),
                $name,
                $firstName,
                $lastName,
                $email,
                $adUsername,
                $placeholderHash,
                $role,
                $employeeNumber,
                $departmentName,
                $positionTitle,
                $officeLocation,
            ]);
        }

        $find = $this->db->prepare('SELECT id, name, email, password_hash, role, can_assign_cases FROM users WHERE email = ? AND is_active = 1');
        $find->execute([$email]);
        $user = $find->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new ServerException('Unable to provision LDAP user');
        }

        return $user;
    }

    private function resolveLdapEmailDomain(): string
    {
        $host = strtolower(trim((string) ($this->appConfig['ldap_host'] ?? '')));
        if ($host !== '' && str_contains($host, '.')) {
            return $host;
        }

        $baseDn = strtolower(trim((string) ($this->appConfig['ldap_base_dn'] ?? '')));
        if ($baseDn !== '') {
            preg_match_all('/dc=([^,]+)/i', $baseDn, $matches);
            if (!empty($matches[1])) {
                $parts = array_map(static fn ($part): string => trim((string) $part), $matches[1]);
                $parts = array_values(array_filter($parts));
                if ($parts !== []) {
                    return implode('.', $parts);
                }
            }
        }

        return '';
    }

    private function findProvisionedConsoleUser(array $emailCandidates, array $usernameCandidates = []): ?array
    {
        if ($emailCandidates === [] && $usernameCandidates === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count(Authorization::CONSOLE_ROLES), '?'));
        $stmt = $this->db->prepare(
                        "SELECT id, name, email, password_hash, role, can_assign_cases FROM users
             WHERE (LOWER(email) = LOWER(?) OR LOWER(ad_username) = LOWER(?))
               AND role IN ({$placeholders})
               AND is_active = 1"
        );

        $candidates = array_values(array_unique(array_merge($emailCandidates, $usernameCandidates)));
        foreach ($candidates as $candidate) {
            $stmt->execute([$candidate, $candidate, ...Authorization::CONSOLE_ROLES]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                return $user;
            }
        }

        return null;
    }

    private function getLdapAccessConfig(): array
    {
        $groups = array_merge(
            $this->splitLdapConfigValues((string) ($this->appConfig['ldap_hr_groups'] ?? '')),
            $this->splitLdapConfigValues((string) ($this->appConfig['ldap_is_groups'] ?? '')),
        );
        $ous = array_merge(
            $this->splitLdapConfigValues((string) ($this->appConfig['ldap_hr_ous'] ?? '')),
            $this->splitLdapConfigValues((string) ($this->appConfig['ldap_is_ous'] ?? '')),
        );
        $departments = array_merge(
            $this->splitLdapConfigValues((string) ($this->appConfig['ldap_hr_departments'] ?? '')),
            $this->splitLdapConfigValues((string) ($this->appConfig['ldap_is_departments'] ?? '')),
        );

        return [
            'configured' => $groups !== [] || $ous !== [] || $departments !== [],
            'groups' => $groups,
            'ous' => $ous,
            'departments' => $departments,
        ];
    }

    private function splitLdapConfigValues(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode('|', $raw)
        )));
    }

    private function matchesConfiguredGroup(array $profile, array $groups): bool
    {
        $configuredGroups = array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            $groups
        )));
        $configuredGroupCns = [];
        foreach ($configuredGroups as $configuredGroup) {
            if (preg_match('/^cn=([^,]+)/i', $configuredGroup, $matches) === 1) {
                $configuredGroupCns[] = strtolower(trim($matches[1]));
            }
        }

        foreach (($profile['groups'] ?? []) as $groupDn) {
            $normalizedDn = strtolower(trim((string) $groupDn));
            if (in_array($normalizedDn, $configuredGroups, true)) {
                return true;
            }

            if (preg_match('/^cn=([^,]+)/i', (string) $groupDn, $matches) === 1) {
                $commonName = strtolower(trim($matches[1]));
                if (in_array($commonName, $configuredGroups, true) || in_array($commonName, $configuredGroupCns, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matchesConfiguredOu(array $profile, array $ous): bool
    {
        $userDn = strtolower(trim((string) ($profile['distinguished_name'] ?? '')));
        if ($userDn === '') {
            return false;
        }

        foreach ($ous as $ou) {
            if ($ou !== '' && str_contains($userDn, $ou)) {
                return true;
            }
        }

        return false;
    }

    private function matchesConfiguredDepartment(array $profile, array $departments): bool
    {
        $userDept = strtolower(trim((string) ($profile['department'] ?? '')));
        return $userDept !== '' && in_array($userDept, $departments, true);
    }
}

