
## v-1.0 Public Release

This release fixes portable backup restoration for team accounts. Backups created by VaultOTP v-1.0.4 include the source installation encryption key inside the already-encrypted backup payload. Imported team accounts receive a one-time encrypted key migration and transparently re-wrap the current installation key on their first successful login. This preserves the user's existing password without storing or exporting plaintext passwords.

It also fixes first-run setup version rendering and keeps numeric recovery codes as strings during parsing.
## Previous development hotfix

Fixes portable backup/sub-user vault encryption-key access after restore, including additional encrypted key-slot wrappers for users with multiple restored vault keys.

# VaultOTP v-1.0

VaultOTP is a self-hosted, web-based recovery-code vault for securely storing and managing account recovery codes. It is designed for individuals and small teams that want to keep recovery codes under their own hosting and database instead of relying on a third-party cloud vault.

> **Author:** ItNexBD  
> **Website:** https://itnexbd.com  
> **Source / Issues:** https://github.com/NecharUddin

## Highlights

- Owner account with a protected master password
- Optional employee/sub-user accounts
- Per-user vault scope and independent permissions
- Recovery codes encrypted at rest with libsodium
- Reveal and copy controls
- Used/unused code tracking with a short owner-safe undo window for the user who marked a code used
- 10-code pagination with All / Unused / Used filters and Latest / Oldest sorting
- Built-in platform library with local PNG icons
- Custom platform support
- Favorites, categories, search, and responsive UI
- Auto-lock after inactivity
- Activity/audit logging
- Owner audit center with up to 1,000 recent activities and per-user filtering
- Encrypted, portable backups containing vaults, recovery codes, platforms, users, permissions, and selected-vault access
- CSRF protection, secure sessions, login throttling, and prepared SQL statements
- cPanel/MySQL-friendly deployment

## Requirements

- PHP 8.1+ recommended
- MySQL 5.7+ or MySQL-compatible server
- PDO MySQL extension
- libsodium extension
- HTTPS strongly recommended and required for production use
- PHP file upload support for TXT, PNG, and backup files

## Installation

1. Create a MySQL database and database user in cPanel.
2. Grant the database user full privileges on the database.
3. Upload the contents of the VaultOTP package to your web root.
4. Open `setup.php` in a browser.
5. Enter the database connection details and create an owner/master password of at least 12 characters.
6. Complete setup and sign in.
7. Confirm that HTTPS is enabled before storing real recovery codes.

The installer creates the application tables and writes `app/config.local.php`. Database credentials are not hard-coded into the public application files.

## Database schema

The application uses these main tables:

- `settings` — installation-wide security settings and encrypted data-key metadata
- `platforms` — built-in and custom platform definitions
- `vaults` — vault/service metadata
- `codes` — encrypted recovery-code records and used/unused attribution
- `activity` — security and audit events
- `users` — owner and sub-user accounts
- `user_permissions` — global action permissions
- `vault_access` — per-vault access and action permissions

`install.sql` is provided as a manual database fallback. The normal installation path is `setup.php`.

## Security model

### Encryption at rest

Recovery codes are protected with libsodium Secretbox. The application uses a stable random data-encryption key for vault data and wraps that key for authorized users using password-derived keys. This allows multiple authorized users to access the same encrypted vault without sharing the owner password.

### Passwords

Passwords are stored as password hashes. The application prefers Argon2id when available and falls back to PHP's default password hashing algorithm when necessary.

### Sessions

Sessions use secure cookie attributes where HTTPS is available, HTTP-only cookies, SameSite Strict, session ID regeneration after authentication, and inactivity-based locking.

### CSRF and SQL safety

State-changing forms require CSRF validation. Database operations use PDO prepared statements rather than interpolating user input into SQL.

### Activity log safety

The audit log records what action happened, who performed it, which vault was involved when applicable, and when it happened. Plaintext recovery codes are never written to the activity log. Code-related events use a code ID so an owner can identify the affected record without exposing the secret itself.

## Owner and sub-users

The owner is the administrator of the installation. Employee accounts can be created from **Users** without sharing the master password.

### Roles

