<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poll Report</title>
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
            color: #dc2626;
            font-size: 24px;
            margin-top: 0;
            margin-bottom: 20px;
        }
        .info-section {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .info-section h2 {
            font-size: 14px;
            color: #6b7280;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-section p {
            margin: 0;
            font-size: 16px;
            color: #1f2937;
        }
        .reason-badge {
            display: inline-block;
            background-color: #fef2f2;
            color: #dc2626;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #fecaca;
        }
        .note-section {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-radius: 6px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .note-section h2 {
            font-size: 14px;
            color: #92400e;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .note-section p {
            margin: 0;
            font-size: 15px;
            color: #78350f;
            white-space: pre-wrap;
        }
        .view-button {
            display: inline-block;
            background-color: #2563eb;
            color: #ffffff !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            margin: 16px 0;
        }
        .metadata {
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 13px;
            color: #6b7280;
        }
        .metadata-item {
            margin-bottom: 8px;
        }
        .metadata-label {
            font-weight: 600;
            color: #374151;
        }
        .footer {
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            color: #9ca3af;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <h1>Poll Reported</h1>

        <p>A user has reported a poll for potentially violating community guidelines.</p>

        <div class="info-section">
            <h2>Poll Title</h2>
            <p><?= htmlspecialchars($poll->title) ?></p>
        </div>

        <div class="info-section">
            <h2>Reason</h2>
            <p><span class="reason-badge"><?= htmlspecialchars($reasonLabel) ?></span></p>
        </div>

        <?php if (!empty($note)): ?>
        <div class="note-section">
            <h2>Additional Details</h2>
            <p><?= htmlspecialchars($note) ?></p>
        </div>
        <?php endif; ?>

        <p style="text-align: center;">
            <a href="<?= htmlspecialchars($pollUrl) ?>" class="view-button">View Poll</a>
        </p>

        <div class="metadata">
            <div class="metadata-item">
                <span class="metadata-label">Poll ID:</span> <?= htmlspecialchars($poll->publicId) ?>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Reporter IP:</span> <?= htmlspecialchars($reporterIp) ?>
            </div>
            <div class="metadata-item">
                <span class="metadata-label">Reported at:</span> <?= htmlspecialchars($timestamp) ?>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated notification from Pref.Tools Vote.</p>
        </div>
    </div>
</body>
</html>
