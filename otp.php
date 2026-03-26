<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function generateOtpCode(): string
{
    return (string) random_int(100000, 999999);
}

function sendOtpEmail(string $email, string $otp): bool
{
    $subject = 'Your Admission Verification Code';
    $body = "Your OTP code is: {$otp}. It is valid for 5 minutes.";
    return sendNotificationEmail($email, $subject, $body, 'otp-email', $otp);
}

function sendNotificationEmail(string $email, string $subject, string $body, string $fallbackChannel = 'email', string $fallbackValue = ''): bool
{
    if (SMTP_HOST !== '' && SMTP_USERNAME !== '' && SMTP_PASSWORD !== '') {
        $sent = sendEmailViaSmtp($email, $subject, $body);
    } else {
        $headers = 'From: ' . OTP_EMAIL_FROM . "\r\n"
            . 'MIME-Version: 1.0' . "\r\n"
            . 'Content-Type: text/plain; charset=UTF-8';
        $sent = @mail($email, $subject, $body, $headers);
    }

    if ($sent) {
        return true;
    }

    logOtpFallback($fallbackChannel, $email, $fallbackValue !== '' ? $fallbackValue : $subject);
    return false;
}

function sendEmailViaSmtp(string $toEmail, string $subject, string $body): bool
{
    $remoteHost = SMTP_HOST;
    $port = SMTP_PORT;
    $transport = strtolower(SMTP_ENCRYPTION) === 'ssl' ? 'ssl://' . $remoteHost : $remoteHost;

    $socket = @stream_socket_client(
        $transport . ':' . $port,
        $errorNumber,
        $errorMessage,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!is_resource($socket)) {
        return false;
    }

    stream_set_timeout($socket, 20);

    if (!smtpExpect($socket, [220])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'EHLO localhost', [250])) {
        fclose($socket);
        return false;
    }

    if (strtolower(SMTP_ENCRYPTION) === 'tls') {
        if (!smtpCommand($socket, 'STARTTLS', [220])) {
            fclose($socket);
            return false;
        }

        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);
            return false;
        }

        if (!smtpCommand($socket, 'EHLO localhost', [250])) {
            fclose($socket);
            return false;
        }
    }

    if (!smtpCommand($socket, 'AUTH LOGIN', [334])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, base64_encode(SMTP_USERNAME), [334])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, base64_encode(SMTP_PASSWORD), [235])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'MAIL FROM:<' . OTP_EMAIL_FROM . '>', [250])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'RCPT TO:<' . $toEmail . '>', [250, 251])) {
        fclose($socket);
        return false;
    }

    if (!smtpCommand($socket, 'DATA', [354])) {
        fclose($socket);
        return false;
    }

    $headers = [
        'From: ' . formatEmailHeader(OTP_EMAIL_FROM_NAME, OTP_EMAIL_FROM),
        'To: ' . $toEmail,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];
    $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

    if (!smtpCommand($socket, $message, [250])) {
        fclose($socket);
        return false;
    }

    smtpCommand($socket, 'QUIT', [221]);
    fclose($socket);
    return true;
}

function smtpCommand($socket, string $command, array $expectedCodes): bool
{
    fwrite($socket, $command . "\r\n");
    return smtpExpect($socket, $expectedCodes);
}

function smtpExpect($socket, array $expectedCodes): bool
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ($response === '') {
        return false;
    }

    $code = (int) substr($response, 0, 3);
    return in_array($code, $expectedCodes, true);
}

function formatEmailHeader(string $name, string $email): string
{
    if ($name === '') {
        return $email;
    }

    return sprintf('"%s" <%s>', addslashes($name), $email);
}

function sendOtpSms(string $phone, string $otp): bool
{
    $normalizedPhone = preg_replace('/\D+/', '', $phone ?? '');
    if ($normalizedPhone === '') {
        return false;
    }

    if (OTP_SMS_PROVIDER === 'demo') {
        logOtpFallback('sms-demo', $normalizedPhone, $otp);
        return true;
    }

    if (OTP_SMS_PROVIDER === 'fast2sms') {
        return sendOtpViaFast2Sms($normalizedPhone, $otp);
    }

    if (OTP_SMS_PROVIDER === 'twilio') {
        return sendOtpViaTwilio($normalizedPhone, $otp);
    }

    logOtpFallback('sms', $normalizedPhone, $otp);
    return false;
}

