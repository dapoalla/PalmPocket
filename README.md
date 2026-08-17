# PalmPocket

A mobile-first personal budget and expense tracker built with PHP, PDO, and MySQL/MariaDB.

PalmPocket started as a flat-file (JSON) prototype and has since been rebuilt on a proper
relational database with prepared statements throughout, CSRF-protected forms, and a
redesigned, responsive UI with light and dark themes.

## Features

- Expense and inflow tracking by category, purse, and user, with quantity and loan flags
- Purses/wallets with running balances that update automatically as transactions are
  added, edited, or deleted
- Category budgets with progress bars, and a monthly dashboard summary
- Full-text-ish search and filters (type, category, date range, duration) with pagination
- Monthly/weekly reports with a category chart and CSV export (full list or summary)
- Wishlist with manual priority ordering and a purchased/pending split
- Multi-user support with per-user login, password changes, and role labels
- Portable `.sql` backup export/restore, importable via `mysql`/phpMyAdmin too
- Native authenticated SMTP email (no Composer/PHPMailer needed) with a test-email button
- Optional daily cron job for monthly-expense and low-purse-balance threshold alerts
- Mobile-first, responsive UI: bottom nav + floating action button on small screens,
  sidebar on desktop, light/dark theme toggle that persists per browser

## Requirements

- PHP 8.0+ with the `pdo_mysql` extension
- MySQL or MariaDB
- A web server (Apache/XAMPP, nginx, or `php -S` for local development)

## Setup

1. Point your web server at this folder (or run `php -S localhost:8000` from inside it).
2. Open the app in a browser. Since no database is configured yet, you'll land on the
   **setup wizard** automatically.
3. Enter your database host/port/name/user/password. The wizard creates the database
   (if it doesn't exist) and installs the schema for you.
4. Create your first admin account. Default categories and purses are added for you.
5. Log in and go.

Restoring a previous PalmPocket backup? The installer doesn't handle that (it's not part of
ongoing setup) — instead, log in and use **Settings &rarr; Backup &amp; Restore** to upload
the `.sql` file.

Database credentials are written to `includes/config.local.php`, which is git-ignored and
never committed. To reconfigure from scratch, delete that file and revisit the app.

## Project layout

```
index.php            Front controller / router, session + POST handling
setup.php             First-run installer wizard
db/schema.sql          MySQL schema (InnoDB, FKs, indexes)
includes/              db.php, auth.php, repo.php (data access), mailer.php, functions.php
pages/                  One template per page (dashboard, add, edit, transactions, reports, wishlist, settings, login)
assets/css, assets/js  Responsive stylesheet and small progressive-enhancement script
cron_thresholds.php     Daily cron script for threshold email alerts
```

## Cron threshold alerts

Enable "threshold emails" in Settings, fill in your SMTP details, then schedule:

```bash
php /path/to/palmpocket/cron_thresholds.php
```

Once a day is usually enough. It emails you when monthly expenses cross your configured
threshold, or when any purse balance drops at or below your low-balance threshold —
deduplicated per day via the `notifications` table.

## Security notes

- All database access goes through PDO prepared statements.
- Every state-changing form is protected with a per-session CSRF token.
- Passwords are hashed with `password_hash()` / bcrypt.
- `includes/config.local.php` (DB credentials) is git-ignored by design — don't commit it.
