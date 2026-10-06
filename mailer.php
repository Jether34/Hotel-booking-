<?php
// Minimal mail transport: SMTP (STARTTLS/SSL), PHP mail(), or "log" to files.
// No Composer needed. Configure in config.php (MAIL_* constants).

function mime_header_text(string $s): string {
    $s = preg_replace('/[\r\n]+/', ' ', $s);
    if (preg_match('/[^\x20-\x7e]/', $s)) {
        return function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n")
            : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }
    return $s;
}

function mime_address(string $email, string $name = ''): string {
    $email = preg_replace('/[\r\n<>]+/', '', $email);
    $name  = trim(preg_replace('/[\r\n]+/', ' ', $name));
    if ($name === '') return $email;
    if (preg_match('/[^\x20-\x7e]/', $name)) return mime_header_text($name) . " <$email>";
    return '"' . str_replace(['"', '\\'], '', $name) . "\" <$email>";
}

function mime_build(string $html, string $text): array {
    $b = 'lrc_' . bin2hex(random_bytes(8));
    $body  = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n--$b--\r\n";
    return [$body, "multipart/alternative; boundary=\"$b\""];
}

function send_mail(string $to, string $toName, string $subject, string $html, ?string $text = null): bool {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if ($text === null) {
        $text = preg_replace('/<(br|\/p|\/tr|\/h\d|\/div)[^>]*>/i', "\n", $html);
        $text = trim(preg_replace("/\n{3,}/", "\n\n", html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8')));
    }
    try {
        switch (MAIL_DRIVER) {
            case 'smtp': return smtp_deliver($to, $toName, $subject, $html, $text);
            case 'mail': return native_mail($to, $toName, $subject, $html, $text);
            default:     return log_mail($to, $subject, $html);
        }
    } catch (Throwable $e) {
        error_log('Mail failed: ' . $e->getMessage());
        return false;
    }
}

function log_mail(string $to, string $subject, string $html): bool {
    $dir = __DIR__ . '/storage/mail';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $name = date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6) . '-' . preg_replace('/[^a-z0-9]+/i', '_', $to) . '.html';
    $head = '<!-- To: ' . h($to) . ' | Subject: ' . h($subject) . " -->\n";
    return file_put_contents("$dir/$name", $head . $html) !== false;
}

function native_mail(string $to, string $toName, string $subject, string $html, string $text): bool {
    [$body, $ctype] = mime_build($html, $text);
    $headers = "From: " . mime_address(MAIL_FROM, MAIL_FROM_NAME) . "\r\nMIME-Version: 1.0\r\nContent-Type: $ctype";
    return mail(mime_address($to, $toName), mime_header_text($subject), $body, $headers);
}

function smtp_deliver(string $to, string $toName, string $subject, string $html, string $text): bool {
    $secure = strtolower(MAIL_SECURE);
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . MAIL_HOST . ':' . MAIL_PORT, $errno, $errstr, 15);
    if (!$fp) throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
    stream_set_timeout($fp, 15);

    $read = function () use ($fp) {
        $out = '';
        while (($l = fgets($fp, 515)) !== false) { $out .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $out;
    };
    $cmd = function (string $c, array $ok, ?string $label = null) use ($fp, $read) {
        if ($c !== '') fwrite($fp, $c . "\r\n");
        $r = $read();
        if (!in_array((int)substr($r, 0, 3), $ok, true))
            throw new RuntimeException("SMTP error after '" . ($label ?? strtok($c, ' ')) . "': " . trim($r));
        return $r;
    };

    $ehloHost = parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost';
    $cmd('', [220], 'connect');
    $cmd("EHLO $ehloHost", [250]);
    if ($secure === 'tls') {
        $cmd('STARTTLS', [220]);
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))
            throw new RuntimeException('SMTP STARTTLS negotiation failed');
        $cmd("EHLO $ehloHost", [250]);
    }
    if (MAIL_USER !== '') {
        $cmd('AUTH LOGIN', [334]);
        $cmd(base64_encode(MAIL_USER), [334], 'AUTH user');
        $cmd(base64_encode(MAIL_PASS), [235], 'AUTH password');
    }
    $cmd('MAIL FROM:<' . MAIL_FROM . '>', [250]);
    $cmd('RCPT TO:<' . $to . '>', [250, 251]);
    $cmd('DATA', [354]);

    [$body, $ctype] = mime_build($html, $text);
    $msg  = 'Date: ' . date('r') . "\r\n";
    $msg .= 'From: ' . mime_address(MAIL_FROM, MAIL_FROM_NAME) . "\r\n";
    $msg .= 'To: ' . mime_address($to, $toName) . "\r\n";
    $msg .= 'Subject: ' . mime_header_text($subject) . "\r\n";
    $msg .= 'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $ehloHost . ">\r\n";
    $msg .= "MIME-Version: 1.0\r\nContent-Type: $ctype\r\n\r\n" . $body;
    $msg  = preg_replace('/^\./m', '..', $msg);
    fwrite($fp, $msg . "\r\n.\r\n");
    $cmd('', [250], 'message body');
    @fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return true;
}
