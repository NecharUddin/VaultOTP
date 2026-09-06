<?php
/** VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */
require_once __DIR__.'/app/config.php';
init_db();
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'password') {
        require_permission('manage_settings');
        if (!is_owner()) { flash('error','Only the owner can change the master password.'); redirect('settings.php'); }
        $old=(string)($_POST['old']??'');$new=(string)($_POST['new']??'');$confirm=(string)($_POST['confirm']??'');$settings=db()->query('SELECT * FROM settings WHERE id=1')->fetch();
        if(!$settings || !password_verify($old,$settings['password_hash'])) flash('error','Current password is incorrect.');
        elseif(strlen($new)<12) flash('error','New password must be at least 12 characters.');
        elseif($new!==$confirm) flash('error','Passwords do not match.');
        else{try{$dataKey=key_from_session();$hash=password_hash($new,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);if($hash===false)throw new RuntimeException('Could not create password hash.');[$salt,$cipher,$nonce]=wrap_data_key($dataKey,$new);$pdo=db();$pdo->beginTransaction();$pdo->prepare('UPDATE settings SET password_hash=?,salt=?,data_nonce=?,data_ciphertext=?,updated_at=? WHERE id=1')->execute([$hash,$salt,$nonce,$cipher,time()]);$owner=$pdo->query('SELECT id FROM users WHERE is_owner=1 LIMIT 1')->fetchColumn();if($owner)$pdo->prepare('UPDATE users SET password_hash=?,password_salt=?,key_ciphertext=?,key_nonce=?,updated_at=? WHERE id=?')->execute([$hash,$salt,$cipher,$nonce,time(),(int)$owner]);$pdo->commit();log_activity('Changed master password');flash('success','Master password changed. Stored recovery codes were not re-encrypted unnecessarily.');}catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();flash('error','Password change failed; nothing was changed.');}}
        redirect('settings.php');
    }
    if ($action === 'my_password') {
        if(is_owner()){flash('warning','Use the master password section for the owner account.');redirect('settings.php');}
        $old=(string)($_POST['old']??'');$new=(string)($_POST['new']??'');$confirm=(string)($_POST['confirm']??'');$u=current_user();
        if(!$u || !password_verify($old,$u['password_hash'])) flash('error','Current password is incorrect.');
        elseif(strlen($new)<12) flash('error','New password must be at least 12 characters.');
        elseif($new!==$confirm) flash('error','Passwords do not match.');
        else{try{$hash=password_hash($new,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);if($hash===false)throw new RuntimeException('Could not create password hash.');[$salt,$cipher,$nonce]=wrap_data_key(key_from_session(),$new);db()->prepare('UPDATE users SET password_hash=?,password_salt=?,key_ciphertext=?,key_nonce=?,updated_at=? WHERE id=?')->execute([$hash,$salt,$cipher,$nonce,time(),(int)$u['id']]);log_activity('Changed own password');flash('success','Your password has been changed.');}catch(Throwable $e){flash('error','Password change failed.');}}
        redirect('settings.php');
    }
    if ($action === 'autolock') {
        require_permission('manage_settings');
        if(!is_owner()){flash('error','Only the owner can change the shared auto-lock policy.');redirect('settings.php');}
        $value=(int)($_POST['auto_lock']??900);if(!in_array($value,[300,900,1800,3600],true))$value=900;db()->prepare('UPDATE settings SET auto_lock=?,updated_at=? WHERE id=1')->execute([$value,time()]);log_activity('Changed auto-lock setting');flash('success','Auto-lock preference saved.');redirect('settings.php');
    }
    if ($action === 'export') {
        require_permission('manage_backups'); if(!is_owner()){flash('error','Only the owner can create a full installation backup.');redirect('settings.php');}
        try{$backupPassword=(string)($_POST['backup_password']??'');$backupConfirm=(string)($_POST['backup_password_confirm']??'');if(strlen($backupPassword)<12)throw new RuntimeException('Backup password must be at least 12 characters.');if(!hash_equals($backupPassword,$backupConfirm))throw new RuntimeException('Backup passwords do not match.');$data=export_vault_data(key_from_session(),$backupPassword);log_activity('Created encrypted backup');header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="vaultotp-backup.votp"');header('Content-Length: '.strlen($data));echo $data;exit;}catch(Throwable $e){flash('error','Backup could not be created: '.$e->getMessage());redirect('settings.php');}
    }
    if ($action === 'import') {
        require_permission('manage_backups'); if(!is_owner()){flash('error','Only the owner can restore a full installation backup.');redirect('settings.php');}
        try{if(empty($_FILES['backup']['tmp_name'])||($_FILES['backup']['error']??4)!==0)throw new RuntimeException('Choose a VaultOTP backup file.');if(($_FILES['backup']['size']??0)>5242880)throw new RuntimeException('Backup file is too large.');$blob=file_get_contents($_FILES['backup']['tmp_name']);if($blob===false)throw new RuntimeException('Could not read backup.');$backupPassword=(string)($_POST['backup_password']??'');if(strlen($backupPassword)<12)throw new RuntimeException('Enter the backup password used when this file was created.');[$vaults,$codes,$platforms,$usersImported]=import_vault_data($blob,$backupPassword,key_from_session());log_activity('Imported encrypted backup');$platformText=$platforms>0?" and $platforms platform(s)":'';$userText=$usersImported>0?" and $usersImported user(s) with their permissions":'';flash('success',"Imported $vaults vault(s), $codes code(s)$platformText$userText.");}catch(Throwable $e){flash('error',$e->getMessage());}redirect('settings.php');
    }
}

