<?php
declare(strict_types=1);

function get_email_template(string $title, string $content): string {
    return "<html><body style='font-family:Inter,Arial,sans-serif;background:#0f172a;padding:24px;color:#111827;'><div style='max-width:620px;margin:auto;background:#ffffff;border-radius:22px;overflow:hidden;'><div style='background:linear-gradient(135deg,#7c3aed,#06b6d4);color:#fff;padding:32px;text-align:center;'><h1 style='margin:0;font-size:26px;'>" . h($title) . "</h1></div><div style='padding:28px;line-height:1.7;'>$content</div><div style='padding:18px;text-align:center;color:#64748b;font-size:12px;background:#f8fafc;'>Sent via PalmPocket</div></div></body></html>";
}

function smtp_read($socket): string {
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }
    return $response;
}

function smtp_command($socket, string $command, array $expectedCodes): string {
    fwrite($socket, $command . "\r\n");
    $response = smtp_read($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException(trim($response));
    }
    return $response;
}

function smtp_header(string $value): string {
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function send_mail_smtp(array $settings, string $to, string $subject, string $message, bool $isHtml = true): array {
    $host = trim($settings['smtp_host'] ?? '');
    $username = trim($settings['smtp_user'] ?? '');
    $password = (string)($settings['smtp_pass'] ?? '');
    $port = (int)($settings['smtp_port'] ?? 587);
    $secure = strtolower(trim($settings['smtp_secure'] ?? 'tls'));
    $fromEmail = trim($settings['smtp_from_email'] ?: $username);
    $fromName = trim($settings['smtp_from_name'] ?: 'PalmPocket');
    if ($to === '' || $host === '' || $username === '' || $password === '' || $fromEmail === '') {
        return ['ok' => false, 'message' => 'SMTP host, username, password, from email, and recipient are required.'];
    }
    $socket = null;
    try {
        $target = $secure === 'ssl' ? 'ssl://' . $host : $host;
        $socket = stream_socket_client($target . ':' . $port, $errno, $errstr, 30, STREAM_CLIENT_CONNECT);
        if (!$socket) {
            throw new RuntimeException($errstr ?: 'Could not connect to SMTP server.');
        }
        stream_set_timeout($socket, 30);
        $greeting = smtp_read($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException(trim($greeting));
        }
        $serverName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        smtp_command($socket, 'EHLO ' . $serverName, [250]);
        if ($secure === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not start TLS encryption.');
            }
            smtp_command($socket, 'EHLO ' . $serverName, [250]);
        }
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($username), [334]);
        smtp_command($socket, base64_encode($password), [235]);
        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);
        $contentType = $isHtml ? 'text/html' : 'text/plain';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . smtp_header($fromName) . ' <' . $fromEmail . '>',
            'To: <' . $to . '>',
            'Subject: ' . smtp_header($subject),
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType . '; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit'
        ];
        $body = str_replace(["\r\n", "\r"], "\n", $message);
        $body = str_replace("\n.", "\n..", $body);
        fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $body) . "\r\n.\r\n");
        $response = smtp_read($socket);
        if ((int)substr($response, 0, 3) !== 250) {
            throw new RuntimeException(trim($response));
        }
        smtp_command($socket, 'QUIT', [221]);
        fclose($socket);
        return ['ok' => true, 'message' => 'Email sent using native authenticated SMTP.'];
    } catch (Throwable $e) {
        if (is_resource($socket)) {
            fclose($socket);
        }
        return ['ok' => false, 'message' => 'SMTP error: ' . $e->getMessage()];
    }
}
