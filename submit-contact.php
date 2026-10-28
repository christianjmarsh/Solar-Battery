<?php
// ══════════════════════════════════════════════════════════════════
//  Brisbane Solar Battery Systems — Contact Form Mailer
//  Sends contact form submissions to sbsbrisbane@gmail.com
//
//  FROM_EMAIL must be a real mailbox on the Hostinger email plan
//  (hPanel → Email → Email Accounts), or messages may go to spam.
// ══════════════════════════════════════════════════════════════════

define('TO_EMAIL',   'sbsbrisbane@gmail.com');
define('FROM_EMAIL', 'noreply@brisbanesolarbattery.com');
define('FROM_NAME',  'Brisbane Solar Battery Systems Website');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// SMTP login details live outside public_html so they are never served or committed:
// <domain folder>/contact-mail-config.php returning
// ['host' => 'smtp.hostinger.com', 'port' => 465, 'user' => FROM_EMAIL, 'pass' => '...'].
// Sending through the mailbox gets the message DKIM-signed for our domain, so DMARC passes.
// Without the file, fall back to PHP mail().
$configFile = dirname($_SERVER['DOCUMENT_ROOT']) . '/contact-mail-config.php';
$smtpError = null;
try {
    $smtp = is_file($configFile) ? include $configFile : null;
    if ($smtp !== null && !is_array($smtp)) { $smtpError = 'config file does not return an array'; $smtp = null; }
} catch (\Throwable $e) {
    $smtpError = 'config file error on line ' . $e->getLine() . ': ' . $e->getMessage();
    $smtp = null;
}

// Returns null on success, otherwise the step that failed. With $to null, stops after logging in.
function smtp_send(array $cfg, ?string $to, string $subject = '', string $headers = '', string $body = ''): ?string {
    $prefix = ($cfg['secure'] ?? 'ssl') === 'ssl' ? 'ssl://' : 'tcp://';
    $fp = @stream_socket_client($prefix . $cfg['host'] . ':' . $cfg['port'], $errno, $errstr, 15);
    if (!$fp) { error_log("contact smtp connect failed: $errstr"); return "connect: $errstr"; }
    stream_set_timeout($fp, 15);
    $failure = null;
    $expect = function (string $code) use ($fp, &$failure): bool {
        $reply = '';
        while (($line = fgets($fp, 515)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        if (strncmp($reply, $code, 3) !== 0) { $failure = 'server replied: ' . trim($reply); error_log('contact smtp ' . $failure); return false; }
        return true;
    };
    $cmd = function (string $line, string $code) use ($fp, $expect): bool {
        fwrite($fp, $line . "\r\n");
        return $expect($code);
    };
    $message = 'Date: ' . date('r') . "\r\n"
             . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr(FROM_EMAIL, '@'), 1) . ">\r\n"
             . 'To: <' . $to . ">\r\n"
             . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
             . $headers . "\r\n\r\n" . $body;
    $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $message));
    $ok = $expect('220')
       && $cmd('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), '250')
       && $cmd('AUTH LOGIN', '334')
       && $cmd(base64_encode($cfg['user']), '334')
       && $cmd(base64_encode($cfg['pass']), '235')
       && ($to === null || $cmd('MAIL FROM:<' . FROM_EMAIL . '>', '250')
       && $cmd('RCPT TO:<' . $to . '>', '250')
       && $cmd('DATA', '354')
       && $cmd($message . "\r\n.", '250'));
    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok ? null : ($failure ?? 'connection closed');
}


if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error'=>'Method not allowed']); exit; }

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

$name     = trim($data['name']     ?? '');
$email    = trim($data['email']    ?? '');
$phone    = trim($data['phone']    ?? '');
$suburb   = trim($data['suburb']   ?? '');
$existing = trim($data['existing'] ?? '');
$message  = trim($data['message']  ?? '');

if (!$name || !$email || !$message) { http_response_code(400); echo json_encode(['error'=>'Missing required fields']); exit; }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo json_encode(['error'=>'Invalid email']); exit; }

