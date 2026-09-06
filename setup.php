<?php
/**
 * VaultOTP
 * Author: ItNexBD
 * Website: https://itnexbd.com
 * Source: https://github.com/NecharUddin
 */

declare(strict_types=1);

/*
 * First-run installer. It deliberately stays independent from the main
 * database connection until the credentials have been checked.
 */
require_once __DIR__ . '/app/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (is_file(__DIR__ . '/app/config.local.php')) {
    require_once __DIR__ . '/app/config.local.php';

    try {
        if (is_installed()) {
            redirect('login.php');
        }
    } catch (Throwable $e) {
        // A broken/partial installation should still allow the installer to run.
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
        $sessionToken = isset($_SESSION['setup_csrf']) ? (string) $_SESSION['setup_csrf'] : '';

        if ($sessionToken === '' || $token === '' || !hash_equals($sessionToken, $token)) {
            throw new RuntimeException('Your setup session expired. Refresh this page and try again.');
        }

        $host = trim((string) ($_POST['db_host'] ?? 'localhost')) ?: 'localhost';
        $name = trim((string) ($_POST['db_name'] ?? ''));
        $user = trim((string) ($_POST['db_user'] ?? ''));
        $pass = (string) ($_POST['db_pass'] ?? '');
        $master = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['confirm'] ?? '');

        if ($name === '' || $user === '') {
            throw new RuntimeException('Database name and database user are required.');
        }
        if (strlen($master) < 12) {
            throw new RuntimeException('Master password must be at least 12 characters.');
        }
        if (!hash_equals($master, $confirm)) {
            throw new RuntimeException('Passwords do not match.');
        }
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('PHP PDO MySQL is not enabled on this hosting account. Enable pdo_mysql in cPanel PHP Selector.');
        }
        if (!function_exists('sodium_crypto_pwhash') || !function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('PHP Sodium is not enabled on this hosting account. Enable sodium in cPanel PHP Selector.');
        }

        $dsn = 'mysql:host=' . $host . ';dbname=' . $name . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        install_schema($pdo);

        $salt = random_bytes(VAULTOTP_PWHASH_SALT_BYTES);
        $passwordAlgorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $passwordHash = password_hash($master, $passwordAlgorithm);
        if ($passwordHash === false) {
            throw new RuntimeException('The server could not create a secure password hash.');
        }

        $now = time();
        $stmt = $pdo->prepare(
            'INSERT INTO settings (id, password_hash, salt, created_at, updated_at)
             VALUES (1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), salt = VALUES(salt), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([$passwordHash, $salt, $now, $now]);

        $config = "<?php\n";
        $config .= 'const DB_HOST = ' . var_export($host, true) . ";\n";
        $config .= 'const DB_NAME = ' . var_export($name, true) . ";\n";
        $config .= 'const DB_USER = ' . var_export($user, true) . ";\n";
        $config .= 'const DB_PASS = ' . var_export($pass, true) . ";\n";

        $configPath = __DIR__ . '/app/config.local.php';
        if (file_put_contents($configPath, $config, LOCK_EX) === false) {
            throw new RuntimeException('VaultOTP could not save its database configuration. Give the app folder temporary write permission and run setup again.');
        }
        @chmod($configPath, 0600);

        unset($_SESSION['setup_csrf']);
        session_regenerate_id(true);
        redirect('login.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Setup';
include __DIR__ . '/app/header.php';
?>
<div class="auth-card setup-card">
  <div class="setup-brand-row">
    <div class="setup-brand"><span class="setup-brand-mark">🔐</span><div><strong>VaultOTP</strong><small>Private recovery-code vault</small></div></div>
    <button class="btn theme-btn" id="setup-theme-toggle" type="button" data-theme-toggle aria-label="Switch to dark mode"><span data-theme-label>◐ Dark mode</span></button>
  </div>
  <div class="setup-intro">
    <span class="setup-step">FIRST-TIME SETUP</span>
    <h1>Set up your secure vault</h1>
    <p>Connect your cPanel MySQL database, then create the master password that protects your VaultOTP data.</p>
  </div>
  <div class="setup-note"><span>✓</span><div><strong>Self-hosted &amp; encrypted</strong><small>Your database stays on your hosting account. Recovery codes are encrypted at rest.</small></div></div>
  <?php if ($error !== ''): ?><div class="flash error"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="off" class="setup-form">
    <input type="hidden" name="csrf" value="<?= e($_SESSION['setup_csrf']) ?>">
    <div class="setup-section"><div class="setup-section-head"><span>01</span><div><h2>Database connection</h2><p>Use the MySQL database and user created in cPanel.</p></div></div>
      <div class="setup-form-grid">
        <div class="field"><label>DATABASE HOST</label><input name="db_host" value="<?= e($_POST['db_host'] ?? 'localhost') ?>" placeholder="localhost" required></div>
        <div class="field"><label>DATABASE NAME</label><input name="db_name" value="<?= e($_POST['db_name'] ?? '') ?>" placeholder="cpaneluser_vaultotp" required></div>
        <div class="field"><label>DATABASE USER</label><input name="db_user" value="<?= e($_POST['db_user'] ?? '') ?>" placeholder="cpaneluser_vaultotp" required></div>
        <div class="field"><label>DATABASE PASSWORD</label><input type="password" name="db_pass" autocomplete="new-password" placeholder="Enter database password"></div>
      </div>
    </div>
    <div class="setup-section"><div class="setup-section-head"><span>02</span><div><h2>Master password</h2><p>This password unlocks your VaultOTP data. Never share it with employees.</p></div></div>
      <div class="setup-form-grid"><div class="field"><label>MASTER PASSWORD</label><input type="password" name="password" minlength="12" required autocomplete="new-password" placeholder="At least 12 characters"></div><div class="field"><label>CONFIRM MASTER PASSWORD</label><input type="password" name="confirm" minlength="12" required autocomplete="new-password" placeholder="Repeat your password"></div></div>
    </div>
    <button class="btn primary setup-submit" type="submit"><span>Create secure vault</span><b>→</b></button>
  </form>
</div>
<?php include __DIR__ . '/app/footer.php'; ?>
