# Putting Budgeteer on Afrihost

Budgeteer runs on the same Afrihost cPanel hosting as Bowls Buddy, at `budget.bowlsbuddy.co.za`. Everything
below is done in cPanel, plus a few commands in cPanel's **Terminal**.

## First time

### 1. Subdomain, PHP and HTTPS

1. **Domains → Create A New Domain**: `budget.bowlsbuddy.co.za`. Untick "Share document root" and set the
   document root to `budgeteer/public`.
2. **MultiPHP Manager**: set `budget.bowlsbuddy.co.za` to **PHP 8.3** (`ea-php83`).
3. **Select PHP Version** (or MultiPHP INI Editor / extensions): make sure `intl`, `sodium`, `zip`, `pdo_mysql`
   and `mbstring` are on.
4. **SSL/TLS Status**: run **AutoSSL** so the subdomain gets a certificate, then under **Domains** switch on
   **Force HTTPS Redirect** for it.

### 2. Database

1. **MySQL Databases → Create New Database**: `budgeteer` (cPanel names it `bowlsbg5n9w0_budgeteer`).
2. **Add New User**: for example `budget`, with a generated password. Keep the password for step 4.
3. **Add User To Database**: the new user, the new database, **All Privileges**.

### 3. Upload the app

1. **File Manager**: in your home folder (not `public_html`), create the folder `budgeteer`.
2. Upload `budgeteer-<commit>.zip` into `budgeteer` and **Extract** it there. You should see `app`, `public`,
   `vendor`, `artisan` and so on directly inside `budgeteer`.

### 4. Settings file (`.env`)

In File Manager, in `budgeteer`, copy `.env.example` to `.env` (turn on "Show hidden files" in Settings), then
**Edit** `.env` and set:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://budget.bowlsbuddy.co.za

LOG_STACK=single
LOG_LEVEL=error

DB_CONNECTION=mariadb
DB_HOST=localhost
DB_DATABASE=bowlsbg5n9w0_budgeteer
DB_USERNAME=bowlsbg5n9w0_budget
DB_PASSWORD=<the database password from step 2>

SESSION_SECURE_COOKIE=true

GOOGLE_CLIENT_ID=<from Google, ends in .apps.googleusercontent.com>
GOOGLE_CLIENT_SECRET=<from Google>

BUDGETEER_HOUSEHOLD_NAME="Pieterse"
BUDGETEER_ALLOWED_EMAILS=dawie.pieterse@gmail.com,annie260264@gmail.com
BUDGETEER_DEV_LOGIN=false
```

Leave `APP_KEY` empty; the next step fills it in. Never put this file in GitHub.

### 5. Finish in Terminal

Open **Terminal** in cPanel and run:

```sh
cd ~/budgeteer
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan key:generate --force
$PHP artisan migrate --force
$PHP artisan budgeteer:setup
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
chmod -R u+rwX storage bootstrap/cache
```

`budgeteer:setup` should answer that both addresses can sign in.

### 6. Scheduled jobs (cron)

**Cron Jobs → Add New Cron Job**, every 5 minutes (`*/5 * * * *`):

```
cd ~/budgeteer && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Nothing is scheduled yet; Gmail sync will use it.

### 7. Check

1. Open `https://budget.bowlsbuddy.co.za/privacy`: the privacy page shows without signing in.
2. Open `https://budget.bowlsbuddy.co.za` and **Sign in with Google**.
3. In **Settings**, set the budget month start day and the names on payments between your own accounts
   (for example `DJ PIETERSE`), then add statements under **Statements**.

## Updating to a new version

1. Upload the new `budgeteer-<commit>.zip` into `~/budgeteer` and **Extract**, overwriting files. `.env` is
   not in the zip, so your settings stay.
2. In Terminal:

```sh
cd ~/budgeteer
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan migrate --force
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache
```

## If something goes wrong

- **Error page with no detail:** look at `~/budgeteer/storage/logs/laravel.log`.
- **"500" straight away after upload:** usually `storage` or `bootstrap/cache` not writable (step 5, last
  line), or `APP_KEY` missing.
- **Google "redirect_uri_mismatch":** `APP_URL` must be exactly `https://budget.bowlsbuddy.co.za`, and the
  redirect URI in Google must be `https://budget.bowlsbuddy.co.za/auth/google/callback`. Run
  `config:cache` again after changing `.env`.
- **"… is not allowed to use Budgeteer":** that address is not in `BUDGETEER_ALLOWED_EMAILS`; add it, then
  run `budgeteer:setup` and `config:cache`.

## Building the zip

`scripts/build-afrihost.sh` builds `build/budgeteer-<commit>.zip` from the last commit, with the production
Composer packages in `vendor/`. It needs PHP 8.2+ and Composer.