function clean(string $s): string { return strip_tags(str_replace(["\r","\n"],' ',$s)); }
$name    = clean($name);
$email   = clean($email);
$phone   = clean($phone)  ?: 'Not provided';
$suburb  = clean($suburb) ?: 'Not provided';
$message = strip_tags($message);

$isExisting  = ($existing === 'yes');
$customerTag = $isExisting ? '🔁 EXISTING CUSTOMER' : '🆕 NEW ENQUIRY';
$bgColor     = $isExisting ? '#fff8e6' : '#e8f5e9';
$borderColor = $isExisting ? '#F5A623' : '#3EA833';
$textColor   = $isExisting ? '#b8690a' : '#2a7a1f';
$msgSafe     = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
$subject     = ($isExisting ? '[Existing Customer]' : '[New Enquiry]') . " Contact from $name";

$textBody = "Brisbane Solar Battery Systems — Contact Form\n\n$customerTag\n\nName: $name\nEmail: $email\nPhone: $phone\nSuburb: $suburb\n\nMessage:\n$message";

$htmlBody = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;background:#f4f4f4;padding:20px;margin:0">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1)">
  <div style="background:#1B4F87;padding:24px 28px">
    <h1 style="color:#fff;margin:0;font-size:20px">Brisbane Solar Battery Systems</h1>
    <p style="color:rgba(255,255,255,0.7);margin:4px 0 0;font-size:14px">New Contact Form Submission</p>
  </div>
  <div style="padding:28px">
    <div style="background:{$bgColor};border-left:4px solid {$borderColor};padding:10px 16px;border-radius:4px;margin-bottom:24px">
      <strong style="color:{$textColor}">{$customerTag}</strong>
    </div>
    <h2 style="color:#1B4F87;font-size:16px;margin:0 0 16px">Contact Details</h2>
    <table style="width:100%;border-collapse:collapse;margin-bottom:24px">
      <tr><td style="padding:8px 12px;background:#f9f9f9;width:100px;color:#666;font-size:14px">Name</td><td style="padding:8px 12px;font-size:14px">{$name}</td></tr>
      <tr><td style="padding:8px 12px;background:#f0f0f0;color:#666;font-size:14px">Email</td><td style="padding:8px 12px;font-size:14px"><a href="mailto:{$email}" style="color:#1B4F87">{$email}</a></td></tr>
      <tr><td style="padding:8px 12px;background:#f9f9f9;color:#666;font-size:14px">Phone</td><td style="padding:8px 12px;font-size:14px">{$phone}</td></tr>
      <tr><td style="padding:8px 12px;background:#f0f0f0;color:#666;font-size:14px">Suburb</td><td style="padding:8px 12px;font-size:14px">{$suburb}</td></tr>
    </table>
    <h2 style="color:#1B4F87;font-size:16px;margin:0 0 12px">Message</h2>
    <div style="background:#f9f9f9;border:1px solid #e0e0e0;border-radius:6px;padding:16px;font-size:14px;line-height:1.7;color:#333">{$msgSafe}</div>
  </div>
  <div style="background:#f4f4f4;padding:16px 28px;text-align:center">
    <p style="color:#999;font-size:12px;margin:0">Submitted via Brisbane Solar Battery Systems website</p>
  </div>
</div>
</body></html>
HTML;

$boundary = md5(uniqid(rand(), true));
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
$headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Base64 parts keep lines under SMTP's 998-character limit and carry emoji safely.
$mailBody  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($textBody)) . "\r\n";
$mailBody .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($htmlBody)) . "\r\n";
$mailBody .= "--{$boundary}--";

$sent = $smtp
    ? smtp_send($smtp, TO_EMAIL, $subject, $headers, $mailBody) === null
    : mail(TO_EMAIL, $subject, $mailBody, $headers);

http_response_code($sent ? 200 : 500);
echo json_encode(['success' => $sent]);
