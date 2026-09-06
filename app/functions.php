<?php
/**
 * VaultOTP
 * Author: ItNexBD
 * Website: https://itnexbd.com
 * Source: https://github.com/NecharUddin
 */

declare(strict_types=1);

// Sub-users may undo only their own recent "Mark used" action.
const VAULTOTP_MARK_UNDO_WINDOW = 900; // 15 minutes

function e(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function redirect(string $u): void { header('Location: '.$u); exit; }
function flash(string $t,string $m): void { $_SESSION['flash'][]=['type'=>$t,'message'=>$m]; }
function flashes(): array { $x=$_SESSION['flash']??[]; unset($_SESSION['flash']); return $x; }
function csrf_token(): string { if(empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { $a=(string)($_SESSION['csrf']??'');$b=(string)($_POST['csrf']??'');if($a===''||$b===''||!hash_equals($a,$b)){http_response_code(419);exit('Invalid request.');} }
function is_logged_in(): bool { return !empty($_SESSION['authenticated']); }
function get_auto_lock(): int { try{$v=(int)db()->query("SELECT auto_lock FROM settings WHERE id=1")->fetchColumn();return in_array($v,[300,900,1800,3600],true)?$v:900;}catch(Throwable $e){return 900;} }
function require_login(): void { if(!is_logged_in()) redirect('login.php'); if((int)($_SESSION['user_id']??0)<=0 || !current_user()){ logout(false); redirect('login.php?session=expired'); } $timeout=get_auto_lock(); if(time()-(int)($_SESSION['last_activity']??0)>$timeout){logout(false);redirect('login.php?locked=1');} $_SESSION['last_activity']=time(); }
function logout(bool $go=true): void { if(is_logged_in()){ log_activity($go?'Locked vault':'Vault locked after inactivity'); } $_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),' ',time()-42000,$p['path'],$p['domain']??'',$p['secure']??false,$p['httponly']??true);}session_destroy();if($go)redirect('login.php'); }