The role label (`Viewer`, `Member`, or `Manager`) is an organizational label only. It does **not** grant access. Actual access comes from the explicit permissions.

### Vault scope

A user can receive either:

- Access to selected vaults, or
- **Give access to all vaults**, which includes current and future vaults.

All-vault access is a scope setting only. It never grants actions by itself.

### Independent permissions

Global permissions:

- Create vaults
- Manage platforms
- Manage backups
- View activity log

Vault/code permissions:

- Reveal codes
- Copy codes
- Add recovery codes
- Mark codes used / unused
- Delete recovery codes
- Edit vault details
- Delete vaults

The UI hides actions the user is not allowed to perform, and the server validates the same permissions again.

### Used/unused safety rule

When a sub-user marks a recovery code as used, that same user can undo their own action for a limited 15-minute window. A different employee cannot undo another employee's status change. The owner retains administrative control.

## Recovery codes

Open a vault to see its recovery codes. Codes can be:

- Filtered by **All**, **Unused**, or **Used**
- Sorted **Latest first** or **Oldest first**
- Viewed 10 per page
- Revealed only when the user has permission
- Copied only when the user has permission
- Marked used/unused only when the user has permission
- Deleted only when the user has permission

TXT import accepts one recovery code per line. Duplicate codes are skipped automatically.

## Platforms

The Platforms page contains built-in services with local PNG icons. Owners or users with the platform-management permission can add custom platforms, upload icons, hide built-in platforms, and restore the built-in platform set. Removing a platform definition does not delete existing vault data.

## Backups

VaultOTP backups use an encrypted `.votp` format. Backup creation requires a dedicated backup password of at least 12 characters. The same backup password is required to restore the file.

A backup can contain:

- Vaults and vault metadata
- Recovery codes and used/unused state
- Custom platforms and their icons
- Users and account metadata
- User password/key wrappers
- Global permissions
- Selected-vault permissions
- All-vault scope

The owner account is never blindly overwritten during restore. Existing installations should still be backed up before importing a large or unfamiliar backup.

**Important:** A backup password is separate from the login/master password. Store it securely. Anyone who has both the backup file and its password may be able to restore the protected data.

## Activity center

Owners have a dedicated **Activities** page in the main navigation. It provides:

- Up to the latest 1,000 activity records
- All users in one audit stream
- Filtering by individual user
- Latest-first and oldest-first sorting
- Actor username/display name
- Vault name when applicable
- Code action identifiers for auditability

Sub-users do not receive the owner audit center. If granted the activity permission, they can view their own recent activity.

## Support and issue reporting

If you encounter an error or unexpected behavior, open an issue at:

https://github.com/NecharUddin

When reporting a problem, include:

1. VaultOTP version
2. PHP version
3. MySQL/MariaDB version if known
4. Browser and operating system
5. The page/action where the problem occurred
6. The exact error message
7. Relevant server/PHP error-log details with passwords, recovery codes, database credentials, and other secrets removed
8. Steps to reproduce the issue

Never post recovery codes, passwords, database credentials, session cookies, or backup passwords in a public issue.

## Production checklist

Before using VaultOTP with real recovery codes:

- Enable HTTPS.
- Use a strong unique owner password.
- Keep PHP and MySQL updated.
- Restrict database credentials to the required database.
- Keep regular encrypted backups.
- Store backup passwords separately and securely.
- Give employees only the permissions they need.
- Review the Activities page regularly.
- Do not expose `app/config.local.php` publicly.
- Keep the `data/` directory protected by the included `.htaccess`.
- Never paste secrets into GitHub issues or source control.

## Open-source publishing

VaultOTP v-1.0 is prepared for publication as an open-source project. Before publishing, review the repository for environment-specific configuration, test data, personal recovery codes, generated backups, and server credentials. Do not commit `app/config.local.php`, real backup files, or production data.

## Versioning

The public release line starts at **VaultOTP v-1.0**. Future releases should use semantic-style versioning where practical (for example, `1.0.1`, `1.1.0`, `2.0.0`) and document user-visible or security-relevant changes in the release notes.

## Credits

VaultOTP is built and maintained by **ItNexBD**.

Website: https://itnexbd.com

GitHub: https://github.com/NecharUddin
