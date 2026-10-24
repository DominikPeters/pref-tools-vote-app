<!DOCTYPE html>
<html lang="<?= e($emailLocale) ?>" dir="<?= e($emailDirection) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($emailT('email_verify_your_email_address')) ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .email-container {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #2563eb;
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .verify-button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 14px 28px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .verify-button:hover {
            background-color: #1d4ed8;
        }
        .verify-link {
            word-wrap: break-word;
            overflow-wrap: anywhere;
            color: #6b7280;
            font-size: 14px;
            margin-top: 16px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 14px;
        }
        .expiry-notice {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 12px 16px;
            margin-top: 24px;
            color: #92400e;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <h1><?= e($emailT('email_verify_your_email_address_2')) ?></h1>

        <p><?= e($emailT('email_hi_name', ['name' => $userName])) ?></p>

        <p><?= e($emailT('email_thank_you_for_registering_please_verify_your_email_address_by_clicking_the_button_below')) ?></p>

        <p style="text-align: center;">
            <a href="<?= htmlspecialchars($verifyUrl) ?>" class="verify-button"><?= e($emailT('email_verify_email_address')) ?></a>
        </p>

        <p class="verify-link">
            <?= e($emailT('email_or_copy_and_paste_this_link_into_your_browser')) ?><br>
            <?= htmlspecialchars($verifyUrl) ?>
        </p>

        <div class="expiry-notice">
            <?= e($emailT('email_this_verification_link_will_expire_in_24_hours')) ?>
        </div>

        <div class="footer">
            <p><?= e($emailT('email_if_you_did_not_create_an_account_you_can_safely_ignore_this_email')) ?></p>
            <p><?= e($emailT('email_sent_by')) ?> <a href="<?= htmlspecialchars(url('')) ?>">Pref.Tools Vote</a></p>
        </div>
    </div>
</body>
</html>
