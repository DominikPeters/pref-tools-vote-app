<!DOCTYPE html>
<html lang="<?= e($emailLocale) ?>" dir="<?= e($emailDirection) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($emailT('email_new_response_to_your_poll')) ?></title>
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
        .poll-title {
            font-size: 20px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 16px;
        }
        .response-info {
            background-color: #f3f4f6;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .response-info-item {
            margin-bottom: 8px;
        }
        .response-info-item:last-child {
            margin-bottom: 0;
        }
        .response-info-label {
            font-weight: 600;
            color: #6b7280;
        }
        .view-button {
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
        .view-button:hover {
            background-color: #1d4ed8;
        }
        .view-link {
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
        .unsubscribe-note {
            color: #9ca3af;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <h1><?= e($emailT('email_new_response_received')) ?></h1>

        <p class="poll-title"><?= htmlspecialchars($poll->title) ?></p>

        <div class="response-info">
            <?php if (!empty($voterName)): ?>
            <div class="response-info-item">
                <span class="response-info-label"><?= e($emailT('email_from')) ?></span> <?= htmlspecialchars($voterName) ?>
            </div>
            <?php endif; ?>
            <div class="response-info-item">
                <span class="response-info-label"><?= e($emailT('email_response')) ?></span> <?= e($emailT('email_number', ['number' => $responseNumber])) ?>
            </div>
            <div class="response-info-item">
                <span class="response-info-label"><?= e($emailT('email_submitted')) ?></span> <?= e($submittedAt) ?>
            </div>
        </div>

        <p><?= e($emailT('email_someone_has_submitted_a_response_to_your_poll_click_the_button_below_to_view_the_details')) ?></p>

        <p style="text-align: center;">
            <a href="<?= htmlspecialchars($viewUrl) ?>" class="view-button"><?= e($emailT('email_view_response')) ?></a>
        </p>

        <p class="view-link">
            <?= e($emailT('email_or_copy_and_paste_this_link_into_your_browser')) ?><br>
            <?= htmlspecialchars($viewUrl) ?>
        </p>

        <div class="footer">
            <p><?= e($emailT('email_sent_by')) ?> <a href="<?= htmlspecialchars(url('')) ?>">Pref.Tools Vote</a></p>
            <p class="unsubscribe-note">
            <?= e($emailT('email_you_re_receiving_this_email_because_you_enabled_response_notifications_for_this_poll_to_stop_receivi')) ?>
        </p>
        </div>
    </div>
</body>
</html>
