if ($email) {
    $downloadUrl = rtrim(SITE_URL, '/') . base_url('download.php') . '?t=' . urlencode($token);
    $successUrl  = rtrim(SITE_URL, '/') . base_url('success.php') . '?sid=' . urlencode($sessionId);

    $isTest = ($livemode === 0);

    $subject = $isTest
        ? "Your Backing Track Blueprint is here (TEST)"
        : "Your Backing Track Blueprint is here";

    $body =
        "Hey there,\n\n" .
        ($isTest ? "[TEST MODE]\n\n" : "") .
        "Thanks for grabbing Backing Track Blueprint.\n\n" .
        "Inside, you'll get the exact ideas, shortcuts, and practical moves I use to make my tracks hit harder, feel bigger, and support a stronger live show.\n\n" .
        "Use what fits, skip what doesn't, and start tightening up your sound right away.\n\n" .
        "Download your guide: " . $downloadUrl . "\n" .
        "View your success page: " . $successUrl . "\n\n" .
        "A couple notes:\n" .
        "- Your download link expires and has limited uses.\n" .
        "- If anything gives you trouble, just reply to this email.\n\n" .
        "Thanks again,\n" .
        "Nick Sanzeri\n";

    $htmlBody = '
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Your Backing Track Blueprint is here</title>
</head>
<body style="margin:0; padding:0; background:#f6f3ea; font-family:Arial, Helvetica, sans-serif; color:#1f1a12;">
  <div style="max-width:640px; margin:0 auto; padding:32px 20px;">
    <div style="background:#ffffff; border:1px solid #e7dcc5; border-radius:14px; padding:32px;">
      <h1 style="margin:0 0 18px; font-size:28px; line-height:1.2;">Your Backing Track Blueprint is here</h1>
      
      ' . ($isTest ? '<p style="margin:0 0 18px; color:#9a6a00;"><strong>TEST MODE</strong></p>' : '') . '

      <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">
        Hey there,
      </p>

      <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">
        Thanks for grabbing <strong>Backing Track Blueprint</strong>.
      </p>

      <p style="margin:0 0 16px; font-size:16px; line-height:1.6;">
        Inside, you\'ll get the exact ideas, shortcuts, and practical moves I use to make my tracks hit harder, feel bigger, and support a stronger live show.
      </p>

      <p style="margin:0 0 24px; font-size:16px; line-height:1.6;">
        Use what fits, skip what doesn\'t, and start tightening up your sound right away.
      </p>

      <p style="margin:0 0 14px;">
        <a href="' . htmlspecialchars($downloadUrl, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:#b68a2f; color:#ffffff; text-decoration:none; padding:14px 22px; border-radius:8px; font-weight:bold;">
          Download your guide
        </a>
      </p>

      <p style="margin:0 0 24px;">
        <a href="' . htmlspecialchars($successUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#7a5a16; text-decoration:underline;">
          View your success page
        </a>
      </p>

      <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#5f5648;">
        <strong>A couple notes:</strong>
      </p>
      <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#5f5648;">
        • Your download link expires and has limited uses.<br>
        • If anything gives you trouble, just reply to this email.
      </p>

      <p style="margin:24px 0 0; font-size:16px; line-height:1.6;">
        Thanks again,<br>
        Nick Sanzeri
      </p>
    </div>
  </div>
</body>
</html>';

    $idemKey = "dl_link:" . ($livemode ? "live" : "test") . ":" . $sessionId . ":" . $productKey;

    try {
        send_and_log_email($pdo, [
            'message_type'    => 'download_link',
            'recipient'       => $email,
            'subject'         => $subject,
            'body'            => $body,
            'html_body'       => $htmlBody,
            'from_email'      => 'no-reply@nicksanzeri.com',
            'from_name'       => 'Nick Sanzeri',
            'reply_to'        => 'nsanzeri@gmail.com',
            'idempotency_key' => $idemKey,
            'related_table'   => 'purchases',
            'related_id'      => null,
        ]);
    } catch (Throwable $e) {
        error_log("Webhook email send failed: " . $e->getMessage());
    }
}