const VAULTOTP_PWHASH_SALT_BYTES=16;
const VAULTOTP_PWHASH_OPSLIMIT=2;
const VAULTOTP_PWHASH_MEMLIMIT=67108864;
const VAULTOTP_PWHASH_ALG=2;
function derive_key(string $password,string $salt): string { if(strlen($salt)!==VAULTOTP_PWHASH_SALT_BYTES)throw new RuntimeException('Invalid password salt.');return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETBOX_KEYBYTES,$password,$salt,VAULTOTP_PWHASH_OPSLIMIT,VAULTOTP_PWHASH_MEMLIMIT,VAULTOTP_PWHASH_ALG); }
function encrypt_code(string $plain,string $key): array {$nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);return [sodium_crypto_secretbox($plain,$nonce,$key),$nonce];}
function decrypt_code(string $cipher,string $nonce,string $key): string {$p=sodium_crypto_secretbox_open($cipher,$nonce,$key);if($p===false)throw new RuntimeException('Decrypt failed');return $p;}
function key_from_session(): string {$k=base64_decode($_SESSION['vault_key']??'',true);if($k===false||strlen($k)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('Vault key unavailable');return $k;}
function session_key_slot(): int { return max(0,(int)($_SESSION['vault_key_slot']??0)); }
function data_key_for_slot(int $slotId, string $currentKey): string {
    if ($slotId === 0) return $currentKey;
    $s=db()->prepare('SELECT ciphertext,nonce FROM data_key_slots WHERE id=? LIMIT 1'); $s->execute([$slotId]); $r=$s->fetch();
    if (!$r) throw new RuntimeException('The encryption key for this vault is unavailable.');
    return decrypt_code($r['ciphertext'],$r['nonce'],$currentKey);
}
function key_for_vault(array $vault, string $currentKey): string {
    $slot=(int)($vault['data_key_id']??0);
    if ($slot===0) return $currentKey;
    if (is_owner()) return data_key_for_slot($slot,$currentKey);
    $userSlot=session_key_slot();
    if ($slot === $userSlot) return $currentKey;
    $uid=(int)($_SESSION['user_id']??0);
    if($uid>0){
        $s=db()->prepare('SELECT ciphertext,nonce FROM user_data_key_slots WHERE user_id=? AND slot_id=? LIMIT 1');
        $s->execute([$uid,$slot]); $r=$s->fetch();
        if($r){
            try { $k=decrypt_code($r['ciphertext'],$r['nonce'],$currentKey); if(strlen($k)===SODIUM_CRYPTO_SECRETBOX_KEYBYTES)return $k; } catch(Throwable $e) {}
        }
    }
    // Compatibility fallback: data_key_slots are wrapped by the installation data key.
    // Normal sub-user sessions also hold that installation data key, so they can safely
    // unwrap a vault slot even when an older import did not create user_data_key_slots.
    try {
        $k=data_key_for_slot($slot,$currentKey);
        if(strlen($k)===SODIUM_CRYPTO_SECRETBOX_KEYBYTES)return $k;
    } catch(Throwable $e) {}
    throw new RuntimeException('This vault encryption key is unavailable to this account.');
}
function create_key_slot(string $dataKey, string $currentKey, string $label='Imported key'): int {
    [$cipher,$nonce]=encrypt_code($dataKey,$currentKey);
    $s=db()->prepare('INSERT INTO data_key_slots(label,ciphertext,nonce,created_at) VALUES(?,?,?,?)'); $s->execute([substr($label,0,150),$cipher,$nonce,time()]);
    return (int)db()->lastInsertId();
}
function login_attempt_allowed(): bool {$n=time();$_SESSION['login_attempts']=array_values(array_filter($_SESSION['login_attempts']??[],fn($t)=>$n-$t<LOGIN_WINDOW));return count($_SESSION['login_attempts'])<MAX_LOGIN_ATTEMPTS;}
function record_login_failure(): void {$_SESSION['login_attempts'][]=time();}
function clear_login_failures(): void {unset($_SESSION['login_attempts']);}
function get_user(int $id): ?array { $s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$id]);return $s->fetch()?:null; }
function get_user_permissions(int $id): array { $s=db()->prepare('SELECT * FROM user_permissions WHERE user_id=?');$s->execute([$id]);$r=$s->fetch()?:[];return $r; }
function get_user_vault_access(int $id): array { $s=db()->prepare('SELECT * FROM vault_access WHERE user_id=?');$s->execute([$id]);$out=[];foreach($s as $r)$out[(int)$r['vault_id']]=$r;return $out; }
function user_display_name(): string { return (string)(current_user()['display_name']??'Owner'); }
function current_user(): ?array { static $cached=null; static $loaded=false; if($loaded)return $cached; $loaded=true; $id=(int)($_SESSION['user_id']??0); if($id<=0)return null; $s=db()->prepare('SELECT * FROM users WHERE id=? AND enabled=1');$s->execute([$id]);$cached=$s->fetch()?:null;return $cached;}
function is_owner(): bool { $u=current_user(); return $u ? (int)$u['is_owner']===1 : false; }
function current_role(): string { return (string)(current_user()['role']??'member'); }
function can(string $permission): bool { if(is_owner())return true; $u=current_user(); if(!$u)return false; $allowed=['create_vault','manage_platforms','manage_backups','view_activity','reveal_codes','copy_codes','add_codes','mark_codes','delete_codes','edit_vaults','delete_vaults']; if(!in_array($permission,$allowed,true))return false; $s=db()->prepare("SELECT $permission FROM user_permissions WHERE user_id=?");$s->execute([(int)$u['id']]);return (int)$s->fetchColumn()===1;}
function can_any(array $permissions): bool { foreach($permissions as $permission){ if(can((string)$permission)) return true; } return false;}
function require_permission(string $permission): void { if(!can($permission)){http_response_code(403);exit('You do not have permission to access this area.');} }
function user_can_vault(int $vaultId,string $action='view'): bool { if(is_owner())return true; $u=current_user();if(!$u)return false; $map=['view'=>'can_view','reveal'=>'can_reveal','copy'=>'can_copy','add'=>'can_add','mark'=>'can_mark','delete_code'=>'can_delete_code','edit'=>'can_edit','delete'=>'can_delete']; $global=['view'=>'1','reveal'=>'reveal_codes','copy'=>'copy_codes','add'=>'add_codes','mark'=>'mark_codes','delete_code'=>'delete_codes','edit'=>'edit_vaults','delete'=>'delete_vaults']; $col=$map[$action]??'can_view'; $g=$global[$action]??null; if((int)$u['all_vaults']===1)return $g==='1'||($g!==null&&can($g)); $s=db()->prepare("SELECT $col FROM vault_access WHERE user_id=? AND vault_id=?");$s->execute([(int)$u['id'],$vaultId]);return (int)$s->fetchColumn()===1;}
function require_vault_access(int $vaultId,string $action='view'): void { if(!user_can_vault($vaultId,$action)){http_response_code(403);exit('You do not have access to this vault.');} }
function accessible_vault_sql(string $alias='v'): array { if(is_owner())return ['',[]];$u=current_user();if(!$u)return [' AND 1=0',[]];if((int)$u['all_vaults']===1)return ['',[]];return [" AND EXISTS (SELECT 1 FROM vault_access va WHERE va.vault_id={$alias}.id AND va.user_id=? AND va.can_view=1)",[(int)$u['id']]];}
function wrap_data_key(string $dataKey,string $password): array { $salt=random_bytes(VAULTOTP_PWHASH_SALT_BYTES);$unlock=derive_key($password,$salt);[$cipher,$nonce]=encrypt_code($dataKey,$unlock);return [$salt,$cipher,$nonce]; }
function unwrap_data_key(string $cipher,string $nonce,string $password,string $salt): string { return decrypt_code($cipher,$nonce,derive_key($password,$salt)); }
function unwrap_imported_session_key(int $userId,string $sessionKey,string $password): string {
    if ($userId <= 0) return $sessionKey;
    // Imported v7 accounts normally keep their source encryption key in the user
    // wrapper and carry the corresponding target slot in users.data_key_id. In that
    // case the session key must NOT be replaced with the installation key.
    $u=get_user($userId);
    $userSlot=(int)($u['data_key_id']??0);
    if ($userSlot > 0) {
        // A migration row from an older broken import is not applicable to a
        // per-slot user; leave the original source key intact.
        try { db()->prepare('DELETE FROM user_key_migrations WHERE user_id=?')->execute([$userId]); } catch (Throwable $e) {}
        return $sessionKey;
    }
    try {
        $q=db()->prepare('SELECT ciphertext,nonce FROM user_key_migrations WHERE user_id=? LIMIT 1');
        $q->execute([$userId]); $r=$q->fetch();
        if (!$r) return $sessionKey;
        $newKey=decrypt_code($r['ciphertext'],$r['nonce'],$sessionKey);
        if (strlen($newKey)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('Invalid migrated encryption key.');
        // Legacy broken imports stored the imported installation key encrypted by
        // the user's source key. Re-wrap it with the user's password so future
        // logins use the normal account wrapper.
        [$salt,$cipher,$nonce]=wrap_data_key($newKey,$password);
        $pdo=db(); $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_salt=?,key_ciphertext=?,key_nonce=?,data_key_id=0,updated_at=? WHERE id=?')->execute([$salt,$cipher,$nonce,time(),$userId]);
        $pdo->prepare('DELETE FROM user_key_migrations WHERE user_id=?')->execute([$userId]);
        $pdo->commit();
        return $newKey;
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function ensure_data_key(string $unlockKey): string { $s=db()->query('SELECT * FROM settings WHERE id=1')->fetch();if(!$s)throw new RuntimeException('Vault settings are missing.');$cipher=$s['data_ciphertext']??null;$nonce=$s['data_nonce']??null;if(is_string($cipher)&&$cipher!==''&&is_string($nonce)&&strlen($nonce)===SODIUM_CRYPTO_SECRETBOX_NONCEBYTES){$key=decrypt_code($cipher,$nonce,$unlockKey);if(strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)throw new RuntimeException('Invalid vault encryption key.');return $key;}
 $dataKey=random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);$pdo=db();$pdo->beginTransaction();try{$rows=$pdo->query('SELECT id,ciphertext,nonce FROM codes')->fetchAll();$up=$pdo->prepare('UPDATE codes SET ciphertext=?,nonce=? WHERE id=?');foreach($rows as $r){$plain=decrypt_code($r['ciphertext'],$r['nonce'],$unlockKey);[$c,$n]=encrypt_code($plain,$dataKey);$up->execute([$c,$n,$r['id']]);}[$wrapped,$wrapNonce]=encrypt_code($dataKey,$unlockKey);$pdo->prepare('UPDATE settings SET data_nonce=?,data_ciphertext=?,updated_at=? WHERE id=1')->execute([$wrapNonce,$wrapped,time()]);$pdo->commit();return $dataKey;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
function ensure_owner_user(string $password,string $dataKey,array $settings): int { $pdo=db();$existing=$pdo->query('SELECT id FROM users WHERE is_owner=1 LIMIT 1')->fetchColumn();$hash=(string)$settings['password_hash'];[$salt,$cipher,$nonce]=wrap_data_key($dataKey,$password);if($existing){$pdo->prepare('UPDATE users SET password_hash=?,password_salt=?,key_ciphertext=?,key_nonce=?,updated_at=? WHERE id=?')->execute([$hash,$salt,$cipher,$nonce,time(),(int)$existing]);$id=(int)$existing;}else{$pdo->prepare("INSERT INTO users(username,display_name,password_hash,password_salt,key_ciphertext,key_nonce,role,enabled,is_owner,created_at,updated_at,last_login,all_vaults) VALUES('owner','Owner',?,?,?,?, 'owner',1,1,?,?,?,1)")->execute([$hash,$salt,$cipher,$nonce,time(),time(),time()]);$id=(int)$pdo->lastInsertId();}$perm=$pdo->prepare('INSERT INTO user_permissions(user_id,create_vault,manage_platforms,manage_users,manage_settings,manage_backups,view_activity,reveal_codes,copy_codes,add_codes,mark_codes,delete_codes,manage_codes,edit_vaults,delete_vaults) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE create_vault=1,manage_platforms=1,manage_users=1,manage_settings=1,manage_backups=1,view_activity=1,reveal_codes=1,copy_codes=1,add_codes=1,mark_codes=1,delete_codes=1,manage_codes=1,edit_vaults=1,delete_vaults=1');$perm->execute([$id,1,1,1,1,1,1,1,1,1,1,1,1,1,1]);return $id;}
function grant_creator_vault_access(int $vaultId): void { if(is_owner())return; $u=current_user();if(!$u|| (int)$u['all_vaults']===1)return; $p=get_user_permissions((int)$u['id']); db()->prepare('INSERT INTO vault_access(user_id,vault_id,can_view,can_reveal,can_copy,can_add,can_mark,can_delete_code,can_edit,can_delete) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_reveal=VALUES(can_reveal),can_copy=VALUES(can_copy),can_add=VALUES(can_add),can_mark=VALUES(can_mark),can_delete_code=VALUES(can_delete_code),can_edit=VALUES(can_edit),can_delete=VALUES(can_delete)')->execute([(int)$u['id'],$vaultId,1,(int)($p['reveal_codes']??0),(int)($p['copy_codes']??0),(int)($p['add_codes']??0),(int)($p['mark_codes']??0),(int)($p['delete_codes']??0),(int)($p['edit_vaults']??0),(int)($p['delete_vaults']??0)]);}
function provision_user_vault_keys(int $userId, array $vaultIds, string $ownerKey): void {
    if (!is_owner() || $userId <= 0) return;
    $pdo = db();
    $ins = $pdo->prepare('INSERT INTO user_data_key_slots(user_id,slot_id,ciphertext,nonce) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE ciphertext=VALUES(ciphertext),nonce=VALUES(nonce)');
    foreach (array_unique(array_map('intval',$vaultIds)) as $vid) {
        if ($vid <= 0) continue;
        $vault = get_vault($vid);
        if (!$vault) continue;
        $slot = (int)($vault['data_key_id'] ?? 0);
        if ($slot === 0) continue;
        try {
            $dataKey = data_key_for_slot($slot, $ownerKey);
            if (strlen($dataKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) continue;
            [$cipher,$nonce] = encrypt_code($dataKey,$ownerKey);
            $ins->execute([$userId,$slot,$cipher,$nonce]);
        } catch (Throwable $e) {
            error_log('VaultOTP: could not provision vault key for user '.$userId.' / vault '.$vid.': '.$e->getMessage());
        }
    }
}

function repair_owner_created_user_keys(string $ownerKey): void {
    if (!is_owner()) return;
    try {
        $pdo = db();
        $users = $pdo->query('SELECT id,all_vaults,data_key_id FROM users WHERE is_owner=0 AND enabled=1')->fetchAll();
        $allVaultIds = array_map('intval', $pdo->query('SELECT id FROM vaults ORDER BY id')->fetchAll(PDO::FETCH_COLUMN));
        $upsert = $pdo->prepare('INSERT INTO user_data_key_slots(user_id,slot_id,ciphertext,nonce) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE ciphertext=VALUES(ciphertext),nonce=VALUES(nonce)');
        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $userSlot = (int)($u['data_key_id'] ?? 0);
            // This is the key the sub-user receives after decrypting their own
            // password-wrapped account key. For imported users it can be an
            // imported source slot; for normal users it is the installation key.
            try {
                $userSessionKey = $userSlot > 0 ? data_key_for_slot($userSlot, $ownerKey) : $ownerKey;
            } catch (Throwable $e) {
                error_log('VaultOTP: could not resolve user key for user '.$uid.': '.$e->getMessage());
                continue;
            }
            if (strlen($userSessionKey) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) continue;

            if ((int)$u['all_vaults'] === 1) {
                $vaultIds = $allVaultIds;
            } else {
                $q = $pdo->prepare('SELECT vault_id FROM vault_access WHERE user_id=? AND can_view=1');
                $q->execute([$uid]);
                $vaultIds = array_map('intval', $q->fetchAll(PDO::FETCH_COLUMN));
            }

            foreach (array_unique($vaultIds) as $vid) {
                if ($vid <= 0) continue;
                $vault = get_vault($vid);
                if (!$vault) continue;
                $slot = (int)($vault['data_key_id'] ?? 0);
                if ($slot === 0) continue;
                try {
                    // Owner can unwrap the canonical vault key. Re-wrap that
                    // exact key with the sub-user's account/session key so the
                    // user can decrypt it without ever receiving the owner key.
                    $vaultKey = data_key_for_slot($slot, $ownerKey);
                    [$cipher,$nonce] = encrypt_code($vaultKey, $userSessionKey);
                    $upsert->execute([$uid,$slot,$cipher,$nonce]);
                } catch (Throwable $e) {
                    error_log('VaultOTP: could not provision vault key for user '.$uid.' / vault '.$vid.': '.$e->getMessage());
                }
            }
        }
    } catch (Throwable $e) {
        error_log('VaultOTP: user key repair skipped: '.$e->getMessage());
    }
}

function provision_user_access_keys(int $userId, bool $allVaults, array $vaultIds, string $ownerKey): void {
    if (!is_owner() || $userId <= 0) return;
    if ($allVaults) {
        $rows = db()->query('SELECT id FROM vaults ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        provision_user_vault_keys($userId,array_map('intval',$rows),$ownerKey);
    } else {
        provision_user_vault_keys($userId,$vaultIds,$ownerKey);
    }
}

function create_user_account(string $username,string $displayName,string $password,string $role,bool $allVaults,string $dataKey,array $perms,array $vaults): int { $username=strtolower(trim($username));if($username==='owner')throw new RuntimeException("The username 'owner' is reserved.");if(!preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/',$username))throw new RuntimeException('Username must be 3–60 characters and use letters, numbers, dot, underscore or hyphen.');if(strlen($password)<12)throw new RuntimeException('Password must be at least 12 characters.');if(!in_array($role,['viewer','member','manager'],true))$role='member';if($displayName==='')$displayName=$username;$pdo=db();$hash=password_hash($password,defined('PASSWORD_ARGON2ID')?PASSWORD_ARGON2ID:PASSWORD_DEFAULT);if($hash===false)throw new RuntimeException('Could not create password hash.');[$salt,$cipher,$nonce]=wrap_data_key($dataKey,$password);$pdo->beginTransaction();try{$pdo->prepare('INSERT INTO users(username,display_name,password_hash,password_salt,key_ciphertext,key_nonce,role,enabled,is_owner,created_at,updated_at,last_login,all_vaults) VALUES(?,?,?,?,?,?,?,1,0,?,?,NULL,?)')->execute([$username,substr($displayName,0,100),$hash,$salt,$cipher,$nonce,$role,time(),time(),$allVaults?1:0]);$id=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO user_permissions(user_id,create_vault,manage_platforms,manage_users,manage_settings,manage_backups,view_activity,reveal_codes,copy_codes,add_codes,mark_codes,delete_codes,manage_codes,edit_vaults,delete_vaults) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$id,(int)!empty($perms['create_vault']),(int)!empty($perms['manage_platforms']),0,0,(int)!empty($perms['manage_backups']),(int)!empty($perms['view_activity']),(int)!empty($perms['reveal_codes']),(int)!empty($perms['copy_codes']),(int)!empty($perms['add_codes']),(int)!empty($perms['mark_codes']),(int)!empty($perms['delete_codes']),((int)!empty($perms['add_codes'])||(int)!empty($perms['mark_codes'])||(int)!empty($perms['delete_codes'])),(int)!empty($perms['edit_vaults']),(int)!empty($perms['delete_vaults'])]);if(!$allVaults){$ins=$pdo->prepare('INSERT INTO vault_access(user_id,vault_id,can_view,can_reveal,can_copy,can_add,can_mark,can_delete_code,can_manage_codes,can_edit,can_delete) VALUES(?,?,?,?,?,?,?,?,?,?,?)');foreach(array_unique(array_map('intval',$vaults)) as $vid){if(!get_vault($vid))continue;$ins->execute([$id,$vid,1,(int)!empty($perms['reveal_codes']),(int)!empty($perms['copy_codes']),(int)!empty($perms['add_codes']),(int)!empty($perms['mark_codes']),(int)!empty($perms['delete_codes']),((int)!empty($perms['add_codes'])||(int)!empty($perms['mark_codes'])||(int)!empty($perms['delete_codes'])),(int)!empty($perms['edit_vaults']),(int)!empty($perms['delete_vaults'])]);}}$pdo->commit();return $id;}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}}
function can_undo_own_mark(int $codeId): bool {
    if (is_owner()) return true;
    $uid=(int)($_SESSION['user_id']??0);
    if ($uid<=0) return false;
    $s=db()->prepare('SELECT used,used_by,used_at FROM codes WHERE id=? LIMIT 1');
    $s->execute([$codeId]); $r=$s->fetch();
    if (!$r || (int)$r['used']!==1 || (int)($r['used_by']??0)!==$uid || $r['used_at']===null) return false;
    return (time()-(int)$r['used_at']) <= VAULTOTP_MARK_UNDO_WINDOW;
}

function log_activity(string $a,?int $id=null): void { try { $uid=(int)($_SESSION['user_id']??0); $s=db()->prepare("INSERT INTO activity(action,vault_id,user_id,created_at) VALUES(?,?,?,?)"); $s->execute([$a,$id,$uid>0?$uid:null,time()]); } catch(Throwable $e) { error_log('VaultOTP activity log: '.$e->getMessage()); } }
function normalize_code(string $s): string {
    $s = trim($s);
    $normalized = preg_replace('/\s+/u', '', $s);
    return $normalized === null ? $s : $normalized;
}
function parse_codes(string $text): array {
    $out = [];
    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $code = normalize_code((string)$line);
        if ($code === '' || strlen($code) > 500) continue;
        // Prefix the key so PHP never converts numeric recovery codes to integer array keys.
        $out['code:'.$code] = $code;
    }
    return array_values($out);
}
function get_vault(int $id): ?array {$s=db()->prepare("SELECT * FROM vaults WHERE id=?");$s->execute([$id]);return $s->fetch()?:null;}
function code_exists(int $vid,string $needle,string $key): bool {$s=db()->prepare("SELECT ciphertext,nonce FROM codes WHERE vault_id=?");$s->execute([$vid]);foreach($s as $r){try{if(normalize_code(decrypt_code($r['ciphertext'],$r['nonce'],$key))===$needle)return true;}catch(Throwable $e){}}return false;}
function add_codes(int $vid,string $text,?array $file,string $key): int {if($file&&($file['error']??4)===0){if(($file['size']??0)>1048576)throw new RuntimeException('TXT file is larger than 1 MB.');$c=file_get_contents($file['tmp_name']);if($c!==false)$text.="\n".$c;}$n=0;$ins=db()->prepare("INSERT INTO codes(vault_id,ciphertext,nonce,created_at) VALUES(?,?,?,?)");foreach(parse_codes($text) as $c){$c=normalize_code($c);if($c===''||code_exists($vid,$c,$key))continue;[$x,$nonce]=encrypt_code($c,$key);$ins->execute([$vid,$x,$nonce,time()]);$n++;}return $n;}

function platforms(): array { return db()->query("SELECT * FROM platforms WHERE enabled=1 ORDER BY is_default DESC, name")->fetchAll(); }
function removed_default_platforms(): array { return db()->query("SELECT * FROM platforms WHERE is_default=1 AND enabled=0 ORDER BY name")->fetchAll(); }
function get_platform(int $id): ?array { $s=db()->prepare("SELECT * FROM platforms WHERE id=?"); $s->execute([$id]); return $s->fetch() ?: null; }
function platform_by_name(string $name): ?array { $s=db()->prepare("SELECT * FROM platforms WHERE name=?"); $s->execute([$name]); return $s->fetch() ?: null; }
function platform_icon(?string $icon): string { $icon=(string)$icon; return $icon!=='' ? $icon : ''; }
function platform_slug(string $name): string { $slug=strtolower(trim($name)); $slug=preg_replace('/[^a-z0-9]+/','-', $slug) ?? ''; return trim($slug,'-') ?: 'platform'; }
function platform_image(?array $platform, string $class='platform-logo'): string {
    $name=(string)($platform['name']??'Platform'); $icon=(string)($platform['icon']??'');
    $src='';
    if ($icon !== '' && preg_match('/^[a-z0-9._-]+\.png$/i',$icon) && is_file(__DIR__.'/../assets/platforms/'.$icon)) $src='assets/platforms/'.$icon;
    if ($src !== '') return '<img class="'.e($class).'" src="'.e($src).'" alt="'.e($name).'">';
    $initial=e(strtoupper(substr($name,0,1)));
    return '<span class="'.$class.' platform-fallback" aria-label="'.e($name).'">'.$initial.'</span>';
}
function platform_logo_svg(string $name, ?string $icon=null): string {
    $label = $icon !== null && $icon !== '' ? $icon : strtoupper(substr($name, 0, 1));
    $safe = e($label);
    return '<svg class="platform-svg" viewBox="0 0 40 40" aria-hidden="true"><rect x="1" y="1" width="38" height="38" rx="11" fill="currentColor" opacity=".12"/><text x="20" y="26" text-anchor="middle" font-family="Arial,sans-serif" font-size="17" font-weight="800" fill="currentColor">'.$safe.'</text></svg>';
}
function categories(): array { [$scope,$params]=accessible_vault_sql('v'); $q=db()->prepare("SELECT DISTINCT v.category FROM vaults v WHERE v.category<>''{$scope} ORDER BY v.category");$q->execute($params);return $q->fetchAll(PDO::FETCH_COLUMN);}
function derive_backup_key(string $password,string $salt): string {
    if (strlen($salt) !== VAULTOTP_PWHASH_SALT_BYTES) {
        throw new RuntimeException('Invalid backup password salt.');
    }
    return sodium_crypto_pwhash(
        SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        $password,
        $salt,
        VAULTOTP_PWHASH_OPSLIMIT,
        VAULTOTP_PWHASH_MEMLIMIT,
        VAULTOTP_PWHASH_ALG
    );
}

function export_vault_data(string $vaultKey,string $backupPassword): string {
    $backupPassword=(string)$backupPassword; if(strlen($backupPassword)<12) throw new RuntimeException('Backup password must be at least 12 characters.');
    $pdo=db();
    $slots=[];
    foreach($pdo->query('SELECT id,label,ciphertext,nonce,created_at FROM data_key_slots ORDER BY id') as $r){
        $plain=data_key_for_slot((int)$r['id'],$vaultKey);
        $slots[]=['id'=>(int)$r['id'],'label'=>(string)$r['label'],'key'=>base64_encode($plain),'created_at'=>(int)$r['created_at']];
    }
    $vaults=$pdo->query('SELECT id,name,account,category,notes,favorite,created_at,updated_at,data_key_id FROM vaults ORDER BY id')->fetchAll();
    $codes=[];
    $keyCache=[0=>$vaultKey]; foreach($slots as $slot) $keyCache[(int)$slot['id']]=base64_decode($slot['key'],true);
    foreach($slots as $slot)$keyCache[(int)$slot['id']]=base64_decode($slot['key'],true);
    $q=$pdo->query('SELECT c.vault_id,c.ciphertext,c.nonce,c.used,c.created_at,c.used_at,c.used_by,u.username AS used_by_username,v.data_key_id FROM codes c LEFT JOIN users u ON u.id=c.used_by INNER JOIN vaults v ON v.id=c.vault_id ORDER BY c.id');
    foreach($q as $r){$slot=(int)($r['data_key_id']??0);$k=$keyCache[$slot]??$vaultKey;$plain=decrypt_code($r['ciphertext'],$r['nonce'],$k);$codes[]=['vault_id'=>(int)$r['vault_id'],'code'=>$plain,'used'=>(int)$r['used'],'created_at'=>(int)$r['created_at'],'used_at'=>$r['used_at']===null?null:(int)$r['used_at'],'used_by_username'=>$r['used_by_username']===null?null:(string)$r['used_by_username']];}
    $customPlatforms=[]; foreach($pdo->query('SELECT name,icon,enabled FROM platforms WHERE is_default=0 ORDER BY id') as $platform){$item=['name'=>(string)$platform['name'],'enabled'=>(int)$platform['enabled'],'icon'=>null];$icon=(string)($platform['icon']??'');if($icon!==''&&preg_match('/^[a-z0-9._-]+\.png$/i',$icon)){ $path=__DIR__.'/../assets/platforms/'.$icon;if(is_file($path)){$bytes=@file_get_contents($path);if($bytes!==false&&strlen($bytes)<=262144)$item['icon']=base64_encode($bytes);}}$customPlatforms[]=$item;}
    $users=$pdo->query('SELECT id,username,display_name,password_hash,password_salt,key_ciphertext,key_nonce,role,enabled,is_owner,created_at,updated_at,last_login,all_vaults,data_key_id FROM users WHERE is_owner=0 ORDER BY id')->fetchAll();
    foreach($users as &$u){$u['password_salt']=base64_encode((string)$u['password_salt']);$u['key_ciphertext']=$u['key_ciphertext']===null?null:base64_encode((string)$u['key_ciphertext']);$u['key_nonce']=$u['key_nonce']===null?null:base64_encode((string)$u['key_nonce']);}unset($u);
    $userPermissions=$pdo->query('SELECT * FROM user_permissions WHERE user_id IN (SELECT id FROM users WHERE is_owner=0)')->fetchAll();$vaultAccess=$pdo->query('SELECT * FROM vault_access WHERE user_id IN (SELECT id FROM users WHERE is_owner=0) ORDER BY user_id,vault_id')->fetchAll();
    $payload=json_encode(['format'=>'VaultOTP Backup','version'=>7,'created_at'=>time(),'base_key'=>base64_encode($vaultKey),'key_slots'=>$slots,'vaults'=>$vaults,'codes'=>$codes,'custom_platforms'=>$customPlatforms,'users'=>$users,'user_permissions'=>$userPermissions,'vault_access'=>$vaultAccess],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);if($payload===false)throw new RuntimeException('Could not create backup data.');
    $compressed=function_exists('gzcompress')?gzcompress($payload,6):false;$data=$compressed!==false?$compressed:$payload;$salt=random_bytes(VAULTOTP_PWHASH_SALT_BYTES);$backupKey=derive_backup_key($backupPassword,$salt);[$cipher,$nonce]=encrypt_code($data,$backupKey);$outer=['magic'=>'VOTP','version'=>7,'compression'=>$compressed!==false?'zlib':'none','kdf'=>'argon2id','salt'=>base64_encode($salt),'nonce'=>base64_encode($nonce),'data'=>base64_encode($cipher)];$blob=json_encode($outer,JSON_UNESCAPED_SLASHES);if($blob===false)throw new RuntimeException('Could not finalize backup.');return $blob;
}

function import_vault_data(string $blob,string $backupPassword,string $legacyKey): array {
    $outer=json_decode(trim($blob),true);if(!is_array($outer)){$decoded=base64_decode(trim($blob),true);if($decoded!==false)$outer=json_decode($decoded,true);}
    $outerVersion=(int)($outer['version']??0);if(!is_array($outer)||!in_array($outerVersion,[1,2,3,4,5,6,7],true))throw new RuntimeException('Invalid VaultOTP backup file.');$version=$outerVersion;
    $nonce=base64_decode((string)($outer['nonce']??''),true);$cipher=base64_decode((string)($outer['data']??''),true);if($nonce===false||$cipher===false||strlen($nonce)!==SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)throw new RuntimeException('Invalid backup data.');
    $decryptKey=$legacyKey;if($version>=4){if(strlen($backupPassword)<12)throw new RuntimeException('Enter the backup password used when this file was created.');$salt=base64_decode((string)($outer['salt']??''),true);if($salt===false||strlen($salt)!==VAULTOTP_PWHASH_SALT_BYTES)throw new RuntimeException('Invalid backup password data.');$decryptKey=derive_backup_key($backupPassword,$salt);}
    try{$raw=decrypt_code($cipher,$nonce,$decryptKey);}catch(Throwable $e){throw new RuntimeException($version>=4?'Backup password is incorrect, or this backup file is damaged.':'Could not decrypt this older backup.');}
    if(($outer['compression']??'none')==='zlib'){$raw=function_exists('gzuncompress')?@gzuncompress($raw):false;if($raw===false)throw new RuntimeException('This backup uses compression that is unavailable on this server.');}
    $payload=json_decode((string)$raw,true);if(!is_array($payload)||($payload['format']??'')!=='VaultOTP Backup')throw new RuntimeException('This backup is not compatible with VaultOTP.');$payloadVersion=(int)($payload['version']??0);if($payloadVersion>=1&&$payloadVersion<=7)$version=max($version,$payloadVersion);
    $pdo=db();$pdo->beginTransaction();$map=[];$userMap=[];$slotMap=[];$slotKeys=[0=>$legacyKey];$sourceSlotKeys=[];$userSourceSlot=[];$added=0;$platformsAdded=0;$iconFiles=[];$currentKey=$legacyKey;$sourceBaseKey=$legacyKey;
    try{
        if ($version>=7 && !empty($payload['base_key'])) {
            $decodedBase=base64_decode((string)$payload['base_key'],true);
            if ($decodedBase===false || strlen($decodedBase)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('Invalid backup encryption metadata.');
            $sourceBaseKey=$decodedBase;
        }
        // Every imported source key gets a target slot in the current installation.
        // Even the source base key (legacy slot 0) receives a real slot so that
        // imported users can keep their original password-wrapped key while the
        // Owner can unwrap the same key through the current installation key.
        if ($version>=7 && strlen($sourceBaseKey)===SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            $sourceSlotKeys[0]=$sourceBaseKey;
            $slotMap[0]=create_key_slot($sourceBaseKey,$currentKey,'Imported base key');
        }
        // Restore portable encryption-key slots first. The source key is encrypted inside the backup and then wrapped by the current installation key for Owner access.
        if($version>=6){foreach(($payload['key_slots']??[]) as $slot){if(!is_array($slot))continue;$source=base64_decode((string)($slot['key']??''),true);if($source===false||strlen($source)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES)continue;$sourceId=(int)($slot['id']??0);$sourceSlotKeys[$sourceId]=$source;$newSlot=create_key_slot($source,$currentKey,(string)($slot['label']??'Imported key'));$slotMap[$sourceId]=$newSlot;$slotKeys[$newSlot]=$source;}}
        foreach(($payload['custom_platforms']??[]) as $platform){if(!is_array($platform))continue;$name=trim((string)($platform['name']??''));if($name===''||strlen($name)>100)continue;$existing=platform_by_name($name);if($existing&&((int)$existing['is_default']===1))continue;if($existing){if((int)($platform['enabled']??1)===1&&(int)$existing['enabled']===0)$pdo->prepare('UPDATE platforms SET enabled=1 WHERE id=?')->execute([(int)$existing['id']]);continue;}
            $iconFile='';$b64=(string)($platform['icon']??'');if($b64!==''){$bytes=base64_decode($b64,true);if($bytes!==false&&strlen($bytes)<=262144){$tmp=tempnam(sys_get_temp_dir(),'votp_icon_');if($tmp!==false){@file_put_contents($tmp,$bytes);$info=@getimagesize($tmp);if($info&&($info['mime']??'')==='image/png'&&($info[0]??0)<=512&&($info[1]??0)<=512){$iconFile=platform_slug($name).'-'.bin2hex(random_bytes(3)).'.png';$dir=__DIR__.'/../assets/platforms';if(!is_dir($dir))@mkdir($dir,0755,true);if(@copy($tmp,$dir.'/'.$iconFile))$iconFiles[]=$dir.'/'.$iconFile;else $iconFile='';}@unlink($tmp);}}}$pdo->prepare('INSERT INTO platforms(name,icon,is_default,enabled,created_at) VALUES(?,?,0,?,?)')->execute([$name,$iconFile,(int)($platform['enabled']??1)?1:0,time()]);$platformsAdded++;}
        $insV=$pdo->prepare('INSERT INTO vaults(name,account,category,notes,favorite,created_at,updated_at,data_key_id) VALUES(?,?,?,?,?,?,?,?)');
        $vaultSourceSlot=[];
        foreach(($payload['vaults']??[]) as $v){if(!is_array($v))continue;$old=(int)($v['id']??0);$sourceSlot=(int)($v['data_key_id']??0);$targetSlot=$version>=6?($slotMap[$sourceSlot]??0):0;$now=time();$insV->execute([substr((string)($v['name']??'Imported vault'),0,150),substr((string)($v['account']??''),0,255),substr((string)($v['category']??'General'),0,100),(string)($v['notes']??''),(int)($v['favorite']??0)?1:0,(int)($v['created_at']??$now),(int)($v['updated_at']??$now),$targetSlot]);$map[$old]=(int)$pdo->lastInsertId();$vaultSourceSlot[$old]=$sourceSlot;}
        // Restore users before codes so used_by attribution can be mapped. Imported users retain the password/key wrapper that unlocks their source key slot.
        foreach((is_array($payload['users']??null)?$payload['users']:[]) as $u){if(!is_array($u))continue;$username=strtolower(trim((string)($u['username']??'')));if($username===''||$username==='owner'||!preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/',$username))continue;$ex=$pdo->prepare('SELECT id,is_owner FROM users WHERE username=? LIMIT 1');$ex->execute([$username]);$existing=$ex->fetch();if($existing&&((int)$existing['is_owner']===1))continue;$ps=base64_decode((string)($u['password_salt']??''),true);$kc=$u['key_ciphertext']===null?null:base64_decode((string)$u['key_ciphertext'],true);$kn=$u['key_nonce']===null?null:base64_decode((string)$u['key_nonce'],true);if($ps===false||strlen($ps)!==VAULTOTP_PWHASH_SALT_BYTES||$kc===false||$kn===false||strlen($kn)!==SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)continue;$sourceSlot=(int)($u['data_key_id']??0);$targetSlot=$version>=6?($slotMap[$sourceSlot]??0):0;$fields=[substr((string)($u['display_name']??$username),0,100),(string)($u['password_hash']??''),$ps,$kc,$kn,in_array((string)($u['role']??'member'),['viewer','member','manager'],true)?(string)$u['role']:'member',(int)($u['enabled']??1)?1:0,(int)($u['updated_at']??time()),$u['last_login']===null?null:(int)$u['last_login'],$targetSlot];if($existing){$newId=(int)$existing['id'];$pdo->prepare('UPDATE users SET display_name=?,password_hash=?,password_salt=?,key_ciphertext=?,key_nonce=?,role=?,enabled=?,updated_at=?,last_login=?,all_vaults=?,data_key_id=? WHERE id=?')->execute([...array_slice($fields,0,1),$fields[1],$fields[2],$fields[3],$fields[4],$fields[5],$fields[6],$fields[7],$fields[8],(int)($u['all_vaults']??0)?1:0,$fields[9],$newId]);}else{$pdo->prepare('INSERT INTO users(username,display_name,password_hash,password_salt,key_ciphertext,key_nonce,role,enabled,is_owner,created_at,updated_at,last_login,all_vaults,data_key_id) VALUES(?,?,?,?,?,?,?,1,0,?,?,?,?,?)')->execute([$username,...$fields,(int)($u['created_at']??time())]);$newId=(int)$pdo->lastInsertId();}$userMap[(int)($u['id']??0)]=$newId;$userSourceSlot[$newId]=$targetSlot;}
        $cols=['create_vault','manage_platforms','manage_users','manage_settings','manage_backups','view_activity','reveal_codes','copy_codes','add_codes','mark_codes','delete_codes','manage_codes','edit_vaults','delete_vaults'];foreach((is_array($payload['user_permissions']??null)?$payload['user_permissions']:[]) as $perm){if(!is_array($perm))continue;$old=(int)($perm['user_id']??0);if(!$old||!isset($userMap[$old]))continue;$vals=[$userMap[$old]];foreach($cols as $c)$vals[]=(int)($perm[$c]??0)?1:0;$place=implode(',',array_fill(0,count($vals),'?'));$updates=implode(',',array_map(fn($c)=>$c.'=VALUES('.$c.')',$cols));$pdo->prepare('INSERT INTO user_permissions(user_id,'.implode(',',$cols).') VALUES('.$place.') ON DUPLICATE KEY UPDATE '.$updates)->execute($vals);}
        foreach($userMap as $oldUid=>$newUid)$pdo->prepare('DELETE FROM vault_access WHERE user_id=?')->execute([$newUid]);foreach((is_array($payload['vault_access']??null)?$payload['vault_access']:[]) as $access){if(!is_array($access))continue;$ou=(int)($access['user_id']??0);$ov=(int)($access['vault_id']??0);if(!$ou||!$ov||!isset($userMap[$ou],$map[$ov]))continue;$pdo->prepare('INSERT INTO vault_access(user_id,vault_id,can_view,can_reveal,can_copy,can_add,can_mark,can_delete_code,can_manage_codes,can_edit,can_delete) VALUES(?,?,?,?,?,?,?,?,?,?,?)')->execute([$userMap[$ou],$map[$ov],(int)($access['can_view']??1)?1:0,(int)($access['can_reveal']??0)?1:0,(int)($access['can_copy']??0)?1:0,(int)($access['can_add']??0)?1:0,(int)($access['can_mark']??0)?1:0,(int)($access['can_delete_code']??0)?1:0,(int)($access['can_manage_codes']??0)?1:0,(int)($access['can_edit']??0)?1:0,(int)($access['can_delete']??0)?1:0]);}
        // Provision imported vault keys for every restored sub-user. Their
        // account wrapper yields the source account key; each accessible vault
        // key is re-wrapped with that same user key. This is what lets imported
        // users decrypt codes after a backup restore.
        foreach ($userMap as $oldUid => $newUid) {
            $uRow = get_user((int)$newUid);
            if (!$uRow) continue;
            $userSlot = (int)($uRow['data_key_id'] ?? 0);
            $userSessionKey = $userSlot > 0 ? ($sourceSlotKeys[(int)($payload['users'][array_search($oldUid, array_column($payload['users'] ?? [], 'id'))]['data_key_id'] ?? 0)] ?? null) : $sourceBaseKey;
            if (!is_string($userSessionKey) || strlen($userSessionKey)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) continue;
            $allowedVaultIds=[];
            $rawUser=null; foreach (($payload['users']??[]) as $candidate) { if (is_array($candidate) && (int)($candidate['id']??0)===(int)$oldUid) { $rawUser=$candidate; break; } }
            if (!$rawUser) continue;
            $sourceUserSlot=(int)($rawUser['data_key_id']??0);
            $userSessionKey=$sourceSlotKeys[$sourceUserSlot]??($sourceUserSlot===0?$sourceBaseKey:null);
            if (!is_string($userSessionKey) || strlen($userSessionKey)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) continue;
            if ((int)($rawUser['all_vaults']??0)===1) { $allowedVaultIds=array_keys($map); }
            else { foreach (($payload['vault_access']??[]) as $ac) { if (is_array($ac) && (int)($ac['user_id']??0)===(int)$oldUid && (int)($ac['can_view']??1)===1 && isset($map[(int)($ac['vault_id']??0)])) $allowedVaultIds[]=(int)$ac['vault_id']; } }
            foreach (array_unique($allowedVaultIds) as $oldVid) {
                $sourceVaultSlot=(int)($vaultSourceSlot[$oldVid]??0);
                $vaultKey=$sourceSlotKeys[$sourceVaultSlot]??null;
                $targetSlot=$slotMap[$sourceVaultSlot]??0;
                if (!is_string($vaultKey)||strlen($vaultKey)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES||$targetSlot<=0) continue;
                [$uc,$un]=encrypt_code($vaultKey,$userSessionKey);
                $pdo->prepare('INSERT INTO user_data_key_slots(user_id,slot_id,ciphertext,nonce) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE ciphertext=VALUES(ciphertext),nonce=VALUES(nonce)')->execute([$newUid,$targetSlot,$uc,$un]);
            }
        }
        // Imported user key wrappers remain bound to the source key they originally used.
        // Vaults point at the corresponding imported target slot.
        $insC=$pdo->prepare('INSERT INTO codes(vault_id,ciphertext,nonce,used,created_at,used_at,used_by) VALUES(?,?,?,?,?,?,?)');foreach(($payload['codes']??[]) as $c){if(!is_array($c))continue;$old=(int)($c['vault_id']??0);if(!isset($map[$old]))continue;$sourceSlot=$version>=6?(int)($vaultSourceSlot[$old]??0):0;$targetSlot=$version>=6?($slotMap[$sourceSlot]??0):0;$k=$currentKey;if($targetSlot>0)$k=data_key_for_slot($targetSlot,$currentKey);$plain=normalize_code((string)($c['code']??''));if($plain==='')continue;[$enc,$nn]=encrypt_code($plain,$k);$usedBy=null;if(!empty($c['used_by_username'])){$ub=$pdo->prepare('SELECT id FROM users WHERE username=? LIMIT 1');$ub->execute([strtolower((string)$c['used_by_username'])]);$x=$ub->fetchColumn();$usedBy=$x===false?null:(int)$x;}$insC->execute([$map[$old],$enc,$nn,(int)($c['used']??0)?1:0,$c['created_at']??time(),$c['used_at']===null?null:(int)$c['used_at'],$usedBy]);$added++;}
        $pdo->commit();return [count($map),$added,$platformsAdded,count($userMap)];
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();foreach($iconFiles as $f)@unlink($f);throw $e;}
}
