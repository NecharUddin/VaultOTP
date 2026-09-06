-- VaultOTP database schema. Setup.php creates this automatically; manual import is optional.
CREATE TABLE IF NOT EXISTS settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  password_hash VARCHAR(255) NOT NULL,
  salt VARBINARY(32) NOT NULL,
  auto_lock INT UNSIGNED NOT NULL DEFAULT 900,
  data_nonce VARBINARY(24) NULL,
  data_ciphertext MEDIUMBLOB NULL,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `platforms` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `icon` varchar(32) NOT NULL DEFAULT '◆',
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `platforms` (`name`,`icon`,`created_at`) VALUES
('Google','google.png',UNIX_TIMESTAMP()),
('Facebook','facebook.png',UNIX_TIMESTAMP()),
('Instagram','instagram.png',UNIX_TIMESTAMP()),
('GitHub','github.png',UNIX_TIMESTAMP()),
('Discord','discord.png',UNIX_TIMESTAMP()),
('Microsoft','microsoft.png',UNIX_TIMESTAMP()),
('Apple','apple.png',UNIX_TIMESTAMP()),
('X','x.png',UNIX_TIMESTAMP()),
('Reddit','reddit.png',UNIX_TIMESTAMP()),
('LinkedIn','linkedin.png',UNIX_TIMESTAMP()),
('Steam','steam.png',UNIX_TIMESTAMP()),
('Amazon','amazon.png',UNIX_TIMESTAMP()),
('Dropbox','dropbox.png',UNIX_TIMESTAMP()),
('PayPal','paypal.png',UNIX_TIMESTAMP()),
('Binance','binance.png',UNIX_TIMESTAMP()),
('OpenAI','openai.png',UNIX_TIMESTAMP()),
('Twitch','twitch.png',UNIX_TIMESTAMP()),
('Notion','notion.png',UNIX_TIMESTAMP()),
('Proton','proton.png',UNIX_TIMESTAMP()),
('Cloudflare','cloudflare.png',UNIX_TIMESTAMP());

CREATE TABLE IF NOT EXISTS vaults (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  account VARCHAR(255) NOT NULL DEFAULT '',
  category VARCHAR(100) NOT NULL DEFAULT 'General',
  notes TEXT NULL,
  favorite TINYINT(1) NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  INDEX(category), INDEX(updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  vault_id INT UNSIGNED NOT NULL,
  ciphertext MEDIUMBLOB NOT NULL,
  nonce VARBINARY(24) NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  used_at INT UNSIGNED NULL,
  used_by INT UNSIGNED NULL,
  CONSTRAINT fk_codes_vault FOREIGN KEY(vault_id) REFERENCES vaults(id) ON DELETE CASCADE,
  INDEX(vault_id), INDEX(used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(255) NOT NULL,
  vault_id INT UNSIGNED NULL,
  user_id INT UNSIGNED NULL,
  created_at INT UNSIGNED NOT NULL,
  CONSTRAINT fk_activity_vault FOREIGN KEY(vault_id) REFERENCES vaults(id) ON DELETE SET NULL,
  INDEX(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  password_salt VARBINARY(16) NOT NULL,
  key_ciphertext MEDIUMBLOB NULL,
  key_nonce VARBINARY(24) NULL,
  role VARCHAR(30) NOT NULL DEFAULT 'member',
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  is_owner TINYINT(1) NOT NULL DEFAULT 0,
  created_at INT UNSIGNED NOT NULL,
  updated_at INT UNSIGNED NOT NULL,
  last_login INT UNSIGNED NULL,
  all_vaults TINYINT(1) NOT NULL DEFAULT 0,
  INDEX(enabled), INDEX(is_owner)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_permissions (
  user_id INT UNSIGNED PRIMARY KEY,
  create_vault TINYINT(1) NOT NULL DEFAULT 0,
  manage_platforms TINYINT(1) NOT NULL DEFAULT 0,
  manage_users TINYINT(1) NOT NULL DEFAULT 0,
  manage_settings TINYINT(1) NOT NULL DEFAULT 0,
  manage_backups TINYINT(1) NOT NULL DEFAULT 0,
  view_activity TINYINT(1) NOT NULL DEFAULT 0,
  reveal_codes TINYINT(1) NOT NULL DEFAULT 0,
  copy_codes TINYINT(1) NOT NULL DEFAULT 0,
  add_codes TINYINT(1) NOT NULL DEFAULT 0,
  mark_codes TINYINT(1) NOT NULL DEFAULT 0,
  delete_codes TINYINT(1) NOT NULL DEFAULT 0,
  manage_codes TINYINT(1) NOT NULL DEFAULT 0,
  edit_vaults TINYINT(1) NOT NULL DEFAULT 0,
  delete_vaults TINYINT(1) NOT NULL DEFAULT 0,
  CONSTRAINT fk_permissions_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vault_access (
  user_id INT UNSIGNED NOT NULL,
  vault_id INT UNSIGNED NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 1,
  can_reveal TINYINT(1) NOT NULL DEFAULT 0,
  can_copy TINYINT(1) NOT NULL DEFAULT 0,
  can_add TINYINT(1) NOT NULL DEFAULT 0,
  can_mark TINYINT(1) NOT NULL DEFAULT 0,
  can_delete_code TINYINT(1) NOT NULL DEFAULT 0,
  can_manage_codes TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  can_delete TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY(user_id,vault_id),
  CONSTRAINT fk_access_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_access_vault FOREIGN KEY(vault_id) REFERENCES vaults(id) ON DELETE CASCADE,
  INDEX(vault_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
