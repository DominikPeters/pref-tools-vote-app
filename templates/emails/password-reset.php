<!DOCTYPE html>
<html lang="<?= e($emailLocale) ?>" dir="<?= e($emailDirection) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($emailT('email_reset_your_password')) ?></title>
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
        .reset-button {
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
        .reset-button:hover {
            background-color: #1d4ed8;
        }
        .reset-link {
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
        .security-notice {
            background-color: #fee2e2;
            border: 1px solid #f87171;
            border-radius: 6px;
            padding: 12px 16px;
            margin-top: 16px;
            color: #991b1b;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <h1><?= e($emailT('email_reset_your_password_2')) ?></h1>

        <p><?= e($emailT('email_hi_name', ['name' => $userName])) ?></p>

        <p><?= e($emailT('email_we_received_a_request_to_reset_your_password_click_the_button_below_to_choose_a_new_password')) ?></p>

        <p style="text-align: center;">
            <a href="<?= htmlspecialchars($resetUrl) ?>" class="reset-button"><?= e($emailT('email_reset_password')) ?></a>
        </p>

        <p class="reset-link">
            <?= e($emailT('email_or_copy_and_paste_this_link_into_your_browser')) ?><br>
            <?= htmlspecialchars($resetUrl) ?>
        </p>

        <div class="expiry-notice">
            <?= e($emailT('email_this_password_reset_link_will_expire_in_1_hour')) ?>
        </div>

        <div class="security-notice">
            <?= e($emailT('email_if_you_did_not_request_a_password_reset_please_ignore_this_email_your_password_will_remain_unchanged')) ?>
        </div>

        <div class="footer">
            <p><?= e($emailT('email_sent_by')) ?> <a href="<?= htmlspecialchars(url('')) ?>">Pref.Tools Vote</a></p>
        </div>
    </div>
</body>
</html>
