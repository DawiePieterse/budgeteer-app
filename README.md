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
- **Settings:** household name, the day the budget month starts, the names on payments between your own
  accounts, and account names.

Next: recurring payments, budgets per category, Standard Bank emails.

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