$row = db()->query('SELECT auto_lock FROM settings WHERE id=1')->fetch();
$auto = (int)($row['auto_lock'] ?? 900);
if (is_owner()) {
    // Owner audit view: show the latest 500 actions from every user, including the owner.
    $activity = db()->query('SELECT a.*, v.name, u.display_name AS actor_name, u.username AS actor_username FROM activity a LEFT JOIN vaults v ON v.id=a.vault_id LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 500')->fetchAll();
} else {
    // Team members only see their own activity history.
    $stmt = db()->prepare('SELECT a.*, v.name, u.display_name AS actor_name, u.username AS actor_username FROM activity a LEFT JOIN vaults v ON v.id=a.vault_id LEFT JOIN users u ON u.id=a.user_id WHERE a.user_id=? ORDER BY a.id DESC LIMIT 500');
    $stmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
    $activity = $stmt->fetchAll();
}
$pageTitle = 'Settings';
include __DIR__.'/app/header.php';
?>
<div class="topbar settings-topbar">
    <div>
        <div class="eyebrow">Security & preferences</div>
        <div class="title">Settings</div>
        <p class="subtitle">Manage your vault security, backups and account preferences.</p>
    </div>
    <button class="btn theme-btn" data-theme-toggle type="button"><span data-theme-label>◐ Dark mode</span></button>
</div>

