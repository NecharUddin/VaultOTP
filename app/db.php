<?php
/**
 * VaultOTP
 * Author: ItNexBD
 * Website: https://itnexbd.com
 * Source: https://github.com/NecharUddin
 */

declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        throw new RuntimeException('Database configuration is missing.');
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    return $pdo;
}

function install_schema(PDO $pdo): void {
    $sql = [
        "CREATE TABLE IF NOT EXISTS settings (id TINYINT UNSIGNED PRIMARY KEY,password_hash VARCHAR(255) NOT NULL,salt VARBINARY(32) NOT NULL,auto_lock INT UNSIGNED NOT NULL DEFAULT 900,created_at INT UNSIGNED NOT NULL,updated_at INT UNSIGNED NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS platforms (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,icon VARCHAR(255) NOT NULL DEFAULT '',is_default TINYINT(1) NOT NULL DEFAULT 0,enabled TINYINT(1) NOT NULL DEFAULT 1,created_at INT UNSIGNED NOT NULL,INDEX(enabled),INDEX(is_default)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS data_key_slots (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(150) NOT NULL DEFAULT 'Imported key',ciphertext MEDIUMBLOB NOT NULL,nonce VARBINARY(24) NOT NULL,created_at INT UNSIGNED NOT NULL,INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS vaults (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(150) NOT NULL,account VARCHAR(255) NOT NULL DEFAULT '',category VARCHAR(100) NOT NULL DEFAULT 'General',notes TEXT NULL,favorite TINYINT(1) NOT NULL DEFAULT 0,created_at INT UNSIGNED NOT NULL,updated_at INT UNSIGNED NOT NULL,data_key_id INT UNSIGNED NOT NULL DEFAULT 0,INDEX(category),INDEX(updated_at),INDEX(data_key_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS codes (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,vault_id INT UNSIGNED NOT NULL,ciphertext MEDIUMBLOB NOT NULL,nonce VARBINARY(24) NOT NULL,used TINYINT(1) NOT NULL DEFAULT 0,created_at INT UNSIGNED NOT NULL,used_at INT UNSIGNED NULL,used_by INT UNSIGNED NULL,CONSTRAINT fk_codes_vault FOREIGN KEY(vault_id) REFERENCES vaults(id) ON DELETE CASCADE,INDEX(vault_id),INDEX(used)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS activity (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,action VARCHAR(255) NOT NULL,vault_id INT UNSIGNED NULL,user_id INT UNSIGNED NULL,created_at INT UNSIGNED NOT NULL,CONSTRAINT fk_activity_vault FOREIGN KEY(vault_id) REFERENCES vaults(id) ON DELETE SET NULL,INDEX(created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS users (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(60) NOT NULL UNIQUE,display_name VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL,password_salt VARBINARY(16) NOT NULL,key_ciphertext MEDIUMBLOB NULL,key_nonce VARBINARY(24) NULL,role VARCHAR(30) NOT NULL DEFAULT 'member',enabled TINYINT(1) NOT NULL DEFAULT 1,is_owner TINYINT(1) NOT NULL DEFAULT 0,created_at INT UNSIGNED NOT NULL,updated_at INT UNSIGNED NOT NULL,last_login INT UNSIGNED NULL,all_vaults TINYINT(1) NOT NULL DEFAULT 0,data_key_id INT UNSIGNED NOT NULL DEFAULT 0,INDEX(enabled),INDEX(is_owner)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_permissions (user_id INT UNSIGNED PRIMARY KEY,create_vault TINYINT(1) NOT NULL DEFAULT 0,manage_platforms TINYINT(1) NOT NULL DEFAULT 0,manage_users TINYINT(1) NOT NULL DEFAULT 0,manage_settings TINYINT(1) NOT NULL DEFAULT 0,manage_backups TINYINT(1) NOT NULL DEFAULT 0,view_activity TINYINT(1) NOT NULL DEFAULT 0,reveal_codes TINYINT(1) NOT NULL DEFAULT 0,copy_codes TINYINT(1) NOT NULL DEFAULT 0,manage_codes TINYINT(1) NOT NULL DEFAULT 0,edit_vaults TINYINT(1) NOT NULL DEFAULT 0,delete_vaults TINYINT(1) NOT NULL DEFAULT 0,CONSTRAINT fk_permissions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS vault_access (user_id INT UNSIGNED NOT NULL,vault_id INT UNSIGNED NOT NULL,can_view TINYINT(1) NOT NULL DEFAULT 1,can_reveal TINYINT(1) NOT NULL DEFAULT 0,can_copy TINYINT(1) NOT NULL DEFAULT 0,can_add TINYINT(1) NOT NULL DEFAULT 0,can_mark TINYINT(1) NOT NULL DEFAULT 0,can_delete_code TINYINT(1) NOT NULL DEFAULT 0,can_manage_codes TINYINT(1) NOT NULL DEFAULT 0,can_edit TINYINT(1) NOT NULL DEFAULT 0,can_delete TINYINT(1) NOT NULL DEFAULT 0,PRIMARY KEY(user_id,vault_id),CONSTRAINT fk_access_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_access_vault FOREIGN KEY(vault_id) REFERENCES vaults(id) ON DELETE CASCADE,INDEX(vault_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_data_key_slots (user_id INT UNSIGNED NOT NULL,slot_id INT UNSIGNED NOT NULL,ciphertext MEDIUMBLOB NOT NULL,nonce VARBINARY(24) NOT NULL,PRIMARY KEY(user_id,slot_id),CONSTRAINT fk_udks_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,CONSTRAINT fk_udks_slot FOREIGN KEY(slot_id) REFERENCES data_key_slots(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS user_key_migrations (user_id INT UNSIGNED PRIMARY KEY,ciphertext MEDIUMBLOB NOT NULL,nonce VARBINARY(24) NOT NULL,created_at INT UNSIGNED NOT NULL,CONSTRAINT fk_ukm_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];
    foreach ($sql as $query) $pdo->exec($query);
    try { $pdo->exec("ALTER TABLE settings ADD COLUMN auto_lock INT UNSIGNED NOT NULL DEFAULT 900 AFTER salt"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE settings ADD COLUMN data_nonce VARBINARY(24) NULL AFTER auto_lock"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE settings ADD COLUMN data_ciphertext MEDIUMBLOB NULL AFTER data_nonce"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE codes ADD COLUMN used_by INT UNSIGNED NULL AFTER used_at"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE codes ADD INDEX(used_by)"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE activity ADD COLUMN user_id INT UNSIGNED NULL AFTER vault_id"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE activity ADD INDEX(user_id)"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN key_ciphertext MEDIUMBLOB NULL AFTER password_salt"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN key_nonce VARBINARY(24) NULL AFTER key_ciphertext"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN all_vaults TINYINT(1) NOT NULL DEFAULT 0 AFTER last_login"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE users ADD COLUMN data_key_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER all_vaults"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE vaults ADD COLUMN data_key_id INT UNSIGNED NOT NULL DEFAULT 0 AFTER updated_at"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE vaults ADD INDEX(data_key_id)"); } catch (PDOException $e) { }
    foreach ([
        'reveal_codes TINYINT(1) NOT NULL DEFAULT 0',
        'copy_codes TINYINT(1) NOT NULL DEFAULT 0',
        'manage_codes TINYINT(1) NOT NULL DEFAULT 0',
        'add_codes TINYINT(1) NOT NULL DEFAULT 0',
        'mark_codes TINYINT(1) NOT NULL DEFAULT 0',
        'delete_codes TINYINT(1) NOT NULL DEFAULT 0',
        'edit_vaults TINYINT(1) NOT NULL DEFAULT 0',
        'delete_vaults TINYINT(1) NOT NULL DEFAULT 0'
    ] as $col) { try { $pdo->exec("ALTER TABLE user_permissions ADD COLUMN $col"); } catch (PDOException $e) { } }
    // Migrate older platform tables without interrupting an existing installation.
    try { $pdo->exec("ALTER TABLE platforms ADD COLUMN icon VARCHAR(255) NOT NULL DEFAULT '' AFTER name"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE platforms ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER icon"); } catch (PDOException $e) { }
    try { $pdo->exec("ALTER TABLE platforms ADD COLUMN enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER is_default"); } catch (PDOException $e) { }
    $platforms = [
        ['Google','google.png'], ['Facebook','facebook.png'], ['Instagram','instagram.png'], ['GitHub','github.png'],
        ['Discord','discord.png'], ['Microsoft','microsoft.png'], ['Apple','apple.png'], ['X','x.png'],
        ['Reddit','reddit.png'], ['LinkedIn','linkedin.png'], ['Steam','steam.png'], ['Amazon','amazon.png'],
        ['Dropbox','dropbox.png'], ['PayPal','paypal.png'], ['Binance','binance.png'], ['OpenAI','openai.png'],
        ['Twitch','twitch.png'], ['Notion','notion.png'], ['Proton','proton.png'], ['Cloudflare','cloudflare.png']
    ];
    $insert = $pdo->prepare("INSERT IGNORE INTO platforms(name,icon,is_default,enabled,created_at) VALUES(?,?,?,?,?)");
    $now = time();
    foreach ($platforms as [$name,$icon]) $insert->execute([$name,$icon,1,1,$now]);
    $fix = $pdo->prepare("UPDATE platforms SET icon=?, is_default=1 WHERE name=?");
    foreach ($platforms as [$name,$icon]) $fix->execute([$icon,$name]);
    // Keep selected-vault action permissions in sync with the canonical user permissions.
    // This repairs v27 accounts created before selected-vault permissions were wired correctly.
    try {
        $pdo->exec("UPDATE vault_access va INNER JOIN user_permissions up ON up.user_id=va.user_id SET va.can_reveal=up.reveal_codes, va.can_copy=up.copy_codes, va.can_add=up.add_codes, va.can_mark=up.mark_codes, va.can_delete_code=up.delete_codes, va.can_manage_codes=(up.add_codes OR up.mark_codes OR up.delete_codes), va.can_edit=up.edit_vaults, va.can_delete=up.delete_vaults WHERE va.user_id IN (SELECT id FROM users WHERE is_owner=0 AND all_vaults=0)");
    } catch (PDOException $e) { }
}

function init_db(): void { install_schema(db()); }
function is_installed(): bool {
    try { return (int)db()->query("SELECT COUNT(*) FROM settings WHERE id=1")->fetchColumn() > 0; }
    catch (Throwable $e) { return false; }
}
