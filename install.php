<?php
/**
 * Pref.Tools Vote - Installation Script
 *
 * This script handles first-run setup:
 * 1. Creates configuration file
 * 2. Initializes the database
 * 3. Optionally creates an admin user
 */

// Base path (project root)
$basePath = __DIR__;

// Detect environment (for test isolation)
$appEnv = getenv('APP_ENV') ?: 'production';
$isTestEnv = $appEnv === 'test';

// Detect URL base path from current request (for subfolder deployment)
$urlBasePath = '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
if (preg_match('#^(.+)/install\.php$#', $scriptName, $matches)) {
    $urlBasePath = $matches[1];
}

// Auto-detect the full app URL for the form default
$detectedProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$detectedHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$detectedAppUrl = $detectedProtocol . '://' . $detectedHost . $urlBasePath;

// Check if config exists (use test config in test environment)
$configPath = $isTestEnv
    ? $basePath . '/config/config.test.php'
    : $basePath . '/config/config.php';
$configExists = file_exists($configPath);

// Handle post-database-setup steps
if ($configExists) {
    require_once $basePath . '/src/bootstrap.php';

    $step = $_POST['step'] ?? ($_GET['step'] ?? 'welcome');

    // Always allow the complete step (it's just a success message)
    if ($step === 'complete') {
        // Allow showing complete page
    }
    // Allow admin step only if no users exist yet
    elseif ($step === 'admin') {
        $userCount = \App\Database::getInstance()->fetchColumn("SELECT COUNT(*) FROM users");
        if ($userCount > 0) {
            // Already have users, redirect to home
            header('Location: ' . $urlBasePath . '/');
            exit;
        }
        // Allow showing admin page
    }
    // For welcome/database steps, check if fully installed
    else {
        $userCount = \App\Database::getInstance()->fetchColumn("SELECT COUNT(*) FROM users");
        if ($userCount > 0) {
            // Fully installed, redirect to home
            header('Location: ' . $urlBasePath . '/');
            exit;
        }
        // Config exists but no users - redirect to admin step
        header('Location: ' . $urlBasePath . '/install.php?step=admin');
        exit;
    }
}

$step = $_POST['step'] ?? ($_GET['step'] ?? 'welcome');
$error = null;
$success = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 'database':
            $result = handleDatabaseSetup();
            if ($result === true) {
                header('Location: install.php?step=admin');
                exit;
            }
            $error = $result;
            $step = 'database';
            break;

        case 'admin':
            $result = handleAdminSetup();
            if ($result === true) {
                header('Location: install.php?step=complete');
                exit;
            }
            $error = $result;
            $step = 'admin';
            break;
    }
}

