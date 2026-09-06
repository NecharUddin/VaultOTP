<?php
/** VaultOTP | Author: ItNexBD | https://itnexbd.com | https://github.com/NecharUddin */
require_once __DIR__.'/app/config.php';
try{init_db();}catch(Throwable $e){$dbError='Database connection failed. Check app/config.php and your cPanel MySQL database/user settings.';}
if(is_logged_in())redirect('index.php');
$error='';
if(!empty($dbError))$error=$dbError;elseif(!is_installed())redirect('setup.php');
if($_SERVER['REQUEST_METHOD']==='POST' && !$error){
    verify_csrf();
    if(!login_attempt_allowed()){$error='Too many attempts. Try again later.';}else{
        $username=strtolower(trim((string)($_POST['username']??'')));
        $password=(string)($_POST['password']??'');
        try{
            $settings=db()->query('SELECT * FROM settings WHERE id=1')->fetch();
            $authenticated=false; $sessionKey=''; $uid=0; $display=''; $role='owner';
            if($username==='' || $username==='owner'){
                if($settings && password_verify($password,$settings['password_hash'])){
                    $unlockKey=derive_key($password,$settings['salt']);
                    $sessionKey=ensure_data_key($unlockKey);
                    $uid=ensure_owner_user($password,$sessionKey,$settings); $display='Owner'; $authenticated=true;
                }
            }else{
                $u=db()->prepare('SELECT * FROM users WHERE username=? AND enabled=1 AND is_owner=0');$u->execute([$username]);$user=$u->fetch();
                if($user && password_verify($password,$user['password_hash'])){
                    $unlockKey=derive_key($password,$user['password_salt']);
                    $sessionKey=unwrap_data_key($user['key_ciphertext'],$user['key_nonce'],$password,$user['password_salt']);
                    if(strlen($sessionKey)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('This user account cannot unlock the vault. Ask the owner to reset the user password.');
                    // Imported accounts keep the encryption key wrapped by their own password.
                    $sessionKey=unwrap_imported_session_key((int)$user['id'],$sessionKey,$password);
                    $pdo=db();$pdo->prepare('UPDATE users SET last_login=?,updated_at=? WHERE id=?')->execute([time(),time(),$user['id']]);$uid=(int)$user['id'];$display=$user['display_name'];$role=$user['role'];$authenticated=true;
                }
            }
            if($authenticated){session_regenerate_id(true);$_SESSION['authenticated']=true;$_SESSION['user_id']=$uid;$_SESSION['display_name']=$display;$_SESSION['user_role']=$role;$_SESSION['last_activity']=time();$_SESSION['vault_key']=base64_encode($sessionKey);$_SESSION['vault_key_slot']=($role==='owner'?0:(int)($user['data_key_id']??0)); if($role==='owner'){ repair_owner_created_user_keys($sessionKey); } clear_login_failures();log_activity('Unlocked vault');redirect('index.php');}
            record_login_failure();$error='Incorrect username or password.';
        }catch(Throwable $e){record_login_failure();$error='Unable to unlock this account. '.$e->getMessage();}
    }
}
$pageTitle='Unlock';include __DIR__.'/app/header.php';
?>
<div class="auth-card login-card"><div class="auth-head"><div class="auth-brand">VaultOTP</div><button class="btn theme-btn" type="button" data-theme-toggle><span data-theme-label>◐ Dark mode</span></button></div><div class="lock-badge">🔒</div><h1>Welcome back</h1><p>Sign in with the owner account or your assigned team account.</p><?php if($error):?><div class="flash error"><?=e($error)?></div><?php endif;?><form method="post" autocomplete="on"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><div class="field"><label>USERNAME <span>OPTIONAL FOR OWNER</span></label><input autofocus type="text" name="username" autocomplete="username" placeholder="owner or your username"></div><div class="field"><label>PASSWORD</label><input type="password" name="password" required autocomplete="current-password" placeholder="Enter your password"></div><button class="btn primary">Unlock Vault →</button></form><div class="login-note"><b>Team access</b><span>Employees use their own account. The master password is never shared with them.</span></div></div>
<?php include __DIR__.'/app/footer.php'; ?>