function dispatchOtpByPreference(string $preference, string $email, string $phone, string $otp): array
{
    $emailSent = false;
    $smsSent = false;

    if (OTP_SMS_PROVIDER === 'demo') {
        if ($preference === 'email' || $preference === 'both') {
            logOtpFallback('email-demo', $email, $otp);
            $emailSent = true;
        }
        if ($preference === 'sms' || $preference === 'both') {
            logOtpFallback('sms-demo', $phone, $otp);
            $smsSent = true;
        }
    }

    if ($preference === 'email' || $preference === 'both') {
        $emailSent = $emailSent || sendOtpEmail($email, $otp);
    }

    if ($preference === 'sms' || $preference === 'both') {
        $smsSent = $smsSent || sendOtpSms($phone, $otp);
    }

    return [
        'email' => $emailSent,
        'sms' => $smsSent,
        'ok' => ($preference === 'both')
            ? ($emailSent || $smsSent)
            : (($preference === 'email') ? $emailSent : $smsSent),
    ];
}

function sendOtpViaFast2Sms(string $phone, string $otp): bool
{
    if (FAST2SMS_API_KEY === '') {
        logOtpFallback('sms-fast2sms-no-key', $phone, $otp);
        return false;
    }

    $message = rawurlencode("Your OTP for admission verification is {$otp}. Valid for 5 minutes.");
    $url = 'https://www.fast2sms.com/dev/bulkV2'
        . '?route=' . rawurlencode(FAST2SMS_ROUTE)
        . '&sender_id=' . rawurlencode(FAST2SMS_SENDER_ID)
        . '&message=' . $message
        . '&language=' . rawurlencode(FAST2SMS_LANGUAGE)
        . '&flash=0'
        . '&numbers=' . rawurlencode($phone);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'authorization: ' . FAST2SMS_API_KEY,
            'accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '' || $httpCode < 200 || $httpCode >= 300) {
        logOtpFallback('sms-fast2sms-failed', $phone, $otp);
        return false;
    }

    $json = json_decode((string) $response, true);
    return is_array($json) && (($json['return'] ?? false) === true || ($json['message'][0] ?? '') === 'SMS sent successfully.');
}

function sendOtpViaTwilio(string $phone, string $otp): bool
{
    if (TWILIO_ACCOUNT_SID === '' || TWILIO_AUTH_TOKEN === '' || TWILIO_FROM_NUMBER === '') {
        logOtpFallback('sms-twilio-not-configured', $phone, $otp);
        return false;
    }

    $to = $phone;
    if (!str_starts_with($to, '+')) {
        $to = '+91' . ltrim($to, '0');
    }

    $url = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode(TWILIO_ACCOUNT_SID) . '/Messages.json';
    $postFields = http_build_query([
        'From' => TWILIO_FROM_NUMBER,
        'To' => $to,
        'Body' => "Your OTP for admission verification is {$otp}. Valid for 5 minutes.",
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields,
        CURLOPT_USERPWD => TWILIO_ACCOUNT_SID . ':' . TWILIO_AUTH_TOKEN,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error !== '' || $httpCode < 200 || $httpCode >= 300) {
        logOtpFallback('sms-twilio-failed', $phone, $otp);
        return false;
    }

    $json = json_decode((string) $response, true);
    return is_array($json) && !empty($json['sid']);
}

function logOtpFallback(string $channel, string $target, string $otp): void
{
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $line = sprintf(
        "[%s] OTP fallback (%s) for %s: %s\n",
        date('Y-m-d H:i:s'),
        $channel,
        $target,
        $otp
    );
    @file_put_contents($logDir . '/otp.log', $line, FILE_APPEND);
}
