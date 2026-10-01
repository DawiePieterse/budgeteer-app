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

It reads new bank emails from every linked Gmail account.

### 7. Check

1. Open `https://budget.bowlsbuddy.co.za/privacy`: the privacy page shows without signing in.
2. Open `https://budget.bowlsbuddy.co.za` and **Sign in with Google**.
3. In **More › Household**, set the budget month start day and the names on payments between your own accounts
   (for example `DJ PIETERSE`), then add statements under **Statements**.

## Reading bank emails (Gmail)

In the **Google Cloud console**, project **Budgeteer**:

1. **APIs & Services → Library**: search **Gmail API** and click **Enable**.
2. **Google Auth Platform → Clients → Budgeteer web**: under **Authorised redirect URIs** add
   `https://budget.bowlsbuddy.co.za/gmail/callback` (keep the sign-in one) and save.
3. **Google Auth Platform → Audience**: the app must be **In production**. In Testing, Google withdraws Gmail
   access after 7 days.

Do **not** add the Gmail scope under **Data access**. That asks Google to verify the app, which for Gmail
means a paid security assessment. Budgeteer asks for the scope itself when Gmail is linked; an unverified app
may do that for up to 100 users, who each click through Google's "unverified app" warning once.

In **Gmail** (the account the banks email), create the filter:

1. Search `from:(discovery OR standardbank) subject:("Transaction update" OR MyUpdates)`, open the search options, **Create filter**.
2. Tick **Apply the label**, **New label…** `Budgeteer`, tick **Also apply filter to matching conversations**,
   **Create filter**.

For online orders, make a second filter the same way: search `from:(info@takealot.com OR auto-confirm@amazon.co.za)`,
the same `Budgeteer` label, and **Also apply filter to matching conversations**. Then read the older ones once:
`php artisan budgeteer:gmail-sync --since=2026-07-01`.

In **Budgeteer**: More › Bank emails → **Link Gmail**. Google warns that the app is not verified: click **Advanced**,
then **Go to budget.bowlsbuddy.co.za**, then tick **View your email messages and settings** and **Continue**.
The first 30 days of labelled emails are read straight away; after that the cron job checks every 5 minutes.

## Phone notifications

Once, in Terminal (it writes two keys into `.env`; nothing to copy anywhere):

```sh
cd ~/budgeteer
PHP=/opt/cpanel/ea-php83/root/usr/bin/php
$PHP artisan budgeteer:push-keys && $PHP artisan config:cache
```

Then each person, on each phone: Budgeteer → More › Phone notifications → **Turn on** → allow, then
**Send a test notification**.

- **Android** (Chrome or Samsung Internet): works straight from the browser.
- **iPhone** (iOS 16.4 or newer): open Budgeteer in Safari, **Share → Add to Home Screen**, open it from the
  new icon, then turn notifications on in More › Phone notifications there.

The cron job checks every 15 minutes between 07:00 and 20:30 (`budgeteer:notify`) and sends each warning once:
a recurring payment late or its amount changed, a budget line at 80% or over, the whole budget over, bank
emails stopped because Gmail needs linking again, how the budget month went (from 08:00 on the first day of
the next), and a statement to upload (3 days after the next one should end, again a week later). Several at once arrive as one notification. Running
`budgeteer:push-keys --force` makes new keys, after which every phone must turn notifications on again.

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

## Keeping data only from a date

```sh
$PHP artisan budgeteer:keep-from 2026-07-01
```

Shows how many transactions and repayments are dated before that day, and deletes them after you confirm.
From then on, statement lines and bank emails dated before it are skipped.

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
