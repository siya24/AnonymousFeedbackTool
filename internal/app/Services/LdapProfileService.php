<?php declare(strict_types=1);

namespace App\Services;

final class LdapProfileService
{
    public function __construct(private array $config)
    {
    }

    public function resolveProfileEmail(array $profile): string
    {
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $upn = strtolower(trim((string) ($profile['email_upn'] ?? '')));
        $username = strtolower(trim((string) ($profile['username'] ?? '')));

        $resolved = $email !== '' ? $email : $upn;
        if ($resolved === '' && $username !== '') {
            $domain = $this->resolveLdapEmailDomain();
            if ($domain !== '') {
                $resolved = $username . '@' . $domain;
            }
        }

        return $resolved;
    }

    public function mapProfileFromEntry(array $entry, string $identifier, string $username): array
    {
        $username = $username !== '' ? $username : $this->extractUsername($identifier);
        $displayName = trim((string) ($entry['displayname'][0] ?? ''));
        $firstName = trim((string) ($entry['givenname'][0] ?? ''));
        $lastName = trim((string) ($entry['sn'][0] ?? ''));
        $foundEmail = strtolower(trim((string) ($entry['mail'][0] ?? '')));
        $samAccountName = trim((string) ($entry['samaccountname'][0] ?? ''));
        $upn = strtolower(trim((string) ($entry['userprincipalname'][0] ?? '')));
        $employeeNumber = trim((string) ($entry['employeeid'][0] ?? ''));
        $company = trim((string) ($entry['company'][0] ?? ''));

        $displayName = $this->resolveDisplayName($displayName, $firstName, $lastName, $samAccountName, $upn, $username);
        $groups = $this->extractGroups($entry);
        $distinguishedName = trim((string) ($entry['distinguishedname'][0] ?? $entry['dn'] ?? ''));
        $department = trim((string) ($entry['department'][0] ?? ''));
        $title = trim((string) ($entry['title'][0] ?? ''));
        $location = $this->resolveLocation($entry);

        return [
            'name' => $displayName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $foundEmail,
            'email_upn' => $upn,
            'username' => $this->resolveProfileUsername($samAccountName, $upn, $employeeNumber, $username),
            'groups' => $groups,
            'distinguished_name' => $distinguishedName,
            'department' => $department,
            'employee_number' => $employeeNumber,
            'region' => $company,
            'title' => $title,
            'location' => $location,
        ];
    }

    public function resolvePersonnelRole(array $profile): ?string
    {
        $hrGroups = $this->splitPipe((string) ($this->config['ldap_hr_groups'] ?? ''));
        $ethicsGroups = $this->splitPipe((string) ($this->config['ldap_is_groups'] ?? ''));
        $hrOus = $this->splitPipe((string) ($this->config['ldap_hr_ous'] ?? ''));
        $ethicsOus = $this->splitPipe((string) ($this->config['ldap_is_ous'] ?? ''));
        $hrDepartments = $this->splitPipe((string) ($this->config['ldap_hr_departments'] ?? ''));
        $ethicsDepartments = $this->splitPipe((string) ($this->config['ldap_is_departments'] ?? ''));

        $role = null;
        $hasConfig = !empty($hrGroups) || !empty($ethicsGroups)
            || !empty($hrOus) || !empty($ethicsOus)
            || !empty($hrDepartments) || !empty($ethicsDepartments);

        if ($hasConfig) {
            if ($this->matchesLdapProfile($profile, $hrGroups, $hrOus, $hrDepartments)) {
                $role = 'hr';
            }

            if ($role === null
                && $this->matchesLdapProfile($profile, $ethicsGroups, $ethicsOus, $ethicsDepartments)
                && $this->isDeveloperProfile($profile)
            ) {
                $role = 'ethics';
            }
        }

        return $role;
    }

    private function matchesLdapProfile(array $profile, array $groups, array $ous, array $departments): bool
    {
        return (!empty($groups) || !empty($ous) || !empty($departments))
            && (
                $this->matchesGroups($profile, $groups)
                || $this->matchesOus($profile, $ous)
                || $this->matchesDepartments($profile, $departments)
            );
    }

    private function isDeveloperProfile(array $profile): bool
    {
        $keywords = $this->splitPipe((string) (
            $this->config['ldap_is_developer_keywords']
            ?? 'developer|software|programmer|engineer|devops'
        ));
        if ($keywords === []) {
            return false;
        }

        $title = strtolower(trim((string) ($profile['title'] ?? '')));
        $department = strtolower(trim((string) ($profile['department'] ?? '')));
        $username = strtolower(trim((string) ($profile['username'] ?? '')));

        foreach ($keywords as $keyword) {
            if ($keyword === '') {
                continue;
            }

            if (
                ($title !== '' && str_contains($title, $keyword))
                || ($department !== '' && str_contains($department, $keyword))
                || ($username !== '' && str_contains($username, $keyword))
            ) {
                return true;
            }
        }

        return false;
    }

    private function splitPipe(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn(string $value): string => strtolower(trim($value)),
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

    private function resolveDisplayName(string $displayName, string $firstName, string $lastName, string $samAccountName, string $upn, string $username): string
    {
        $resolved = $displayName;
        if ($resolved === '' && ($firstName !== '' || $lastName !== '')) {
            $resolved = trim($firstName . ' ' . $lastName);
        }

        if ($resolved === '') {
            $resolved = $samAccountName;
            if ($resolved === '') {
                $resolved = $upn !== '' ? $upn : $username;
            }
        }

        return $resolved;
    }

    private function extractGroups(array $entry): array
    {
        $groups = [];
        $rawGroups = $entry['memberof'] ?? [];
        $groupCount = (int) ($rawGroups['count'] ?? 0);
        for ($i = 0; $i < $groupCount; $i++) {
            $dn = trim((string) ($rawGroups[$i] ?? ''));
            if ($dn !== '') {
                $groups[] = $dn;
            }
        }

        return $groups;
    }

    private function resolveLocation(array $entry): string
    {
        $office = trim((string) ($entry['physicaldeliveryofficename'][0] ?? ''));
        $city = trim((string) ($entry['l'][0] ?? ''));
        return $office !== '' ? $office : $city;
    }

    private function resolveProfileUsername(string $samAccountName, string $upn, string $employeeNumber, string $username): string
    {
        $resolved = $samAccountName;
        if ($resolved === '' && $upn !== '') {
            $resolved = $this->extractUsername($upn);
        }

        if ($resolved === '' && $employeeNumber !== '') {
            $resolved = $employeeNumber;
        }

        return $resolved !== '' ? $resolved : $username;
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

    private function matchesGroups(array $profile, array $groups): bool
    {
        foreach (($profile['groups'] ?? []) as $groupDn) {
            $normDn = strtolower(trim((string) $groupDn));
            if (in_array($normDn, $groups, true)) {
                return true;
            }

            if (preg_match('/^cn=([^,]+)/i', (string) $groupDn, $matches) === 1) {
                $commonName = strtolower(trim((string) $matches[1]));
                if (in_array($commonName, $groups, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function matchesOus(array $profile, array $ous): bool
    {
        $dn = strtolower(trim((string) ($profile['distinguished_name'] ?? '')));
        if ($dn === '') {
            return false;
        }

        foreach ($ous as $ou) {
            if ($ou !== '' && str_contains($dn, $ou)) {
                return true;
            }
        }

        return false;
    }

    private function matchesDepartments(array $profile, array $departments): bool
    {
        $department = strtolower(trim((string) ($profile['department'] ?? '')));
        return $department !== '' && in_array($department, $departments, true);
    }
}
