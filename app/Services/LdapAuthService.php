<?php
declare(strict_types=1);

namespace App\Services;

final class LdapAuthService
{
    public function __construct(private array $config)
    {
    }

    /**
     * Authenticate against LDAP and return profile data on success.
     *
     * @return array{name:string,email:string,username:string,email_upn:string}|null
     */
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

        // AD.cs pattern: bind with service account, search user in subtree.
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

                        // Reconnect for direct-bind fallback after DN verify failure.
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

        // Fallback for environments that do not expose a service bind account.
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

    /**
     * @return string[]
     */
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

        // Mirrors AD.cs search intent and adds UPN/email support.
        $filter = '(&(objectCategory=person)(objectClass=user)(|(sAMAccountName=' . $escapedUsername . ')(employeeID=' . $escapedUsername . ')' . $upnFilter . $mailFilter . ')))';
        $attributes = ['dn', 'displayName', 'mail', 'sAMAccountName', 'userPrincipalName'];
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

    /**
     * @return array{name:string,email:string,username:string,email_upn:string}
     */
    private function mapProfileFromEntry(array $entry, string $identifier): array
    {
        $username = $this->extractUsername($identifier);

        $displayName = trim((string) ($entry['displayname'][0] ?? ''));
        $foundEmail = strtolower(trim((string) ($entry['mail'][0] ?? '')));
        $samAccountName = trim((string) ($entry['samaccountname'][0] ?? ''));
        $upn = strtolower(trim((string) ($entry['userprincipalname'][0] ?? '')));

        if ($displayName === '') {
            $displayName = $samAccountName !== '' ? $samAccountName : $username;
        }

        return [
            'name' => $displayName,
            'email' => $foundEmail,
            'email_upn' => $upn,
            'username' => $samAccountName !== '' ? $samAccountName : $username,
        ];
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
