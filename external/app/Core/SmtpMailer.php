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
        $socket = @stream_socket_client(
            $host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeoutSeconds
        );

        if ($socket === false) {
            throw new \RuntimeException("SMTP connect failed ({$errno}): {$errstr}");
        }

        stream_set_timeout($socket, $this->timeoutSeconds);

        try {
            $this->expect($socket, 220);
            $this->write($socket, 'EHLO ' . gethostname());
            $ehloResponse = $this->readAll($socket);

            
            if (!$ssl && str_contains($ehloResponse, 'STARTTLS')) {
                $this->write($socket, 'STARTTLS');
                $this->expect($socket, 220);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('STARTTLS negotiation failed');
                }
                $this->write($socket, 'EHLO ' . gethostname());
                $this->readAll($socket);
            }

            
            $this->write($socket, 'AUTH LOGIN');
            $this->expect($socket, 334);
            $this->write($socket, base64_encode($this->username));
            $this->expect($socket, 334);
            $this->write($socket, base64_encode($this->password));
            $this->expect($socket, 235);

            
            $this->write($socket, 'MAIL FROM:<' . $this->fromAddress . '>');
            $this->expect($socket, 250);
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
}
