<?php
declare(strict_types=1);

namespace App\Services;

final class LdapAuthService
{
    private LdapProfileService $profileService;

    public function __construct(private array $config)
    {
        $this->profileService = new LdapProfileService($config);
    }

    public function authenticate(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        if (!$this->canAuthenticate($identifier, $password)) {
            return null;
        }

        $host = trim((string) ($this->config['ldap_host'] ?? ''));
        $baseDn = trim((string) ($this->config['ldap_base_dn'] ?? ''));
        $port = (int) ($this->config['ldap_port'] ?? 389);
        $useTls = (bool) ($this->config['ldap_use_tls'] ?? false);

        $connection = $this->createConfiguredConnection($host, $port, $useTls);
        if ($connection === null) {
            return null;
        }

        $profile = $this->authenticateViaServiceAccount(
            $connection,
            $host,
            $port,
            $useTls,
            $baseDn,
            $identifier,
            $password
        );

        if ($profile === null) {
            $profile = $this->authenticateViaBindCandidates($connection, $baseDn, $identifier, $password);
        }

        $this->unbindConnection($connection);
        return $profile;
    }

    public function fetchHrPersonnel(): array
    {
        $host = trim((string) ($this->config['ldap_host'] ?? ''));
        $baseDn = trim((string) ($this->config['ldap_base_dn'] ?? ''));
        $bindUser = trim((string) ($this->config['ldap_service_user'] ?? ''));
        $bindPassword = trim((string) ($this->config['ldap_service_password'] ?? ''));

        if (!function_exists('ldap_connect') || $host === '' || $baseDn === '' || $bindUser === '' || $bindPassword === '') {
            return [];
        }

        $port = (int) ($this->config['ldap_port'] ?? 389);
        $useTls = (bool) ($this->config['ldap_use_tls'] ?? false);

        $connection = $this->createConfiguredConnection($host, $port, $useTls);
        if ($connection === null) {
            return [];
        }

        $results = [];
        if (@ldap_bind($connection, $bindUser, $bindPassword)) {
            $entries = $this->searchPersonnelEntries($connection, $baseDn);
            if ($entries !== []) {
                $results = $this->mapPersonnelEntries($entries);
            }
        }

        $this->unbindConnection($connection);
        return $results;
    }

    private function resolveProfileEmail(array $profile): string
    {
        return $this->profileService->resolveProfileEmail($profile);
    }

    private function canAuthenticate(string $identifier, string $password): bool
    {
        if ($identifier === '' || $password === '' || !function_exists('ldap_connect')) {
            return false;
        }

        $host = trim((string) ($this->config['ldap_host'] ?? ''));
        $baseDn = trim((string) ($this->config['ldap_base_dn'] ?? ''));
        return $host !== '' && $baseDn !== '';
    }

