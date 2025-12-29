<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You're invited to vote</title>
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
            word-break: break-all;
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
        <h1>You're Invited to Vote</h1>

        <p class="poll-title"><?= htmlspecialchars($poll->title) ?></p>

        <?php if (!empty($poll->description)): ?>
        <p class="poll-description"><?= htmlspecialchars($poll->description) ?></p>
        <?php endif; ?>

        <?php if ($poll->votingMode === 'secret_ballot'): ?>
        <div class="secret-ballot-notice">
            <strong>Secret Ballot:</strong> Your vote will be completely anonymous and cannot be changed after submission.
        </div>
        <?php endif; ?>

        <p>You've been invited to participate in this poll. Click the button below to cast your vote:</p>

        <p style="text-align: center;">
            <a href="<?= htmlspecialchars($voteUrl) ?>" class="vote-button">Cast Your Vote</a>
        </p>

        <p class="vote-link">
            Or copy and paste this link into your browser:<br>
            <?= htmlspecialchars($voteUrl) ?>
        </p>

        <div class="footer">
            <p>This invitation link is unique to you. Please do not share it with others.</p>
            <p>Sent by <a href="<?= htmlspecialchars(url('')) ?>">Pref.Tools Vote</a></p>
        </div>
    </div>
</body>
</html>
