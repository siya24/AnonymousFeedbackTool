<?php
declare(strict_types=1);

namespace App\Services;

final class LdapAuthService
{
    public function __construct(private array $config)
    {
    }

    
    public function authenticate(string $identifier, string $password): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return null;
        }

        if (!function_exists('ldap_connect')) {
            return null;
        }

        $host = trim((string) ($this->config['ldap_host'] ?? ''));
        $baseDn = trim((string) ($this->config['ldap_base_dn'] ?? ''));

        if ($host === '' || $baseDn === '') {
            return null;
        }

        $port = (int) ($this->config['ldap_port'] ?? 389);
        $useTls = (bool) ($this->config['ldap_use_tls'] ?? false);

        $connection = @ldap_connect($host, $port);
        if ($connection === false) {
            return null;
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        if ($useTls) {
            @ldap_start_tls($connection);
        }

        $bindUser = trim((string) ($this->config['ldap_service_user'] ?? ''));
        $bindPassword = trim((string) ($this->config['ldap_service_password'] ?? ''));

        
        if ($bindUser !== '' && $bindPassword !== '') {
            if (@ldap_bind($connection, $bindUser, $bindPassword)) {
                $entry = $this->searchUserEntry($connection, $baseDn, $identifier);

                if ($entry !== null) {
                    $userDn = trim((string) ($entry['dn'] ?? ''));

                    if ($userDn !== '') {
                        @ldap_unbind($connection);

                        $verifyConnection = @ldap_connect($host, $port);
                        if ($verifyConnection !== false) {
                            @ldap_set_option($verifyConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
                            @ldap_set_option($verifyConnection, LDAP_OPT_REFERRALS, 0);

                            if ($useTls) {
                                @ldap_start_tls($verifyConnection);
                            }

                            $isValid = @ldap_bind($verifyConnection, $userDn, $password);
                            @ldap_unbind($verifyConnection);

                            if ($isValid) {
                                return $this->mapProfileFromEntry($entry, $identifier);
                            }
                        }

                        
                        $connection = @ldap_connect($host, $port);
                        if ($connection === false) {
                            return null;
                        }

                        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
                        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

                        if ($useTls) {
                            @ldap_start_tls($connection);
                        }
                    }
                }
            }
        }

        
        $candidates = $this->buildBindCandidates($identifier);
        $successfulBind = false;

        foreach ($candidates as $candidate) {
            if (@ldap_bind($connection, $candidate, $password)) {
                $successfulBind = true;
                break;
            }
        }

        if (!$successfulBind) {
            @ldap_unbind($connection);
            return null;
        }

        $entry = $this->searchUserEntry($connection, $baseDn, $identifier);
        @ldap_unbind($connection);

        if ($entry !== null) {
            return $this->mapProfileFromEntry($entry, $identifier);
        }

        $username = $this->extractUsername($identifier);
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : '';

        return [
            'name' => $username,
            'email' => $email,
            'email_upn' => $email,
            'username' => $username,
        ];
    }

    public function fetchHrPersonnel(): array
    {
        if (!function_exists('ldap_connect')) {
            return [];
        }

        $host = trim((string) ($this->config['ldap_host'] ?? ''));
        $baseDn = trim((string) ($this->config['ldap_base_dn'] ?? ''));
        $bindUser = trim((string) ($this->config['ldap_service_user'] ?? ''));
        $bindPassword = trim((string) ($this->config['ldap_service_password'] ?? ''));

        if ($host === '' || $baseDn === '' || $bindUser === '' || $bindPassword === '') {
            return [];
        }

        $port = (int) ($this->config['ldap_port'] ?? 389);
        $useTls = (bool) ($this->config['ldap_use_tls'] ?? false);

        $connection = @ldap_connect($host, $port);
        if ($connection === false) {
            return [];
        }

        @ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        @ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);

        if ($useTls) {
            @ldap_start_tls($connection);
        }

        if (!@ldap_bind($connection, $bindUser, $bindPassword)) {
            @ldap_unbind($connection);
            return [];
        }

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
            @ldap_unbind($connection);
            return [];
        }

        $entries = @ldap_get_entries($connection, $search);
        @ldap_unbind($connection);

        if (!is_array($entries) || (int) ($entries['count'] ?? 0) < 1) {
            return [];
        }

        $results = [];
        $seen = [];
        $count = (int) ($entries['count'] ?? 0);

        for ($i = 0; $i < $count; $i++) {
            $entry = $entries[$i] ?? null;
            if (!is_array($entry)) {
                continue;
            }

            $profile = $this->mapProfileFromEntry($entry, (string) ($entry['samaccountname'][0] ?? ''));

            $email = $this->resolveProfileEmail($profile);
            $username = strtolower(trim((string) ($profile['username'] ?? '')));
            $dedupeKey = $email !== '' ? 'email:' . $email : 'username:' . $username;
            if ($username === '' || isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $results[] = [
                'name' => (string) ($profile['name'] ?? ''),
                'first_name' => (string) ($profile['first_name'] ?? ''),
                'last_name' => (string) ($profile['last_name'] ?? ''),
                'email' => $email,
                'username' => $username,
                'employee_number' => (string) ($profile['employee_number'] ?? ''),
                'department_name' => (string) ($profile['department'] ?? ''),
                'position_title' => (string) ($profile['title'] ?? ''),
                'office_location' => (string) ($profile['location'] ?? ''),
            ];
        }

        return $results;
    }

    private function resolveProfileEmail(array $profile): string
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        if ($email !== '') {
            return $email;
        }

        $upn = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        if ($upn !== '') {
            return $upn;
        }

        $username = strtolower(trim((string) ($profile['username'] ?? '')));
        if ($username === '') {
            return '';
        }

        $domain = $this->resolveLdapEmailDomain();
        if ($domain === '') {
            return '';
        }

        return $username . '@' . $domain;
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

        $displayName = trim((string) ($entry['displayname'][0] ?? ''));
        $firstName = trim((string) ($entry['givenname'][0] ?? ''));
        $lastName = trim((string) ($entry['sn'][0] ?? ''));
        $foundEmail = strtolower(trim((string) ($entry['mail'][0] ?? '')));
        $samAccountName = trim((string) ($entry['samaccountname'][0] ?? ''));
        $upn = strtolower(trim((string) ($entry['userprincipalname'][0] ?? '')));
        $employeeNumber = trim((string) ($entry['employeeid'][0] ?? ''));
        $company = trim((string) ($entry['company'][0] ?? ''));

        

        if ($displayName === '' && ($firstName !== '' || $lastName !== '')) {
            $displayName = trim($firstName . ' ' . $lastName);
        }

        if ($displayName === '') {
            $displayName = $samAccountName !== '' ? $samAccountName : ($upn !== '' ? $upn : $username);
        }

        
        $rawGroups = $entry['memberof'] ?? [];
        $groups = [];
        $groupCount = (int) ($rawGroups['count'] ?? 0);
        for ($i = 0; $i < $groupCount; $i++) {
            $dn = trim((string) ($rawGroups[$i] ?? ''));
            if ($dn !== '') {
                $groups[] = $dn;
            }
        }

        
        $distinguishedName = trim((string) ($entry['distinguishedname'][0] ?? $entry['dn'] ?? ''));

        
        $department = trim((string) ($entry['department'][0] ?? ''));
        $title = trim((string) ($entry['title'][0] ?? ''));
        $office = trim((string) ($entry['physicaldeliveryofficename'][0] ?? ''));
        $city = trim((string) ($entry['l'][0] ?? ''));
        $location = '';
        if ($city !== '') {
            $location = $city;
        }
        if ($office !== '') {
            $location = $office;
        }

        return [
            'name'              => $displayName,
            'first_name'        => $firstName,
            'last_name'         => $lastName,
            'email'             => $foundEmail,
            'email_upn'         => $upn,
            'username'          => $samAccountName !== '' ? $samAccountName : ($upn !== '' ? $this->extractUsername($upn) : ($employeeNumber !== '' ? $employeeNumber : $username)),
            'groups'            => $groups,
            'distinguished_name' => $distinguishedName,
            'department'        => $department,
            'employee_number'   => $employeeNumber,
            'region'            => $company,
            'title'             => $title,
            'location'          => $location,
        ];
    }

    private function isHrProfile(array $profile): bool
    {
        $hrGroups = $this->splitPipe((string) ($this->config['ldap_hr_groups'] ?? ''));
        $hrOus = $this->splitPipe((string) ($this->config['ldap_hr_ous'] ?? ''));
        $hrDepartments = $this->splitPipe((string) ($this->config['ldap_hr_departments'] ?? ''));

        if (empty($hrGroups) && empty($hrOus) && empty($hrDepartments)) {
            return false;
        }

        foreach (($profile['groups'] ?? []) as $groupDn) {
            $normDn = strtolower(trim((string) $groupDn));
            if (in_array($normDn, $hrGroups, true)) {
                return true;
            }

            if (preg_match('/^cn=([^,]+)/i', (string) $groupDn, $m)) {
                $cn = strtolower(trim((string) $m[1]));
                if (in_array($cn, $hrGroups, true)) {
                    return true;
                }
            }
        }

        $dn = strtolower(trim((string) ($profile['distinguished_name'] ?? '')));
        foreach ($hrOus as $ou) {
            if ($ou !== '' && $dn !== '' && str_contains($dn, $ou)) {
                return true;
            }
        }

        $department = strtolower(trim((string) ($profile['department'] ?? '')));
        foreach ($hrDepartments as $hrDepartment) {
            if ($hrDepartment !== '' && $department !== '' && $department === $hrDepartment) {
                return true;
            }
        }

        return false;
    }

    private function splitPipe(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn(string $v): string => strtolower(trim($v)),
            explode('|', $raw)
        )));
    }

    private function resolveLdapEmailDomain(): string
    {
        $host = strtolower(trim((string) ($this->config['ldap_host'] ?? '')));
        if ($host !== '' && str_contains($host, '.')) {
            return $host;
        }

        $baseDn = strtolower(trim((string) ($this->config['ldap_base_dn'] ?? '')));
        if ($baseDn !== '') {
            preg_match_all('/dc=([^,]+)/i', $baseDn, $matches);
            if (!empty($matches[1])) {
                $parts = array_map(static fn($part): string => trim((string) $part), $matches[1]);
                $parts = array_values(array_filter($parts));
                if ($parts !== []) {
                    return implode('.', $parts);
                }
            }
        }

        return '';
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
