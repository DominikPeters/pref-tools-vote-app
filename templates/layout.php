<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Pref.Tools Vote') ?></title>
    <link rel="stylesheet" href="<?= asset('assets/css/main.css') ?>">
    <?php if (!empty($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link rel="stylesheet" href="<?= asset(ltrim($css, '/')) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <script>window.BASE_PATH = '<?= basePath() ?>';</script>
    <header class="site-header">
        <div class="container">
            <a href="<?= basePath() ?>/" class="logo">Pref.Tools Vote</a>
            <nav class="main-nav">
                <a href="<?= basePath() ?>/create" class="btn btn-primary">Create Vote</a>
                <?php if (!empty($user)): ?>
                    <a href="<?= basePath() ?>/dashboard">Dashboard</a>
                    <span class="user-email"><?= e($user->email) ?></span>
                <?php else: ?>
                    <a href="<?= basePath() ?>/login">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <?= $content ?? '' ?>
    </main>

    <footer class="site-footer">
        <div class="container">
            <p>&copy; <?= date('Y') ?> Pref.Tools Vote. A social choice voting application.</p>
        </div>
    </footer>

    <script src="<?= asset('assets/js/app.js') ?>" type="module"></script>
    <?php if (!empty($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= asset(ltrim($js, '/')) ?>" type="module"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
