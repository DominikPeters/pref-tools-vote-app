<!DOCTYPE html>
<html lang="<?= e($emailLocale) ?>" dir="<?= e($emailDirection) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($emailT('email_you_re_invited_to_vote')) ?></title>
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
        .poll-description {
            color: #6b7280;
            margin-bottom: 24px;
        }
        .vote-button {
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
        .vote-button:hover {
            background-color: #1d4ed8;
        }
        .vote-link {
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
        .unsubscribe-link {
            color: #9ca3af;
            text-decoration: underline;
        }
        .unsubscribe-link:hover {
            color: #6b7280;
        }
        .secret-ballot-notice {
            background-color: #fef3c7;
            border: 1px solid #fbbf24;
            border-radius: 6px;
            padding: 12px 16px;
            margin-bottom: 24px;
            color: #92400e;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <?php if (!empty($inviterName)): ?>
        <h1><?= e($emailT('email_name_invited_you_to_vote', ['name' => $inviterName])) ?></h1>
        <?php else: ?>
        <h1><?= e($emailT('email_you_re_invited_to_vote_2')) ?></h1>
        <?php endif; ?>

        <p class="poll-title"><?= htmlspecialchars($poll->title) ?></p>

        <?php if (!empty($poll->description)): ?>
        <p class="poll-description"><?= htmlspecialchars($poll->description) ?></p>
        <?php endif; ?>

        <?php if ($poll->votingMode === 'secret_ballot'): ?>
        <div class="secret-ballot-notice">
            <strong><?= e($emailT('email_secret_ballot')) ?></strong> <?= e($emailT('email_your_vote_will_be_completely_anonymous_and_cannot_be_changed_after_submission')) ?>
        </div>
        <?php endif; ?>

        <p><?= e($emailT('email_you_ve_been_invited_to_participate_in_this_poll_click_the_button_below_to_cast_your_vote')) ?></p>

        <p style="text-align: center;">
            <a href="<?= htmlspecialchars($voteUrl) ?>" class="vote-button"><?= e($emailT('email_cast_your_vote')) ?></a>
        </p>

        <p class="vote-link">
            <?= e($emailT('email_or_copy_and_paste_this_link_into_your_browser')) ?><br>
            <?= htmlspecialchars($voteUrl) ?>
        </p>

        <div class="footer">
            <p><?= e($emailT('email_this_invitation_link_is_unique_to_you_please_do_not_share_it_with_others')) ?></p>
            <p><?= e($emailT('email_sent_by')) ?> <a href="<?= htmlspecialchars(url('')) ?>">Pref.Tools Vote</a></p>
            <?php if (!empty($unsubscribeUrl)): ?>
            <p style="margin-top: 16px;">
                <a href="<?= htmlspecialchars($unsubscribeUrl) ?>" class="unsubscribe-link">
                    <?= e($emailT('email_don_t_want_to_receive_future_invitations_unsubscribe')) ?>
                </a>
            </p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
