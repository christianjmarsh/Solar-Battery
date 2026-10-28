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

$mailBody  = "--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n{$textBody}\r\n\r\n";
$mailBody .= "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$htmlBody}\r\n\r\n";
$mailBody .= "--{$boundary}--";

$sent = mail(TO_EMAIL, $subject, $mailBody, $headers);

http_response_code($sent ? 200 : 500);
echo json_encode(['success' => $sent]);
