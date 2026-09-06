# VaultOTP

**A self-hosted, web-based recovery-code vault for individuals and small teams.**

VaultOTP lets you securely store and manage account recovery codes on your own hosting and database — without relying on a third-party cloud vault.

> **Author:** ItNexBD
> **Website:** https://itnexbd.com
> **Source / Issues:** https://github.com/NecharUddin/VaultOTP

![Version](https://img.shields.io/badge/version-v--1.0-5458cf?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-Open--Source-brightgreen?style=for-the-badge)

---

## ✨ Overview

VaultOTP is a self-hosted recovery-code management application designed for people and small teams who want to keep control of their sensitive recovery codes.

Instead of storing recovery codes in a third-party cloud service, VaultOTP stores the data on your own server and database.

It supports:

- Secure encrypted recovery-code storage
- Owner and employee/sub-user accounts
- Per-user vault access and permissions
- Recovery-code usage tracking
- Platform management
- Encrypted backups and restoration
- Activity/audit logging
- Auto-lock and session security
- Responsive web interface

---

## 📦 Download

The recommended way to install VaultOTP is to download the latest release package from GitHub Releases.

**Latest release:**
https://github.com/NecharUddin/VaultOTP/releases/latest

The release package contains the files required to deploy VaultOTP on your own hosting.

After downloading the ZIP:

1. Extract the package.
2. Upload the extracted files to your web hosting.
3. Open `setup.php`.
4. Complete the installation.

> The GitHub repository contains the source code. Release ZIP files are provided as convenient deployment packages.

---

## 🚀 Features

### 🔐 Security

- Recovery codes encrypted at rest using **libsodium Secretbox**
- Password-based key protection
- Secure password hashing
- Argon2id support when available
- CSRF protection
- Secure session handling
- HTTP-only session cookies
- `SameSite=Strict` cookies
- Session ID regeneration after authentication
- Login throttling
- Automatic inactivity locking
- PDO prepared statements
- Recovery codes never written to the activity log

### 👤 User Management

- Owner/master account
- Employee/sub-user accounts
- Viewer, Member, and Manager organizational labels
- Per-user vault scope
- Selected-vault access
- All-vault access
- Independent permissions for each user

### 🗄️ Vault Management

- Multiple vaults
- Vault categories
- Favorites
- Search
- Platform association
- Vault editing
- Vault deletion
- Responsive vault interface

### 🔑 Recovery Codes

- Add recovery codes manually
- Import recovery codes from TXT files (one code per line)
- Automatic duplicate removal
- Reveal codes
- Copy codes
- Mark codes as used/unused
- Delete codes
- Pagination
- All / Unused / Used filters
- Latest / Oldest sorting

### 🧩 Platform Management

- Built-in platform library
- Local PNG platform icons
- Custom platforms
- Custom platform icons
- Hide built-in platforms
- Restore built-in platforms

Removing a platform definition does not delete existing vault data associated with it.

### 👥 Team Access

VaultOTP allows the owner to create employee/sub-user accounts without sharing the owner password.

Users can be given access to:

- Specific vaults
- All current and future vaults

Permissions can be controlled independently.

### 💾 Encrypted Backups

VaultOTP supports portable encrypted backups containing protected application data such as:

- Vaults
- Recovery codes
- Platforms
- Users
- Permissions
- Vault access rules
- User key metadata

Backups use the `.votp` format and are protected with a dedicated backup password.

### 📋 Activity Logging

VaultOTP maintains an audit trail of important actions.

The owner can access a dedicated Activities page with:

- Up to 1,000 recent activities
- All-user audit stream
- Per-user filtering
- Latest / Oldest sorting
- Actor information
- Vault information when applicable
- Code action identifiers

Plaintext recovery codes are never stored in the activity log.

---

## 🧰 Requirements

| Requirement | Details |
|---|---|
| PHP | 8.1+ |
| Database | MySQL 5.7+ or compatible MySQL/MariaDB server |
| PHP Extensions | PDO MySQL, libsodium |
| HTTPS | Strongly recommended; required for production use |
| File Uploads | Required for TXT imports, platform icons, and backup files |
| Web Server | Apache or another PHP-compatible web server |

VaultOTP is designed to work well with common shared hosting and cPanel environments.

---

## 🚀 Installation

### 1. Create a Database

Create a MySQL/MariaDB database and database user from your hosting control panel.
Grant the database user the required privileges on the VaultOTP database.

### 2. Upload VaultOTP

Download the latest release ZIP:
https://github.com/NecharUddin/VaultOTP/releases/latest

Extract the package and upload its contents to your website's web root. For example:

```text
public_html/
├── app/
├── assets/
├── setup.php
├── index.php
├── login.php
└── ...
```

### 3. Run the Installer

Open:
```
https://your-domain.com/setup.php
```

Enter:

- Database host
- Database name
- Database username
- Database password
- Owner/master password

> The owner password must be at least 12 characters.

### 4. Complete Setup

The installer creates the required database tables and generates `app/config.local.php`.

Database credentials are not hard-coded into the public application source files.

### 5. Sign In

After setup is complete, sign in using the owner account.

### 6. Enable HTTPS

Before storing real recovery codes, make sure the installation is served over HTTPS.

> `install.sql` is included as a manual database fallback. The recommended installation method is `setup.php`.

---

## 🗄️ Database Schema

VaultOTP uses several tables to manage encrypted data, users, permissions, and key metadata.

| Table | Purpose |
|---|---|
| `settings` | Installation-wide settings and encryption metadata |
| `platforms` | Built-in and custom platform definitions |
| `vaults` | Vault metadata and vault encryption-key references |
| `codes` | Encrypted recovery-code records and usage attribution |
| `activity` | Security and audit events |
| `users` | Owner and employee/sub-user accounts |
| `user_permissions` | Global user permissions |
| `vault_access` | Per-vault access and action permissions |
| `data_key_slots` | Encrypted data-key slots used for protected vault-key management |
| `user_data_key_slots` | Encrypted vault-key wrappers for authorized users |
| `user_key_migrations` | One-time compatibility metadata for applicable restored user keys |

---

## 🔐 Security Model

### Encryption at Rest

Recovery codes are encrypted at rest using libsodium Secretbox.

VaultOTP uses encryption keys that are protected separately from the stored recovery-code ciphertext. Key-slot and user-specific wrappers allow authorized users to access encrypted vault data without sharing the owner's password.

Sensitive encryption keys are not stored as plaintext database values.

### Passwords

User passwords are stored as secure password hashes. VaultOTP prefers Argon2id when available and otherwise uses PHP's secure password hashing facilities.

Plaintext passwords are not exported as part of normal backups.

### Sessions

VaultOTP uses multiple session-security measures, including:

- HTTP-only cookies
- `SameSite=Strict`
- Secure cookie settings when HTTPS is available
- Session ID regeneration after authentication
- Inactivity-based locking

### CSRF Protection

State-changing requests are protected with CSRF tokens. Requests without a valid CSRF token are rejected.

### SQL Injection Protection

Database operations use PDO prepared statements. User-controlled values are not directly interpolated into SQL queries.

### Activity Log Protection

The activity log records actions without storing plaintext recovery codes. Code-related events reference internal identifiers rather than writing the recovery-code secret itself to the log.

---

## 👥 Owner and Sub-Users

The owner is the administrator of the VaultOTP installation. Employee/sub-user accounts can be created without sharing the owner's master password.

### Roles

VaultOTP provides organizational role labels:

- Viewer
- Member
- Manager

These labels are descriptive only. A role label does not automatically grant permissions. Actual access is determined by the user's configured permissions and vault scope.

### Vault Scope

A user can receive:

**Selected Vault Access** — Access to specific vaults chosen by the owner.

**All-Vault Access** — Access to all current and future vaults.

> All-vault access controls scope only. It does not automatically grant actions such as revealing, copying, editing, or deleting data.

---

## 🔑 Permissions

### Global Permissions

Users can be granted permissions such as:

- Create vaults
- Manage platforms
- Manage backups
- View activity information

### Vault and Code Permissions

Users can independently receive permissions to:

- Reveal recovery codes
- Copy recovery codes
- Add recovery codes
- Mark codes as used
- Mark codes as unused
- Delete recovery codes
- Edit vault details
- Delete vaults

The interface hides actions that the user is not allowed to perform. The server also validates permissions independently on every protected request.

---

## ⏱️ Used/Unused Safety Rule

When a sub-user marks a recovery code as used, that same user has a **15-minute window** to undo their own action.

A different employee cannot undo another employee's usage action. The owner retains administrative control over recovery-code status.

---

## 🔑 Recovery Codes

Recovery codes can be imported from TXT files or added through the VaultOTP interface.

TXT imports use one recovery code per line. Duplicate codes are automatically skipped.

Recovery codes can be:

- Filtered by All / Unused / Used
- Sorted by Latest / Oldest
- Revealed
- Copied
- Marked used/unused
- Deleted

The recovery-code list displays 10 codes per page. All actions are subject to the user's permissions.

---

## 🧩 Platforms

The Platforms page provides a built-in platform library with local PNG icons.

Authorized users can:

- Add custom platforms
- Upload custom platform icons
- Hide built-in platforms
- Restore built-in platforms

A platform definition is separate from the vault data associated with it. Therefore, removing or hiding a platform does not automatically delete existing vaults or recovery codes.

---

## 💾 Backups

VaultOTP supports encrypted portable backups using the `.votp` file format.

### Backup Password

Creating or restoring a backup requires a dedicated backup password.

- The backup password must be at least 12 characters.
- The backup password is separate from the normal VaultOTP login/master password.

### Backup Contents

Depending on the installation, a backup can contain protected information including:

- Vault metadata
- Encrypted recovery codes
- Used/unused recovery-code state
- Platform definitions
- Platform icons
- User accounts
- User key metadata
- Global permissions
- Selected-vault permissions
- All-vault access settings
- Encryption key metadata required for portable restoration

VaultOTP's portable backup system is designed to preserve the encryption relationships required for authorized users to continue accessing restored vaults.

### ⚠️ Important Backup Warning

The backup password is **not** the same thing as the VaultOTP login password. Store the backup password securely and separately.

Anyone who obtains a protected backup file and its backup password may be able to restore the protected data. Treat both as sensitive secrets.

---

## 📋 Activity Center

The owner has access to a dedicated Activities page. It provides up to the latest 1,000 activity records and supports:

- All-user activity view
- Individual user filtering
- Latest-first sorting
- Oldest-first sorting
- Actor username/display information
- Vault information where applicable
- Code action identifiers

The activity system is designed to provide accountability without exposing plaintext recovery codes.

---

## 🛡️ Production Security Checklist

Before using VaultOTP with real recovery codes:

- [ ] Enable HTTPS
- [ ] Use a strong and unique owner password
- [ ] Use an updated PHP version
- [ ] Keep MySQL/MariaDB updated
- [ ] Restrict database credentials to the required database
- [ ] Keep encrypted backups
- [ ] Store backup passwords securely
- [ ] Give employees only the permissions they require
- [ ] Review the Activities page regularly
- [ ] Protect `app/config.local.php`
- [ ] Keep the `data/` directory protected
- [ ] Never commit production data to Git
- [ ] Never upload real recovery codes to GitHub
- [ ] Never upload database credentials or server secrets to GitHub
- [ ] Never post recovery codes, passwords, cookies, or backup passwords in public issues

---

## 🐞 Bug Reports and Issues

If you find a bug or unexpected behavior, please open an issue:
https://github.com/NecharUddin/VaultOTP/issues

When reporting a problem, include:

1. VaultOTP version
2. PHP version
3. MySQL/MariaDB version
4. Browser and operating system
5. The page or action where the problem occurred
6. The exact error message
7. Relevant PHP/server error-log information
8. Steps required to reproduce the problem

Before posting logs, remove:

- Recovery codes
- Passwords
- Database credentials
- Session cookies
- Backup passwords
- API keys
- Other private information

**Never post real recovery codes or credentials in a public GitHub issue.**

---

## 🤝 Contributing

Contributions, bug reports, security improvements, documentation improvements, and feature suggestions are welcome.

Before submitting a pull request:

- Keep changes focused.
- Do not include real user data or secrets.
- Do not commit production configuration files.
- Explain what was changed.
- Explain how the change was tested.
- Keep security-sensitive changes clearly documented.

For security-sensitive issues, avoid publicly posting exploitable details or private credentials in an issue.

---

## 📂 Repository Structure

A typical VaultOTP installation contains:

```text
VaultOTP/
├── app/
├── assets/
├── setup.php
├── index.php
├── login.php
├── README.md
├── LICENSE
├── .gitignore
└── ...
```

The exact file structure may change between releases.

---

## 🔒 Sensitive Files

Do not commit or publicly distribute environment-specific or production-sensitive files.

In particular, do not commit:

- `app/config.local.php`
- Real recovery codes
- Production databases
- Backup files
- Database credentials
- API keys
- Private keys
- Session data

The repository should contain source code and example/configuration templates only.

---

## 📜 License

VaultOTP is distributed under the license included in the repository: [`LICENSE`](./LICENSE)

Please read the complete license before redistributing or modifying VaultOTP.

---

## 🏷️ Versioning

The initial public release is **VaultOTP v-1.0**.

Future releases may use versions such as `1.0.1`, `1.1.0`, `2.0.0`. Security fixes and important user-visible changes should be documented in the corresponding GitHub release notes.

---

## 🙌 Credits

VaultOTP is built and maintained by **ItNexBD**.

- Website: https://itnexbd.com
- GitHub: https://github.com/NecharUddin

---

## ⚠️ Disclaimer

VaultOTP is provided as self-hosted software. You are responsible for securing your own hosting environment, database, server, domain, backups, passwords, and deployment.

Always test backups and restoration procedures before relying on VaultOTP for critical recovery information. Never store secrets on a server you do not trust.
