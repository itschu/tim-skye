<?php
require_once __DIR__ . '/../includes/bootstrap.php';
// install.php - Multi-step installation wizard
// Follows the requested installation plan closely.
session_start();

// Basic helpers
function h($s)
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function json_resp($arr)
{
    header('Content-Type: application/json');
    echo json_encode($arr);
    exit;
}

$base_dir = ROOT;
$env_path = $base_dir . '/.env';
$install_log = $base_dir . '/logs/install.log';
$install_err_log = $base_dir . '/logs/install-errors.log';

// Prevent re-installation if .env exists
if (file_exists($env_path) && !isset($_GET['force'])) {
    echo "<h2>Installation already completed</h2><p>.env file already exists. To reinstall, remove .env and reload this page.</p>";
    exit;
}

// CSRF token
if (empty($_SESSION['install_csrf'])) {
    $_SESSION['install_csrf'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['install_csrf'];

// Handle AJAX actions: test DB and test email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_action'])) {
    if (!hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        json_resp(['ok' => false, 'error' => 'Invalid CSRF token']);
    }

    if ($_POST['ajax_action'] === 'test_db') {
        $host = trim($_POST['db_host'] ?? 'localhost');
        $name = trim($_POST['db_name'] ?? 'tradeonix_db');
        $user = trim($_POST['db_user'] ?? 'root');
        $pass = $_POST['db_pass'] ?? '';
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $ver = $pdo->query('select version()')->fetchColumn();
            $_SESSION['install_db'] = ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass];
            json_resp(['ok' => true, 'msg' => 'Connection successful', 'version' => $ver]);
        } catch (PDOException $e) {
            json_resp(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    if ($_POST['ajax_action'] === 'test_email') {
        $smtp = [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => intval($_POST['smtp_port'] ?? 587),
            'user' => trim($_POST['smtp_user'] ?? ''),
            'pass' => $_POST['smtp_pass'] ?? '',
            'from' => trim($_POST['smtp_from'] ?? ''),
            'from_name' => trim($_POST['smtp_from_name'] ?? 'Investment Platform'),
            'to' => trim($_POST['test_to'] ?? ''),
        ];

        $_SESSION['install_smtp'] = $smtp;

        // Try PHPMailer if available
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $smtp['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtp['user'];
                $mail->Password = $smtp['pass'];
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = $smtp['port'];
                $mail->setFrom($smtp['from'], $smtp['from_name']);
                $mail->addAddress($smtp['to']);
                $mail->isHTML(true);
                $mail->Subject = 'Test Email from Installer';
                $mail->Body = 'This is a test email from the installation wizard.';
                $mail->send();
                json_resp(['ok' => true, 'msg' => 'Test email sent successfully (PHPMailer)']);
            } catch (Exception $e) {
                json_resp(['ok' => false, 'error' => 'PHPMailer: ' . $e->getMessage()]);
            }
        }

        // Fallback to mail()
        $to = $smtp['to'];
        $subject = 'Test Email from Installer';
        $body = "This is a test email from the installation wizard.";
        $headers = "From: {$smtp['from']}\r\n" . "Reply-To: {$smtp['from']}\r\n";
        $sent = @mail($to, $subject, $body, $headers);
        if ($sent) json_resp(['ok' => true, 'msg' => 'Test email sent (mail)']);
        json_resp(['ok' => false, 'error' => 'Failed to send test email; PHPMailer not available and mail() failed.']);
    }

    json_resp(['ok' => false, 'error' => 'Unknown ajax action']);
}

// Handle final installation execution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['do_install'])) {
    if (!hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $errors = [];
        $logs = [];

        // Retrieve stored session values
        $db = $_SESSION['install_db'] ?? null;
        $smtp = $_SESSION['install_smtp'] ?? null;
        $site_url = $_SESSION['install_site_url'] ?? null;
        $admin = $_SESSION['install_admin'] ?? null;

        if (!$db || !$admin) {
            $errors[] = 'Missing required session data (db or admin). Please re-run previous steps.';
        }

        if (empty($errors)) {
            // 1. Create DB connection. Try connecting to the selected database; if it doesn't exist,
            // connect without a database, create it, then reconnect so subsequent schema executes
            // against the user-provided database name.
            $dsn_with_db = "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4";
            $dsn_no_db = "mysql:host={$db['host']};charset=utf8mb4";
            try {
                // First try connecting directly to the database (may succeed if DB exists)
                try {
                    $pdo = new PDO($dsn_with_db, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $logs[] = 'Connected to database ' . $db['name'] . '.';
                } catch (PDOException $inner) {
                    // If database does not exist, connect without DB and create it
                    $pdo = new PDO($dsn_no_db, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $logs[] = 'Connected to MySQL server (no database selected).';
                    // Create the database if it doesn't exist
                    $create_sql = "CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $db['name']) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
                    $pdo->exec($create_sql);
                    $logs[] = 'Created database (if not existed): ' . $db['name'];
                    // Reconnect to the newly created database
                    $pdo = new PDO($dsn_with_db, $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                    $logs[] = 'Reconnected to database ' . $db['name'] . '.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Database connection failed: ' . $e->getMessage();
            }
        }

        // 2. Execute schema
        if (empty($errors)) {
            $sql_file = $base_dir . '/database/schema.sql';
            if (!file_exists($sql_file)) {
                $errors[] = 'Schema file not found: ' . $sql_file;
            } else {
                $sql_content = file_get_contents($sql_file);
                // Remove /* */ comments
                $sql_content = preg_replace('#/\*.*?\*/#s', '', $sql_content);
                // Remove -- comments
                $sql_content = preg_replace('/--.*?\n/', "\n", $sql_content);
                // Strip CREATE DATABASE and USE statements to avoid forcing a hardcoded DB name
                $sql_content = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql_content);
                $sql_content = preg_replace('/USE\s+[^;]*;/i', '', $sql_content);
                // Ensure statements are executed against the chosen database (PDO is connected to it)
                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                $failed = [];
                foreach ($statements as $idx => $stmt) {
                    if ($stmt === '') continue;
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        $failed[] = ['idx' => $idx, 'error' => $e->getMessage(), 'sql' => substr($stmt, 0, 200)];
                        file_put_contents($install_err_log, date('c') . " - SQL Error: " . $e->getMessage() . "\n", FILE_APPEND);
                    }
                }
                if (!empty($failed)) {
                    $errors[] = 'One or more SQL statements failed. Check logs.';
                } else {
                    $logs[] = 'Database schema executed.';
                }
            }
        }

        // 2b. Execute migration scripts - scan all .sql files in /database (exclude schema.sql)
        if (empty($errors)) {
            $db_dir = $base_dir . '/database';
            $all_sql = glob($db_dir . '/*.sql');
            // Filter out schema.sql (already executed) and ensure natural order
            $migration_paths = [];
            foreach ($all_sql as $f) {
                if (basename($f) === 'schema.sql') continue;
                $migration_paths[] = $f;
            }
            // Natural sort by filename for correct ordering
            natsort($migration_paths);
            // Disable foreign key checks during migrations to avoid order issues
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            } catch (Exception $e) {
                // not fatal; continue
            }

            foreach ($migration_paths as $migration_path) {
                $migration_file = basename($migration_path);
                if (!file_exists($migration_path)) continue;

                $sql_content = file_get_contents($migration_path);
                // Remove /* */ comments
                $sql_content = preg_replace('#/\*.*?\*/#s', '', $sql_content);
                // Remove -- comments
                $sql_content = preg_replace('/--.*?\n/', "\n", $sql_content);
                // Remove CREATE DATABASE / USE to avoid forcing DB names
                $sql_content = preg_replace('/CREATE\s+DATABASE[^;]*;/i', '', $sql_content);
                $sql_content = preg_replace('/USE\s+[^;]*;/i', '', $sql_content);

                $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                $migration_failed = false;
                foreach ($statements as $stmt) {
                    if ($stmt === '') continue;
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        // Ignore common idempotent errors (already exists / duplicate) to allow re-run
                        $error_msg = $e->getMessage();
                        $is_duplicate = stripos($error_msg, 'duplicate') !== false ||
                            stripos($error_msg, 'already exists') !== false ||
                            stripos($error_msg, 'duplicate entry') !== false ||
                            stripos($error_msg, 'exists') !== false;
                        if (!$is_duplicate) {
                            $migration_failed = true;
                            file_put_contents($install_err_log, date('c') . " - Migration Error ({$migration_file}): " . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    }
                }
                if (!$migration_failed) {
                    $logs[] = "Migration executed: {$migration_file}";
                }
            }

            // Re-enable foreign key checks
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (Exception $e) {
                // ignore
            }
        }

        // 3. Verify tables and settings
        if (empty($errors)) {
            try {
                $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                $logs[] = 'Tables present: ' . count($tables);
                $settings_count = 0;
                try {
                    $settings_count = (int)$pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
                } catch (Exception $e) {
                    // settings table may not exist; ignore
                }
            } catch (PDOException $e) {
                $errors[] = 'Could not verify tables: ' . $e->getMessage();
            }
        }

        // 4. Create .env file
        if (empty($errors)) {
            $cron_key = bin2hex(random_bytes(32));
            $env_lines = [];
            $env_lines[] = "# Database Configuration";
            $env_lines[] = "DB_HOST=\"" . ($db['host'] ?? 'localhost') . "\"";
            $env_lines[] = "DB_NAME=\"" . ($db['name'] ?? 'tradeonix_db') . "\"";
            $env_lines[] = "DB_USER=\"" . ($db['user'] ?? 'root') . "\"";
            $env_lines[] = "DB_PASS=\"" . ($db['pass'] ?? '') . "\"";
            $env_lines[] = '';
            $env_lines[] = "# SMTP Configuration";
            $env_lines[] = "SMTP_HOST=\"" . ($smtp['host'] ?? '') . "\"";
            $env_lines[] = "SMTP_PORT=\"" . ($smtp['port'] ?? 587) . "\"";
            $env_lines[] = "SMTP_USER=\"" . ($smtp['user'] ?? '') . "\"";
            $env_lines[] = "SMTP_PASS=\"" . ($smtp['pass'] ?? '') . "\"";
            $env_lines[] = "SMTP_FROM_EMAIL=\"" . ($smtp['from'] ?? '') . "\"";
            $env_lines[] = "SMTP_FROM_NAME=\"" . ($smtp['from_name'] ?? 'Investment Platform') . "\"";
            $env_lines[] = '';
            $env_lines[] = "# Cron Configuration";
            $env_lines[] = "CRON_API_KEY=\"{$cron_key}\"";
            $env_lines[] = '';
            $env_lines[] = "# Application Configuration";
            $env_lines[] = "APP_ENV=\"production\"";
            $env_lines[] = "SESSION_TIMEOUT=\"1800\"";
            $env_lines[] = '';
            $env_lines[] = "# Site URL Configuration";
            $env_lines[] = "SITE_URL=\"" . ($site_url ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')) . "\"";
            $env_lines[] = '';
            $env_lines[] = "# Log Rotation Configuration";
            $env_lines[] = "# Maximum log file size in bytes before it is cleared (default: 10485760 = 10MB)";
            $env_lines[] = "LOG_MAX_SIZE=\"10485760\"";
            $env_content = implode("\n", $env_lines) . "\n";

            $written = false;
            try {
                file_put_contents($env_path, $env_content);
                @chmod($env_path, 0600);
                $written = true;
                $logs[] = '.env created';
            } catch (Exception $e) {
                $errors[] = 'Failed to write .env: ' . $e->getMessage();
            }
        }

        // 5. Create admin user
        if (empty($errors)) {
            $now = date('Y-m-d H:i:s');
            $ref = strtoupper(substr(md5(uniqid($admin['email'], true)), 0, 8));
            $hash = password_hash($admin['password'], PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,status,referral_code,balance,language,kyc_status,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([
                    $admin['name'],
                    $admin['email'],
                    $hash,
                    'admin',
                    'active',
                    $ref,
                    '0.00',
                    'en_US',
                    'not_submitted',
                    $now,
                    $now
                ]);
                $admin_id = $pdo->lastInsertId();
                if ($admin_id) {
                    $logs[] = 'Admin user created (ID ' . $admin_id . ')';
                } else {
                    $errors[] = 'Failed to create admin user.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Admin insertion error: ' . $e->getMessage();
                file_put_contents($install_err_log, date('c') . " - Admin Insert Error: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // Write install log
        $summary = date('c') . ' - Install attempt - ' . (empty($errors) ? 'SUCCESS' : 'FAILED') . "\n";
        foreach ($logs as $l) $summary .= " - " . $l . "\n";
        if (!empty($errors)) foreach ($errors as $e) $summary .= " ! " . $e . "\n";
        file_put_contents($install_log, $summary, FILE_APPEND);

        // On success, clear session and prepare success view
        if (empty($errors)) {
            // Save cron key for display
            $_SESSION['install_result'] = ['cron_key' => $cron_key, 'admin_email' => $admin['email']];
            // Clear sensitive session data
            unset($_SESSION['install_db'], $_SESSION['install_smtp'], $_SESSION['install_admin']);
            // Redirect to success view
            header('Location: ?step=success&force=true');
            exit;
        }

        // If errors, fall through to render with $errors displayed
    }
}

// Handle step navigation and storing inputs
$step = $_GET['step'] ?? ($_POST['step'] ?? '1');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['do_install'])) {
    // Save step-specific data
    if (!hash_equals($_SESSION['install_csrf'] ?? '', $_POST['csrf'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        if (!empty($_POST['save_step']) && $_POST['save_step'] == '2') {
            // DB step saved
            $_SESSION['install_db'] = [
                'host' => trim($_POST['db_host'] ?? 'localhost'),
                'name' => trim($_POST['db_name'] ?? 'tradeonix_db'),
                'user' => trim($_POST['db_user'] ?? 'root'),
                'pass' => $_POST['db_pass'] ?? '',
            ];
            $step = '3';
        }
        if (!empty($_POST['save_step']) && $_POST['save_step'] == '3') {
            $_SESSION['install_smtp'] = [
                'host' => trim($_POST['smtp_host'] ?? ''),
                'port' => intval($_POST['smtp_port'] ?? 587),
                'user' => trim($_POST['smtp_user'] ?? ''),
                'pass' => $_POST['smtp_pass'] ?? '',
                'from' => trim($_POST['smtp_from'] ?? ''),
                'from_name' => trim($_POST['smtp_from_name'] ?? 'Investment Platform'),
            ];
            $step = '3b';
        }
        if (!empty($_POST['save_step']) && $_POST['save_step'] == '3b') {
            $site_url = trim($_POST['site_url'] ?? '');
            // Validate and normalize site URL
            if (empty($site_url)) {
                $site_url = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            }
            // Remove trailing slash
            $site_url = rtrim($site_url, '/');
            // Ensure https:// prefix
            if (!preg_match('#^https?://#i', $site_url)) {
                $site_url = 'https://' . $site_url;
            }
            $_SESSION['install_site_url'] = $site_url;
            $step = '4';
        }
        if (!empty($_POST['save_step']) && $_POST['save_step'] == '4') {
            // Validate admin
            $name = trim($_POST['admin_name'] ?? '');
            $email = trim($_POST['admin_email'] ?? '');
            $p1 = $_POST['admin_pass'] ?? '';
            $p2 = $_POST['admin_pass_confirm'] ?? '';
            $verrors = [];
            if ($name === '') $verrors[] = 'Admin name required.';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $verrors[] = 'Invalid email.';
            if (strlen($p1) < 8) $verrors[] = 'Password must be at least 8 characters.';
            if ($p1 !== $p2) $verrors[] = 'Passwords do not match.';
            if (empty($verrors)) {
                $_SESSION['install_admin'] = ['name' => $name, 'email' => $email, 'password' => $p1];
                $step = '5';
            } else {
                $error = implode('\n', $verrors);
                $step = '4';
            }
        }
    }
}

// Render UI
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Install - Investment Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9
        }

        .card {
            margin-top: 30px
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-9">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h3 class="card-title">Installation Wizard</h3>
                        <p class="text-muted">Step <?php echo h($step === '3b' ? '3b' : $step); ?> of 6</p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo h($error); ?></div>
                        <?php endif; ?>

                        <?php if ($step === '1'): // Requirements check 
                        ?>
                            <?php
                            $checks = [];
                            $checks[] = ['label' => 'PHP >= 8.0', 'ok' => version_compare(PHP_VERSION, '8.0', '>=')];
                            $req_ext = ['pdo', 'pdo_mysql', 'gettext', 'mbstring', 'fileinfo'];
                            foreach ($req_ext as $ext) $checks[] = ['label' => "Extension: {$ext}", 'ok' => extension_loaded($ext)];
                            $dirs = ['uploads', 'logs', $base_dir];
                            foreach ($dirs as $d) $checks[] = ['label' => "Writable: {$d}", 'ok' => is_writable($base_dir . DIRECTORY_SEPARATOR . $d) || is_writable($base_dir)];
                            ?>
                            <ul class="list-group mb-3">
                                <?php foreach ($checks as $c): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <?php echo h($c['label']); ?>
                                        <?php if ($c['ok']): ?>
                                            <span class="badge bg-success">✓</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">✗</span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php $all_ok = array_reduce($checks, function ($carry, $i) {
                                return $carry && $i['ok'];
                            }, true); ?>
                            <div class="d-flex justify-content-end">
                                <a class="btn btn-secondary me-2" href="?step=1">Refresh</a>
                                <a class="btn btn-primary <?php echo $all_ok ? '' : 'disabled'; ?>" href="?step=2">Next</a>
                            </div>

                        <?php elseif ($step === '2'): // DB form 
                        ?>
                            <form method="post" class="needs-validation" novalidate>
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="save_step" value="2">
                                <div class="mb-3">
                                    <label class="form-label">DB Host</label>
                                    <input name="db_host" class="form-control" value="<?php echo h($_SESSION['install_db']['host'] ?? 'localhost'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">DB Name</label>
                                    <input name="db_name" class="form-control" value="<?php echo h($_SESSION['install_db']['name'] ?? 'tradeonix_db'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">DB User</label>
                                    <input name="db_user" class="form-control" value="<?php echo h($_SESSION['install_db']['user'] ?? 'root'); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">DB Password</label>
                                    <input type="password" name="db_pass" class="form-control" value="<?php echo h($_SESSION['install_db']['pass'] ?? ''); ?>">
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a class="btn btn-secondary" href="?step=1">Back</a>
                                    <div>
                                        <button type="button" id="testDb" class="btn btn-success">Test Connection</button>
                                        <button class="btn btn-primary" type="submit">Save & Next</button>
                                    </div>
                                </div>
                            </form>

                            <script>
                                document.getElementById('testDb').addEventListener('click', function() {
                                    var form = this.closest('form');
                                    var data = new FormData(form);
                                    data.append('ajax_action', 'test_db');
                                    data.append('csrf', '<?php echo $csrf; ?>');
                                    fetch('', {
                                        method: 'POST',
                                        body: data
                                    }).then(r => r.json()).then(j => {
                                        if (j.ok) alert('Success: ' + (j.version || j.msg));
                                        else alert('Error: ' + j.error);
                                    });
                                });
                            </script>

                        <?php elseif ($step === '3'): // SMTP form 
                        ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="save_step" value="3">
                                <div class="mb-3">
                                    <label class="form-label">SMTP Host</label>
                                    <input name="smtp_host" class="form-control" value="<?php echo h($_SESSION['install_smtp']['host'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Port</label>
                                    <input name="smtp_port" class="form-control" value="<?php echo h($_SESSION['install_smtp']['port'] ?? 587); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP User</label>
                                    <input name="smtp_user" class="form-control" value="<?php echo h($_SESSION['install_smtp']['user'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SMTP Pass</label>
                                    <input type="password" name="smtp_pass" class="form-control" value="<?php echo h($_SESSION['install_smtp']['pass'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">From Email</label>
                                    <input name="smtp_from" class="form-control" value="<?php echo h($_SESSION['install_smtp']['from'] ?? ''); ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">From Name</label>
                                    <input name="smtp_from_name" class="form-control" value="<?php echo h($_SESSION['install_smtp']['from_name'] ?? 'Investment Platform'); ?>">
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a class="btn btn-secondary" href="?step=2">Back</a>
                                    <div>
                                        <button type="button" id="testEmail" class="btn btn-success">Send Test Email</button>
                                        <button class="btn btn-primary" type="submit">Save & Next</button>
                                    </div>
                                </div>
                            </form>
                            <script>
                                document.getElementById('testEmail').addEventListener('click', function() {
                                    var form = this.closest('form');
                                    var data = new FormData(form);
                                    data.append('ajax_action', 'test_email');
                                    data.append('test_to', prompt('Send test email to (address):'));
                                    data.append('csrf', '<?php echo $csrf; ?>');
                                    fetch('', {
                                        method: 'POST',
                                        body: data
                                    }).then(r => r.json()).then(j => {
                                        if (j.ok) alert('Success: ' + j.msg);
                                        else alert('Error: ' + j.error);
                                    });
                                });
                            </script>

                        <?php elseif ($step === '3b'): // Site URL form 
                        ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="save_step" value="3b">
                                <div class="alert alert-info">
                                    <strong>Important:</strong> Enter your website's URL. This will be used in email templates and links.
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Site URL</label>
                                    <input name="site_url" class="form-control" value="<?php echo h($_SESSION['install_site_url'] ?? ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))); ?>" required placeholder="https://your-domain.com">
                                    <div class="form-text">Enter the full URL including https:// (e.g., https://invest.example.com)</div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a class="btn btn-secondary" href="?step=3">Back</a>
                                    <button class="btn btn-primary" type="submit">Save & Next</button>
                                </div>
                            </form>

                        <?php elseif ($step === '4'): // Admin form 
                        ?>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="save_step" value="4">
                                <div class="mb-3">
                                    <label class="form-label">Admin Name</label>
                                    <input name="admin_name" class="form-control" value="<?php echo h($_SESSION['install_admin']['name'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Admin Email</label>
                                    <input name="admin_email" class="form-control" value="<?php echo h($_SESSION['install_admin']['email'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" id="p1" name="admin_pass" class="form-control" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm Password</label>
                                    <input type="password" id="p2" name="admin_pass_confirm" class="form-control" required>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a class="btn btn-secondary" href="?step=3">Back</a>
                                    <button class="btn btn-primary" type="submit">Save & Next</button>
                                </div>
                            </form>
                            <script>
                                // Simple client-side password check
                                document.querySelector('form').addEventListener('submit', function(e) {
                                    var a = document.getElementById('p1').value,
                                        b = document.getElementById('p2').value;
                                    if (a.length < 8) {
                                        alert('Password must be at least 8 characters');
                                        e.preventDefault();
                                    }
                                    if (a !== b) {
                                        alert('Passwords do not match');
                                        e.preventDefault();
                                    }
                                });
                            </script>

                        <?php elseif ($step === '5'): // Review and Install 
                        ?>
                            <h5>Ready to install</h5>
                            <p>Review configuration and click Install. This will create the database schema, generate the .env file, and create the first admin user.</p>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo $csrf; ?>">
                                <input type="hidden" name="do_install" value="1">
                                <div class="d-flex justify-content-between">
                                    <a class="btn btn-secondary" href="?step=4">Back</a>
                                    <button class="btn btn-success" type="submit">Install</button>
                                </div>
                            </form>

                        <?php elseif ($step === 'success'): // Step 6 - Success 
                        ?>
                            <?php $res = $_SESSION['install_result'] ?? null; ?>
                            <div class="text-center">
                                <div style="font-size:72px; color:green">✓</div>
                                <h3>Installation Completed Successfully!</h3>
                                <p>Your investment platform is ready to use.</p>
                                <p><strong>Admin Email:</strong> <?php echo h($res['admin_email'] ?? ''); ?></p>
                                <p><strong>CRON_API_KEY:</strong> <code><?php echo h($res['cron_key'] ?? ''); ?></code></p>
                                <p>Login URL: <a href="<?php echo 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php'; ?>"><?php echo 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/login.php'; ?></a></p>
                                <ol class="text-start">
                                    <li>Delete this file: <code>install.php</code>. It will be auto-deleted now.</li>
                                    <li>Configure cron job: <code>* * * * * curl -s "https://YOUR-DOMAIN/cron/process-investments.php?key=<?php echo h($res['cron_key'] ?? ''); ?>" >/dev/null 2>&1</code></li>
                                    <li>Enable HTTPS and update APP_ENV if needed.</li>
                                    <li>Visit <a href="admin/settings.php">Admin Settings</a> to customize the site.</li>
                                    <li>Set file permissions as needed for production.</li>
                                </ol>
                                <p id="redirect">Redirecting to login page in <span id="count">10</span> seconds... <a href="login.php">Click here</a> if not redirected.</p>
                            </div>
                            <script>
                                // Attempt self-delete via fetch to same URL (server-side unlink will attempt too)
                                (function() {
                                    fetch('?do_delete=1').catch(() => {});
                                })();
                                var t = 10;
                                setInterval(function() {
                                    t--;
                                    if (t <= 0) location.href = '/login.php';
                                    document.getElementById('count').textContent = t;
                                }, 1000);
                            </script>

                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Self-delete endpoint (triggered by client fetch)
    if (isset($_GET['do_delete'])) {
        $result = false;
        $msg = '';
        try {
            $result = @unlink(__FILE__);
            $msg = $result ? 'deleted' : 'failed';
        } catch (Exception $e) {
            $msg = 'error: ' . $e->getMessage();
        }
        file_put_contents($install_log, date('c') . " - self-delete attempt: " . $msg . "\n", FILE_APPEND);
        echo json_encode(['ok' => $result, 'msg' => $msg]);
        exit;
    }

    // If not success, show any errors collected
    if (!empty($errors)) {
        echo "<div class=\"container mt-3\"><div class=\"alert alert-danger\"><strong>Errors:</strong><ul>";
        foreach ($errors as $e) echo "<li>" . h($e) . "</li>";
        echo "</ul></div></div>";
    }

    ?>