<div class="settings-grid">
    <?php if(is_owner()): ?><section class="card settings-card wide">
        <div class="settings-card-head">
            <div>
                <span class="settings-icon">🔑</span>
                <div><h2>Master password</h2><p>Only the owner can change the master unlock password.</p></div>
            </div>
        </div>
        <form method="post" class="form-grid settings-form">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="password">
            <div class="field full"><label for="old-password">CURRENT PASSWORD</label><input id="old-password" type="password" name="old" required autocomplete="current-password" placeholder="Enter current password"></div>
            <div class="field"><label for="new-password">NEW PASSWORD</label><input id="new-password" type="password" name="new" minlength="12" required autocomplete="new-password" placeholder="At least 12 characters"></div>
            <div class="field"><label for="confirm-password">CONFIRM PASSWORD</label><input id="confirm-password" type="password" name="confirm" minlength="12" required autocomplete="new-password" placeholder="Repeat new password"></div>
            <div class="field full"><button class="btn primary" type="submit">Change password</button></div>
        </form>
    </section><?php endif; ?>

    <?php if(!is_owner()): ?><section class="card settings-card wide"><div class="settings-card-head"><div><span class="settings-icon">🔑</span><div><h2>Your password</h2><p>Change your personal team-account password.</p></div></div></div><form method="post" class="form-grid settings-form"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><input type="hidden" name="action" value="my_password"><div class="field"><label>CURRENT PASSWORD</label><input type="password" name="old" required autocomplete="current-password"></div><div class="field"><label>NEW PASSWORD</label><input type="password" name="new" minlength="12" required autocomplete="new-password" placeholder="At least 12 characters"></div><div class="field"><label>CONFIRM PASSWORD</label><input type="password" name="confirm" minlength="12" required autocomplete="new-password"></div><div class="field"><button class="btn primary" type="submit">Change my password</button></div></form></section><?php endif; ?>

    <?php if(is_owner()): ?><section class="card settings-card auto-lock-card wide">
        <div class="settings-card-head compact">
            <span class="settings-icon">⏱</span>
            <div><h2>Auto-lock</h2><p>Lock the vault after inactivity.</p></div>
        </div>
        <form method="post" class="inline-setting">
            <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
            <input type="hidden" name="action" value="autolock">
            <select name="auto_lock" aria-label="Auto-lock duration">
                <option value="300" <?=$auto===300?'selected':''?>>5 minutes</option>
                <option value="900" <?=$auto===900?'selected':''?>>15 minutes</option>
                <option value="1800" <?=$auto===1800?'selected':''?>>30 minutes</option>
                <option value="3600" <?=$auto===3600?'selected':''?>>60 minutes</option>
            </select>
            <button class="btn primary" type="submit">Save</button>
        </form>
    </section><?php endif; ?>

    <?php if(can('manage_backups')): ?><section class="card settings-card wide backup-settings-card">
        <div class="settings-card-head backup-main-head">
            <div>
                <span class="settings-icon backup-settings-icon">💾</span>
                <div>
                    <div class="backup-title-row"><h2>Encrypted backup</h2><span class="backup-secure-badge">Encrypted</span></div>
                    <p>Protect a portable copy of your vault and restore it whenever you need it.</p>
                </div>
            </div>
        </div>

        <div class="backup-workspace">
            <div class="backup-box backup-export-box">
                <div class="backup-box-head">
                    <div class="backup-box-icon">↑</div>
                    <div><h3>Create a backup</h3><p>Download an encrypted <code>.votp</code> file.</p></div>
                </div>
                <form method="post" class="backup-form">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="export">
                    <div class="backup-password-grid">
                        <div class="field">
                            <label for="backup-export-password">BACKUP PASSWORD</label>
                            <input id="backup-export-password" type="password" name="backup_password" minlength="12" autocomplete="new-password" placeholder="At least 12 characters" required>
                        </div>
                        <div class="field">
                            <label for="backup-export-confirm">CONFIRM PASSWORD</label>
                            <input id="backup-export-confirm" type="password" name="backup_password_confirm" minlength="12" autocomplete="new-password" placeholder="Repeat password" required>
                        </div>
                    </div>
                    <div class="backup-tip"><span>i</span><p>Use your master password if you want one password for both. The backup password is independent from your vault login.</p></div>
                    <button class="btn primary backup-action-btn" type="submit"><span>↓</span> Create encrypted backup</button>
                </form>
            </div>

            <div class="backup-box backup-import-box">
                <div class="backup-box-head">
                    <div class="backup-box-icon">↓</div>
                    <div><h3>Restore a backup</h3><p>Import a previously created <code>.votp</code> file.</p></div>
                </div>
                <form method="post" enctype="multipart/form-data" class="import-form">
                    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
                    <input type="hidden" name="action" value="import">
                    <label class="file-picker backup-file-picker">
                        <input type="file" name="backup" accept=".votp" required>
                        <span>Choose a .votp backup file</span>
                    </label>
                    <div class="field">
                        <label for="backup-import-password">BACKUP PASSWORD</label>
                        <input id="backup-import-password" type="password" name="backup_password" minlength="12" autocomplete="off" placeholder="Password used for this backup" required>
                    </div>
                    <button class="btn backup-action-btn" type="submit"><span>↥</span> Import backup</button>
                </form>
            </div>
        </div>
    </section><?php endif; ?>

    <section class="card settings-card">
        <div class="settings-card-head compact"><span class="settings-icon">🛡</span><div><h2>Security center</h2><p>Protection currently enabled for this vault.</p></div></div>
        <div class="security-list">
            <div><span>Encrypted recovery codes</span><b>Enabled</b></div>
            <div><span>Argon2id password hashing</span><b>Enabled</b></div>
            <div><span>CSRF protection</span><b>Enabled</b></div>
            <div><span>Secure sessions</span><b>Enabled</b></div>
            <div><span>Login throttling</span><b>Enabled</b></div>
        </div>
    </section>

    <section class="card settings-card" id="diagnostics">
        <div class="settings-card-head compact"><span class="settings-icon">✓</span><div><h2>System diagnostics</h2><p>Basic health checks for this installation.</p></div></div>
        <div class="diagnostics-grid">
            <div><span>PHP</span><b><?=e(PHP_VERSION)?></b></div>
            <div><span>PDO MySQL</span><b class="status-ok"><?=extension_loaded('pdo_mysql')?'Ready':'Missing'?></b></div>
            <div><span>libsodium</span><b class="status-ok"><?=function_exists('sodium_crypto_secretbox')?'Ready':'Missing'?></b></div>
            <div><span>Database</span><b class="status-ok">Connected</b></div>
            <div><span>Encryption</span><b class="status-ok">Secretbox</b></div>
            <div><span>Backup compression</span><b class="status-ok"><?=function_exists('gzcompress')&&function_exists('gzuncompress')?'Ready':'Fallback mode'?></b></div>
        </div>
    </section>

    <section class="card settings-card wide support-card">
        <div class="settings-card-head compact"><span class="settings-icon">?</span><div><h2>Need help or found an issue?</h2><p>If something does not work as expected, report it on the VaultOTP GitHub page with the error message and steps to reproduce it.</p></div></div>
        <a class="btn support-github-btn" href="https://github.com/NecharUddin" target="_blank" rel="noopener noreferrer">Report an issue on GitHub ↗</a>
    </section>