function handleDatabaseSetup() {
    global $configPath, $basePath, $isTestEnv;

    $driver = $_POST['driver'] ?? 'sqlite';
    $appUrl = rtrim($_POST['app_url'] ?? 'http://localhost', '/');

    // Use separate database file for test environment
    $sqliteFile = $isTestEnv ? 'poll.test.db' : 'poll.db';

    $config = [
        'database' => [
            'driver' => $driver,
            'sqlite_path' => $basePath . '/data/' . $sqliteFile,
            'mysql_host' => $_POST['mysql_host'] ?? 'localhost',
            'mysql_port' => (int)($_POST['mysql_port'] ?? 3306),
            'mysql_database' => $_POST['mysql_database'] ?? 'poll_app',
            'mysql_username' => $_POST['mysql_username'] ?? 'root',
            'mysql_password' => $_POST['mysql_password'] ?? '',
            'mysql_charset' => 'utf8mb4',
        ],
        'app' => [
            'name' => 'Pref.Tools Vote',
            'url' => $appUrl,
            'debug' => true,
            'timezone' => 'UTC',
        ],
        'session' => [
            'name' => 'poll_session',
            'lifetime' => 7200,
        ],
        'security' => [
            'public_id_length' => 8,
            'admin_token_length' => 32,
            'voter_token_length' => 32,
            'access_token_length' => 16,
        ],
        'mail' => [
            'enabled' => false,
            'from_address' => 'noreply@example.com',
            'from_name' => 'Pref.Tools Vote',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
        ],
    ];

    // Test database connection
    try {
        if ($driver === 'sqlite') {
            $dataDir = $basePath . '/data';
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            $pdo = new PDO('sqlite:' . $config['database']['sqlite_path']);
        } else {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=utf8mb4',
                $config['database']['mysql_host'],
                $config['database']['mysql_port']
            );
            $pdo = new PDO(
                $dsn,
                $config['database']['mysql_username'],
                $config['database']['mysql_password']
            );

            // Create database if it doesn't exist
            $dbName = $config['database']['mysql_database'];
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}`");
            $pdo->exec("USE `{$dbName}`");
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Run migrations
        $migrationSql = file_get_contents($basePath . '/migrations/001_initial_schema.sql');

        if ($driver === 'sqlite') {
            // Adjust SQL for SQLite
            $migrationSql = preg_replace('/\bAUTO_INCREMENT\b/i', '', $migrationSql);
            $migrationSql = preg_replace('/\bINT\s+PRIMARY\s+KEY/i', 'INTEGER PRIMARY KEY', $migrationSql);
        }

        // Execute statements
        $statements = array_filter(
            array_map('trim', explode(';', $migrationSql)),
            fn($s) => !empty($s)
        );

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        // Write config file
        $configContent = "<?php\n\nreturn " . var_export($config, true) . ";\n";
        if (!file_put_contents($configPath, $configContent)) {
            return 'Failed to write configuration file. Check directory permissions.';
        }

        return true;
    } catch (PDOException $e) {
        return 'Database connection failed: ' . $e->getMessage();
    } catch (Exception $e) {
        return 'Installation failed: ' . $e->getMessage();
    }
}

function handleAdminSetup() {
    global $basePath, $configExists;

    // Load bootstrap if not already loaded
    if (!$configExists) {
        require_once $basePath . '/src/bootstrap.php';
    }

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($name)) {
        return 'Name is required.';
    }

    if (empty($email)) {
        return 'Email is required.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    try {
        $auth = \App\Auth::getInstance();
        $user = $auth->register($email, $password, $name, \App\Models\User::ROLE_SYSADMIN);

        \App\Services\LogService::getInstance()->log('user.registered', null, $user->id, null, [
            'via' => 'installer',
            'role' => 'sysadmin',
        ]);

        return true;
    } catch (Exception $e) {
        return 'Failed to create sysadmin account: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install - Pref.Tools Vote</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 2rem;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .installer {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            max-width: 500px;
            width: 100%;
        }

        h1 {
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }

        .step-indicator {
            color: #64748b;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 500;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        select {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .hint {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .btn {
            display: inline-block;
            padding: 0.625rem 1.25rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #16a34a;
            padding: 0.75rem 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .mysql-fields {
            display: none;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 6px;
            margin-top: 0.5rem;
        }

        .mysql-fields.show {
            display: block;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }

        .radio-group {
            display: flex;
            gap: 1rem;
        }

        .radio-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .radio-label input {
            width: auto;
        }

        .feature-list {
            list-style: none;
            padding: 0;
            margin: 1rem 0;
        }

        .feature-list li {
            padding: 0.25rem 0;
            padding-left: 1.5rem;
            position: relative;
        }

        .feature-list li::before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #22c55e;
        }
    </style>
</head>
<body>
    <div class="installer">
        <?php if ($step === 'welcome'): ?>
            <h1>Welcome to Pref.Tools Vote</h1>
            <p>Let's set up your voting application. This will only take a minute.</p>

            <ul class="feature-list">
                <li>Create and share polls with multiple question types</li>
                <li>Rankings, approval voting, star ratings, and more</li>
                <li>No account required to create polls</li>
                <li>Built-in social choice algorithms</li>
            </ul>

            <div class="actions">
                <a href="install.php?step=database" class="btn">Start Installation</a>
            </div>

        <?php elseif ($step === 'database'): ?>
            <h1>Database Setup</h1>
            <p class="step-indicator">Step 1 of 2</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="step" value="database">

                <div class="form-group">
                    <label>Application URL</label>
                    <input type="text" name="app_url" value="<?= htmlspecialchars($_POST['app_url'] ?? $detectedAppUrl) ?>" required>
                    <p class="hint">The URL where your app will be accessible (including subfolder if any)</p>
                </div>

                <div class="form-group">
                    <label>Database Type</label>
                    <div class="radio-group">
                        <label class="radio-label">
                            <input type="radio" name="driver" value="sqlite" checked onchange="toggleMysql()">
                            <span>SQLite (Recommended)</span>
                        </label>
                        <label class="radio-label">
                            <input type="radio" name="driver" value="mysql" onchange="toggleMysql()">
                            <span>MySQL</span>
                        </label>
                    </div>
                </div>

                <div class="mysql-fields" id="mysqlFields">
                    <div class="form-group">
                        <label>MySQL Host</label>
                        <input type="text" name="mysql_host" value="localhost">
                    </div>
                    <div class="form-group">
                        <label>MySQL Port</label>
                        <input type="number" name="mysql_port" value="3306">
                    </div>
                    <div class="form-group">
                        <label>Database Name</label>
                        <input type="text" name="mysql_database" value="poll_app">
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="mysql_username" value="root">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="mysql_password">
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Continue</button>
                </div>
            </form>

            <script>
                function toggleMysql() {
                    const isMySQL = document.querySelector('input[name="driver"]:checked').value === 'mysql';
                    document.getElementById('mysqlFields').classList.toggle('show', isMySQL);
                }
            </script>

        <?php elseif ($step === 'admin'): ?>
            <h1>Create Sysadmin Account</h1>
            <p class="step-indicator">Step 2 of 2</p>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="step" value="admin">

                <p>Create a sysadmin account to manage the entire application, including users, all polls, and view system logs.</p>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                    <p class="hint">At least 8 characters.</p>
                </div>

                <div class="actions">
                    <button type="submit" class="btn">Complete Installation</button>
                </div>
            </form>

        <?php elseif ($step === 'complete'): ?>
            <h1>Installation Complete!</h1>

            <div class="success">
                Your voting application is ready to use.
            </div>

            <p>You can now:</p>
            <ul class="feature-list">
                <li>Create your first poll</li>
                <li>Share voting links with participants</li>
                <li>View and export results</li>
            </ul>

            <div class="actions">
                <a href="<?= htmlspecialchars($urlBasePath) ?>/" class="btn">Go to App</a>
                <a href="<?= htmlspecialchars($urlBasePath) ?>/create" class="btn btn-secondary">Create Poll</a>
            </div>

        <?php endif; ?>
    </div>
</body>
</html>
