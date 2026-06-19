<?php
declare(strict_types=1);

namespace App\Core;


final class SmtpMailer
{
    public function __construct(
        private readonly string $host,
        private readonly int    $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $fromAddress,
        private readonly string $fromName = '',
        private readonly string $tlsServerName = '',
        private readonly bool   $tlsVerify = true,
        private readonly string $authMode = 'auto',
        private readonly bool   $authOptional = false,
        private readonly int    $timeoutSeconds = 15,
    ) {}

    
    public function send(string $to, string $subject, string $body): void
    {
        $this->sendMail($to, $subject, $body, false);
    }

    
    public function sendHtml(string $to, string $subject, string $htmlBody, string $plainBody = ''): void
    {
        $this->sendMail($to, $subject, $htmlBody, true, $plainBody);
    }

    private function sendMail(string $to, string $subject, string $body, bool $html, string $plainFallback = ''): void
    {
        $ssl = ($this->port === 465);
        $host = $ssl ? 'ssl://' . $this->host : $this->host;
        $socketContext = stream_context_create([
            'ssl' => $this->buildTlsContextOptions(),
        ]);
        $socket = @stream_socket_client(
            $host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $socketContext
        );

        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed ({$errno}): {$errstr}");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            $this->expect($socket, 220);
            $this->write($socket, 'EHLO ' . gethostname());
            $ehloResponse = $this->readAll($socket);
            $authMethods = $this->parseAuthMethods($ehloResponse);

            
            if (!$ssl && str_contains(strtoupper($ehloResponse), 'STARTTLS')) {
                $this->write($socket, 'STARTTLS');
                $this->expect($socket, 220);
                if (!@stream_socket_enable_crypto($socket, true, $this->getTlsCryptoMethod())) {
                    throw new \RuntimeException('STARTTLS negotiation failed');
                }
                $this->write($socket, 'EHLO ' . gethostname());
                $ehloResponse = $this->readAll($socket);
                $authMethods = $this->parseAuthMethods($ehloResponse);
            }

            $authFailure = null;
            if ($this->shouldAuthenticate()) {
                try {
                    $this->authenticate($socket, $authMethods);
                } catch (\RuntimeException $e) {
                    if (!$this->authOptional) {
                        throw $e;
                    }
                    $authFailure = $e->getMessage();
                }
            }

            
            $this->write($socket, 'MAIL FROM:<' . $this->fromAddress . '>');
            try {
                $this->expect($socket, 250);
            } catch (\RuntimeException $e) {
                if ($authFailure !== null) {
                    throw new \RuntimeException($e->getMessage() . ' (prior auth failure: ' . $authFailure . ')');
                }
                throw $e;
            }
            $this->write($socket, 'RCPT TO:<' . $to . '>');
            $this->expect($socket, 250);

            
            $this->write($socket, 'DATA');
            $this->expect($socket, 354);

            $date  = date('r');
            $msgId = '<' . bin2hex(random_bytes(8)) . '@' . gethostname() . '>';
            $from  = $this->formatAddress($this->fromAddress, $this->fromName);

            if ($html) {
                $boundary = '----=_Part_' . bin2hex(random_bytes(6));
                $plain = $plainFallback !== '' ? $plainFallback : strip_tags($body);
                $headers = implode("\r\n", [
                    'Date: ' . $date,
                    'Message-ID: ' . $msgId,
                    'From: ' . $from,
                    'To: ' . $to,
                    'Subject: ' . $this->encodeHeader($subject),
                    'MIME-Version: 1.0',
                    'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
                ]);
                $message = $headers . "\r\n\r\n"
                    . '--' . $boundary . "\r\n"
                    . "Content-Type: text/plain; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                    . quoted_printable_encode($plain) . "\r\n\r\n"
                    . '--' . $boundary . "\r\n"
                    . "Content-Type: text/html; charset=UTF-8\r\n"
                    . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
                    . quoted_printable_encode($body) . "\r\n\r\n"
                    . '--' . $boundary . '--';
            } else {
                $headers = implode("\r\n", [
                    'Date: ' . $date,
                    'Message-ID: ' . $msgId,
                    'From: ' . $from,
                    'To: ' . $to,
                    'Subject: ' . $this->encodeHeader($subject),
                    'MIME-Version: 1.0',
                    'Content-Type: text/plain; charset=UTF-8',
                    'Content-Transfer-Encoding: quoted-printable',
                ]);
                $message = $headers . "\r\n\r\n" . quoted_printable_encode($body);
            }

            
            $message = preg_replace('/^\.$/m', '..', $message);

            fwrite($socket, $message . "\r\n.\r\n");
            $this->expect($socket, 250);

            $this->write($socket, 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    

    private function write($socket, string $command): void
    {
        fwrite($socket, $command . "\r\n");
    }

    private function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    private function readAll($socket): string
    {
        return $this->read($socket);
    }

    private function expect($socket, int $code): string
    {
        $response = $this->read($socket);
        $actual   = (int) substr($response, 0, 3);
        if ($actual !== $code) {
            throw new \RuntimeException(
                "SMTP expected {$code}, got {$actual}: " . trim($response)
            );
        }
        return $response;
    }

    private function formatAddress(string $email, string $name): string
    {
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader(string $value): string
    {
        if (mb_detect_encoding($value, 'ASCII', true) !== false && !preg_match('/[^\x20-\x7E]/', $value)) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function shouldAuthenticate(): bool
    {
        $mode = $this->normalizeAuthMode();
        if ($mode === 'none') {
            return false;
        }
        return trim($this->username) !== '' && trim($this->password) !== '';
    }

    private function buildTlsContextOptions(): array
    {
        $options = [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ];

        $peerName = $this->tlsServerName !== '' ? $this->tlsServerName : $this->host;
        if ($peerName !== '') {
            $options['SNI_enabled'] = true;
            $options['SNI_server_name'] = $peerName;
            $options['peer_name'] = $peerName;
        }

        return $options;
    }

    private function normalizeAuthMode(): string
    {
        $mode = strtolower(trim($this->authMode));
        return in_array($mode, ['auto', 'login', 'plain', 'none'], true) ? $mode : 'auto';
    }

    private function parseAuthMethods(string $ehloResponse): array
    {
        $methods = [];
        $lines = preg_split('/\r\n|\n|\r/', $ehloResponse) ?: [];
        foreach ($lines as $line) {
            if (!preg_match('/^\d{3}[\-\s]AUTH\s+(.+)$/i', trim($line), $matches)) {
                continue;
            }
            $tokens = preg_split('/\s+/', strtoupper(trim($matches[1]))) ?: [];
            foreach ($tokens as $token) {
                if (str_starts_with($token, 'AUTH=')) {
                    $token = substr($token, 5);
                }
                if ($token !== '') {
                    $methods[$token] = true;
                }
            }
        }
        return array_keys($methods);
    }

    private function authenticate($socket, array $authMethods): void
    {
        $mode = $this->normalizeAuthMode();

        if ($mode === 'login') {
            $this->authenticateLogin($socket);
            return;
        }

        if ($mode === 'plain') {
            $this->authenticatePlain($socket);
            return;
        }

        if ($mode === 'auto' && $authMethods === []) {
            return;
        }

        $attemptOrder = [];
        if (in_array('LOGIN', $authMethods, true)) {
            $attemptOrder[] = 'login';
        }
        if (in_array('PLAIN', $authMethods, true)) {
            $attemptOrder[] = 'plain';
        }
        if ($attemptOrder === [] && $mode === 'auto') {
            $supported = implode(', ', $authMethods);
            throw new \RuntimeException(
                'SMTP advertised unsupported auth methods' . ($supported !== '' ? ': ' . $supported : '')
            );
        }

        $errors = [];
        foreach ($attemptOrder as $attempt) {
            try {
                if ($attempt === 'login') {
                    $this->authenticateLogin($socket);
                    return;
                }
                $this->authenticatePlain($socket);
                return;
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        $methods = implode(', ', $authMethods);
        $details = implode(' | ', $errors);
        throw new \RuntimeException(
            'SMTP authentication failed'
            . ($methods !== '' ? ' (server methods: ' . $methods . ')' : '')
            . ($details !== '' ? ': ' . $details : '')
        );
    }

    private function authenticateLogin($socket): void
    {
        $this->write($socket, 'AUTH LOGIN');
        $this->expect($socket, 334);
        $this->write($socket, base64_encode($this->username));
        $this->expect($socket, 334);
        $this->write($socket, base64_encode($this->password));
        $this->expect($socket, 235);
    }

    private function authenticatePlain($socket): void
    {
        $this->write($socket, 'AUTH PLAIN ' . base64_encode("\0" . $this->username . "\0" . $this->password));
        $response = $this->read($socket);
        $actual = (int) substr($response, 0, 3);

        if ($actual === 235) {
            return;
        }

        if ($actual === 334) {
            $this->write($socket, base64_encode("\0" . $this->username . "\0" . $this->password));
            $this->expect($socket, 235);
            return;
        }

        throw new \RuntimeException("SMTP expected 235, got {$actual}: " . trim($response));
    }

    private function getTlsCryptoMethod(): int
    {
        $method = 0;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) {
            $method |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        }
        if ($method === 0) {
            return STREAM_CRYPTO_METHOD_TLS_CLIENT;
        }
        return $method;
    }
}