<?php if(can('view_activity')): ?><section class="card settings-card wide" id="activity">
        <div class="section-head activity-head"><div><h2>Activity log</h2><p><?=is_owner()?'A quick view of recent events. Use Activities for the full 1,000-event audit.':'Your recent security and vault events.'?></p></div><span class="activity-count"><?=is_owner()?'Latest 1,000 available in Activities':'Last 500 events'?></span></div>
        <div class="activity-list modern-activity">
            <?php foreach ($activity as $item): ?>
                <div class="activity-item">
                    <span class="activity-dot">•</span>
                    <div class="activity-copy"><strong><?=e($item['action'])?></strong><?php if ($item['name']): ?><span><?=e($item['name'])?></span><?php endif; ?><?php if(is_owner() && !empty($item['actor_name'])): ?><span>by <?=e($item['actor_name'])?><?php if(!empty($item['actor_username'])): ?> <small>(@<?=e($item['actor_username'])?>)</small><?php endif; ?></span><?php endif; ?></div>
                    <time datetime="<?=date('c',(int)$item['created_at'])?>"><?=date('M j, Y · g:i A',(int)$item['created_at'])?></time>
                </div>
            <?php endforeach; ?>
            <?php if (!$activity): ?><div class="empty">No activity yet.</div><?php endif; ?>
        </div>
    </section><?php endif; ?>
</div>
<?php include __DIR__.'/app/footer.php'; ?>
