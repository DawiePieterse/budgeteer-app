# Budgeteer

A household budget that fills itself in from the bank: monthly statements and the banks' notification
emails. It runs as a phone-first web app (installable as a PWA) for a two-person household, built with
Laravel 12 for the Afrihost shared hosting that runs Bowls Buddy.

| | |
|---|---|
| Stack | PHP 8.3, Laravel 12, Blade and Alpine.js (no JS build step), MariaDB |
| Banks read | Discovery Bank and Standard Bank (statements and emails) |
| Other sources | Gmail API (read-only), Takealot and Amazon.co.za order emails |
| Jobs | cPanel cron runs `php artisan schedule:run` every 5 minutes |
| Docs | [docs/TECHNOLOGY.md](docs/TECHNOLOGY.md) (stack), [docs/DEPLOY.md](docs/DEPLOY.md) (deploying) |

## What works so far

- **Sign in with Google**, only for addresses on the household's allowlist (`php artisan budgeteer:setup`).
- **Statements:** choose a Standard Bank or Discovery Bank PDF on the phone. It is read in the browser with
  pdf.js and only its text is sent. The server reads every transaction and refuses the statement unless
  each line adds up to the running balance (and, for Standard Bank, the printed totals).
- **Import:** lines already saved from an overlapping statement are skipped, the same statement cannot be
  imported twice, bank fees and cash are categorised automatically, and money moved between your own
  accounts (the credit card repayment, money market transfers) is recognised and paired, not counted as
  spending or income.
- **Categorise by merchant:** everything not yet categorised is grouped by merchant, biggest first; one
  choice categorises the whole group and is remembered for the next statement.
- **Home:** money in, spending by category and account balances for the budget period.
- **Transactions:** search, filter by account, change a category or mark as a transfer.
- **Bank emails:** link the Gmail that receives the bank notifications (read-only, only emails labelled
  `Budgeteer`); every 5 minutes new Discovery Bank emails become transactions with their card and cardholder,
  and a purchase that is both emailed and on a statement is counted once.
- **Cards charged to someone:** set a card (for example Dewan's) to "Charge to …" and everything on it is
  owed to you instead of counted in the budget. Their page shows what they owe (from an opening balance you
  type in), what they bought, repayments, and a WhatsApp reminder; payments into your accounts that mention
  them are offered as repayments with one tap.
- **Budget:** a monthly amount per category, pasted straight from a spreadsheet (one line per item, the
  amount last). The home screen draws each budget line as a meter: the full track is 100% of the budget,
  filled to what has been spent, with the percentage; over budget turns red with a ⚠ and "over". Starter
  categories can be moved into yours, and unused ones removed.
- **Special projects** (for example a car rebuild): payments are kept out of the monthly budget and shown on
  the project's own page with their total, a month-by-month list and an optional project budget. A merchant
  can be sent to a project once from Categorise, and its later payments follow.
- **Recurring payments:** debit orders and other payments expected every week, month or year. Each budget
  month shows each one as paid, amount changed (with "expect the new amount from now on"), due, or late
  (5 days after its date), and a late one can be marked paid elsewhere or skipped. Payments are linked by
  words in their description as statements and emails come in, and payments that already recur are
  suggested. The home screen lists anything late or changed.
- **Colours by whose it is:** on Transactions each row has a coloured stripe: green for the household's
  own budget, and each person who pays back and each special project their own colour (pink, blue,
  orange, aqua, violet, yellow, handed out in turn and changeable on their page), with the name beside it.
- **Budget circles:** the home screen shows the budget as a ring (all budget lines: spent of budget)
  and a circle per line, filled from the bottom with the share used, with what is left under it; over
  budget turns red. **List** switches to the text lines; each phone remembers its choice. Each line has
  an icon, guessed from its name and changeable on the Budget screen.
- **Online orders:** Takealot payment confirmations and Amazon.co.za "Ordered" emails (labelled
  `Budgeteer` by a second Gmail filter) are kept with their items and linked to the card payment with the
  same total. The transaction shows what was bought, who it was delivered to (name only), and a link to
  the order; Transactions search finds items too.
- **Bought for someone else:** on a purchase, **Whose spending → Bought for …** (or **someone new…** with a
  name) takes it out of the budget and adds it to what that person owes. Categorise offers the same for a
  single purchase; a shop with several is opened one by one. Each person's page shows what they owe, a
  WhatsApp reminder and **Paid it all back**; people who are all square drop off the home screen and stay
  listed in Settings.
- **Phone notifications:** each person turns them on per phone in Settings (Android in the browser; iPhone
  after Add to Home Screen) and picks what to hear about: a recurring payment late or its amount changed, a
  budget line at 80% or over (and the whole budget over), bank emails stopped because Gmail needs linking
  again, how the budget month went (from 08:00 the day after it ends), and a new bank statement that should
  be out but is not uploaded (reminded again a week later). Each warning is sent once, between 07:00 and 20:30; several at once come as one notification, and
  tapping it opens the matching screen.
- **Settings:** household name, the day the budget month starts, the names on payments between your own
  accounts, and account names.

## How it fits together

| Where | What |
|---|---|
| `app/Statements` | PDF text readers per bank, with the check that every line adds up to the balance |
| `app/Gmail` | Gmail OAuth, sync and one email parser per bank |
| `app/Transactions` | Importing, classifying, merchant memory and pairing transfers between own accounts |
| `app/Recurring` | Recurring payment schedules, matching and suggestions |
| `app/Orders` | Takealot and Amazon.co.za order parsing and matching to card payments |
| `app/Notify` | Finding warnings and sending web push notifications |
| `app/Services`, `app/Support` | Budget periods and lists, balances owed, icons and colours |
| `resources/views`, `public` | Phone screens, `manifest.webmanifest` and the service worker |
| `tests` | Pest feature and unit tests with made-up statements and emails |

## Commands and schedule

| Command | Purpose | Runs |
|---|---|---|
| `budgeteer:setup` | Create the household and the sign-in allowlist | once |
| `budgeteer:gmail-sync` | Read new labelled bank emails | every 5 minutes |
| `budgeteer:notify` | Send phone notifications, each once | every 15 minutes, 07:00 to 20:30 |
| `budgeteer:push-keys` | Generate the web push keys | once (`--force` replaces them) |
| `budgeteer:keep-from` | Keep data only from a chosen day | on demand |
| `budgeteer:merge-duplicates` | Merge a purchase saved from an email and again from a statement | on demand |

## Running it locally

```sh
composer setup                       # install, .env, key, migrate
php artisan budgeteer:setup --name="My household" --emails=you@gmail.com
php artisan serve
```

Set `BUDGETEER_DEV_LOGIN=true` in `.env` (local only) to sign in without Google. On Claude Code on the web,
`.claude/hooks/session-start.sh` installs MariaDB and does all of this.

## Deploying

See [docs/DEPLOY.md](docs/DEPLOY.md): `scripts/build-afrihost.sh` builds the zip to upload.

## Checks

```sh
composer check     # Pint and Pest (tests need MariaDB or MySQL: budgeteer_test)
composer audit
```

Tests use made-up statements in `tests/Fixtures/statements`. Never commit a real statement.

## Branches

`main` is the default branch and holds the released code. Work on a branch and merge it into `main`; CI
(GitHub Actions: Pint and Pest on PHP 8.3 with MySQL 8) runs on every push and pull request.
