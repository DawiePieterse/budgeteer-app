# Budgeteer

A household budget that fills itself in from the bank: statements and (later) the banks' notification
emails. Built with Laravel 12 for the Afrihost hosting that runs Bowls Buddy. The full plan is in
[docs/TECHNOLOGY.md](docs/TECHNOLOGY.md).

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
- **Bought for someone else:** on a purchase, **Whose spending → Bought for …** (or **someone new…** with a
  name) takes it out of the budget and adds it to what that person owes. Categorise offers the same for a
  single purchase; a shop with several is opened one by one. Each person's page shows what they owe, a
  WhatsApp reminder and **Paid it all back**; people who are all square drop off the home screen and stay
  listed in Settings.
- **Phone notifications:** each person turns them on per phone in Settings (Android in the browser; iPhone
  after Add to Home Screen) and picks what to hear about: a recurring payment late or its amount changed, a
  budget line at 80% or over (and the whole budget over), and bank emails stopped because Gmail needs linking
  again. Each warning is sent once, between 07:00 and 20:30; several at once come as one notification, and
  tapping it opens the matching screen.
- **Settings:** household name, the day the budget month starts, the names on payments between your own
  accounts, and account names.

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