    private function createConfiguredConnection(string $host, int $port, bool $useTls)
    {
        $connection = @ldap_connect($host, $port);
        if ($connection === false) {
            return null;
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        if ($useTls) {
            @ldap_start_tls($connection);
        }

        return $connection;
    }

    private function authenticateViaServiceAccount($connection, string $host, int $port, bool $useTls, string $baseDn, string $identifier, string $password): ?array
    {
        $bindUser = trim((string) ($this->config['ldap_service_user'] ?? ''));
        $bindPassword = trim((string) ($this->config['ldap_service_password'] ?? ''));
        if ($bindUser === '' || $bindPassword === '' || !@ldap_bind($connection, $bindUser, $bindPassword)) {
            return null;
        }

        $entry = $this->searchUserEntry($connection, $baseDn, $identifier);
        if ($entry === null || !$this->verifyUserEntryCredentials($entry, $host, $port, $useTls, $password)) {
            return null;
        }

        return $this->mapProfileFromEntry($entry, $identifier);
    }

    private function verifyUserEntryCredentials(array $entry, string $host, int $port, bool $useTls, string $password): bool
    {
        $userDn = trim((string) ($entry['dn'] ?? ''));
        if ($userDn === '') {
            return false;
        }

        $verifyConnection = $this->createConfiguredConnection($host, $port, $useTls);
        if ($verifyConnection === null) {
            return false;
        }

        $isValid = @ldap_bind($verifyConnection, $userDn, $password);
        $this->unbindConnection($verifyConnection);
        return $isValid;
    }

    private function authenticateViaBindCandidates($connection, string $baseDn, string $identifier, string $password): ?array
    {
        $profile = null;
        if ($this->bindAnyCandidate($connection, $identifier, $password)) {
            $entry = $this->searchUserEntry($connection, $baseDn, $identifier);
            $profile = $entry !== null ? $this->mapProfileFromEntry($entry, $identifier) : $this->fallbackProfile($identifier);
        }

        return $profile;
    }

    private function bindAnyCandidate($connection, string $identifier, string $password): bool
    {
        foreach ($this->buildBindCandidates($identifier) as $candidate) {
            if (@ldap_bind($connection, $candidate, $password)) {
                return true;
            }
        }

        return false;
    }

    private function fallbackProfile(string $identifier): array
    {
        $username = $this->extractUsername($identifier);
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : '';

        return [
            'name' => $username,
            'email' => $email,
            'email_upn' => $email,
            'username' => $username,
        ];
    }

    private function unbindConnection($connection): void
    {
        if ($connection !== null) {
            @ldap_unbind($connection);
        }
    }

    private function searchPersonnelEntries($connection, string $baseDn): array
    {
        $filter = '(&(objectCategory=person)(objectClass=user))';
        $attributes = [
            'dn',
            'displayName',
            'mail',
            'sAMAccountName',
            'givenName',
            'sn',
            'userPrincipalName',
            'memberOf',
            'department',
            'employeeID',
            'company',
            'distinguishedName',
            'title',
            'physicalDeliveryOfficeName',
            'l',
        ];

        $search = @ldap_search($connection, $baseDn, $filter, $attributes);
        if ($search === false) {
            return [];
        }

        $entries = @ldap_get_entries($connection, $search);
        return is_array($entries) && (int) ($entries['count'] ?? 0) > 0 ? $entries : [];
    }

    private function mapPersonnelEntries(array $entries): array
    {
        $results = [];
        $seen = [];
        $count = (int) ($entries['count'] ?? 0);

        for ($i = 0; $i < $count; $i++) {
            $entry = $entries[$i] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $profile = $this->mapProfileFromEntry($entry, (string) ($entry['samaccountname'][0] ?? ''));
            $matchedRole = $this->profileService->resolvePersonnelRole($profile);
            $username = strtolower(trim((string) ($profile['username'] ?? '')));
            $email = $this->profileService->resolveProfileEmail($profile);
            $dedupeKey = $email !== '' ? 'email:' . $email : 'username:' . $username;

            if ($matchedRole === null || $username === '' || isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $results[] = [
                'name' => (string) ($profile['name'] ?? ''),
                'first_name' => (string) ($profile['first_name'] ?? ''),
                'last_name' => (string) ($profile['last_name'] ?? ''),
                'email' => $email,
                'username' => $username,
                'role' => $matchedRole,
                'employee_number' => (string) ($profile['employee_number'] ?? ''),
                'department_name' => (string) ($profile['department'] ?? ''),
                'position_title' => (string) ($profile['title'] ?? ''),
                'office_location' => (string) ($profile['location'] ?? ''),
            ];
        }

        return $results;
    }

    
    private function buildBindCandidates(string $identifier): array
    {
        $domain = trim((string) ($this->config['ldap_domain'] ?? ''));
        $bindPattern = trim((string) ($this->config['ldap_bind_pattern'] ?? ''));
        $username = $this->extractUsername($identifier);

        $candidates = [];

        if (str_contains($identifier, '@') || str_contains($identifier, '\\')) {
            $candidates[] = $identifier;
        }

        if ($bindPattern !== '' && str_contains($bindPattern, '%s')) {
            $candidates[] = sprintf($bindPattern, $username);
        }

        if ($domain !== '') {
            if (str_contains($domain, '.')) {
                $candidates[] = $username . '@' . strtolower($domain);
            } else {
                $candidates[] = $domain . '\\' . $username;
            }
        }

        $candidates[] = $username;

        return array_values(array_unique(array_filter($candidates)));
    }

    private function extractUsername(string $identifier): string
    {
        if (str_contains($identifier, '\\')) {
            $parts = explode('\\', $identifier);
            return trim((string) end($parts));
        }

        if (str_contains($identifier, '@')) {
            $parts = explode('@', $identifier, 2);
            return trim((string) $parts[0]);
        }

        return trim($identifier);
    }

    private function searchUserEntry($connection, string $baseDn, string $identifier): ?array
    {
        $email = strtolower((string) (filter_var($identifier, FILTER_VALIDATE_EMAIL) ? $identifier : ''));
        $username = $this->extractUsername($identifier);

        $escapedEmail = $this->escapeLdapFilter($email);
        $escapedUsername = $this->escapeLdapFilter($username);

        $upnFilter = $escapedEmail !== '' ? '(userPrincipalName=' . $escapedEmail . ')' : '(userPrincipalName=' . $escapedUsername . ')';
        $mailFilter = $escapedEmail !== '' ? '(mail=' . $escapedEmail . ')' : '(mail=' . $escapedUsername . ')';

        
        $filter = '(&(objectCategory=person)(objectClass=user)(|(sAMAccountName=' . $escapedUsername . ')(employeeID=' . $escapedUsername . ')' . $upnFilter . $mailFilter . ')))';
        $attributes = [
            'dn',
            'displayName',
            'mail',
            'sAMAccountName',
            'givenName',
            'sn',
            'userPrincipalName',
            'memberOf',
            'department',
            'employeeID',
            'company',
            'title',
            'physicalDeliveryOfficeName',
            'l',
            'distinguishedName',
        ];
        $search = @ldap_search($connection, $baseDn, $filter, $attributes);

        if ($search === false) {
            return null;
        }

        $entries = @ldap_get_entries($connection, $search);
        if (!is_array($entries) || ($entries['count'] ?? 0) < 1) {
            return null;
        }

        return $entries[0];
    }

    private function mapProfileFromEntry(array $entry, string $identifier): array
    {
        $username = $this->extractUsername($identifier);
        return $this->profileService->mapProfileFromEntry($entry, $identifier, $username);
    }

    private function escapeLdapFilter(string $value): string
    {
        return str_replace(
            ['\\', '*', '(', ')', "\x00"],
            ['\\5c', '\\2a', '\\28', '\\29', '\\00'],
            $value
        );
    }

}
