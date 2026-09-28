# Budgeteer technology

What Budgeteer is built with, where it runs and how it is checked. It follows the same approach as Bowls Buddy
(`DawiePieterse/bowlsbuddy-app`, `docs/TECHNOLOGY.md`): Laravel on the existing Afrihost shared hosting, no
Node.js, Docker or always-on workers.

---

## 1. At a glance

| Layer | Technology |
|---|---|
| Language | PHP 8.3 in production (8.2 or newer supported) |
| Framework | Laravel 12 |
| Settings panel | Filament 4 (on Livewire 3 and Alpine.js) |
| Phone screens | Blade templates, Alpine.js and a small hand-written stylesheet; no JavaScript build step |
| PWA | Hand-written `manifest.webmanifest` and `sw.js` in `public/`; installable on iPhone and Android |
| Database | MariaDB 10.11 in production; MySQL 8 in CI; MariaDB for local and cloud development |
| Hosting | Afrihost Bronze Pro shared cPanel hosting (the Bowls Buddy package), LiteSpeed web server |
| Domain | `budget.bowlsbuddy.co.za` |
| Email source | Gmail API, read-only; one linked Gmail account (the one the banks email) feeds the whole household; any number of banks |
| Code | GitHub (`DawiePieterse/budgeteer-app`), GitHub Actions for CI |
| Tests | Pest (unit and feature), Playwright (phone-width browser flows) |
| Quality | Laravel Pint (code style), Larastan / PHPStan level 6 (static analysis), `composer audit` |

Why this stack: the Afrihost package is already paid for and runs Bowls Buddy, so Budgeteer costs nothing
extra to host. Everything the app needs (reading Gmail, parsing, categorising, reminders) fits in short
scheduled jobs run by cPanel cron, so no background process has to stay alive.

---

## 2. Application

### Framework and libraries

| Package | Version | Used for |
|---|---|---|
| `laravel/framework` | 12 | Routing, Eloquent, validation, CSRF, auth, encryption, rate limiting, migrations, scheduler, database queue |
| `laravel/socialite` | 5 | Google sign-in, and linking Gmail with the `gmail.readonly` scope and offline access |
| `filament/filament` | 4 | Settings at `/admin`: categories, budgets, rules, people, email sources, household members |
| `livewire/livewire` | 3 | Interactive Filament pages (comes with Filament) |
| `laravel-notification-channels/webpush` | 13 | Web push to the installed PWA (over budget, items to review, money owed) |
| `anthropic-ai/sdk` (PHP) | 0.51 | Optional fallback: reads a bank email no parser recognises (section 5) |
| `symfony/dom-crawler` | 7 or 8 | Reading values out of HTML bank emails |

Gmail is called through Laravel's HTTP client against the Gmail REST API, not `google/apiclient`, which is
large and mostly unused here.

### Structure

| Where | What |
|---|---|
| `app/Models` | `Household`, `User`, `GmailConnection`, `IngestedEmail`, `Account`, `Transaction`, `TransactionSplit`, `Category`, `Budget`, `Rule`, `Merchant`, `Person`, `Receivable`, `Settlement`, `RecurringPayment`, `RecurringOccurrence` |
| `app/Services/Gmail` | OAuth tokens, `history.list` sync, message fetch |
| `app/Parsers` | One parser per bank or card sender (`DiscoveryBankParser`, `StandardBankParser`), plus `ClaudeParser` as the fallback |
| `app/Services` | Merchant clean-up, categorising, learning, budget periods, recurring payments, reimbursements, payment matching |
| `app/Filament` | Settings pages and resources |
| `app/Http/Controllers` | Phone screens: home, review inbox, transactions, recurring payments, owed to me |
| `resources/views` | Blade views for phone screens and Filament pages |
| `public/sw.js`, `public/manifest.webmanifest` | Offline shell and install metadata |

### Conventions that shape the system

- **Money** is stored as integer cents with a currency code (`ZAR` by default). No floats.
- **Times** are local wall-clock times in `Africa/Johannesburg` (UTC+2 all year, no daylight saving).
- **Budget periods** run from a set day of the month (for example payday on the 25th), set per household.
- **Sign-in** is Google only, limited to the email addresses on the household's allowlist.
- **Email bodies are not kept.** Once parsed, only the Gmail message ID and the extracted fields remain.
- **Security:** CSRF on every form, deletes by POST only, rate-limited routes, secure and encrypted session
  cookies bound to `budget.bowlsbuddy.co.za` only (no `SESSION_DOMAIN`), security headers and a strict CSP on
  every response, Gmail tokens encrypted with Laravel's `encrypted` cast.
- **Privacy (POPIA):** data is stored in South Africa (Afrihost); the Gmail owner can unlink Gmail at any
  time, and the household's data can be downloaded or deleted.

---

## 3. How a transaction gets in

1. **Gmail filter.** The bank emails arrive in one person's Gmail. That person adds a Gmail filter that labels
   bank and card notifications `Budgeteer`, and links that Gmail once. The app only lists messages with that
   label.
2. **Sync (every 5 minutes).** A scheduled job calls Gmail `history.list` from the last stored history ID for
   the linked account and queues new message IDs. The first link does a full `messages.list` of the label.
3. **Parse.** Each message is fetched, matched to a parser by sender, and turned into amount, date, merchant,
   card number ending, and type (purchase, refund, payment received). No match goes to the Claude fallback if
   it is on, otherwise to the review inbox as "could not read".
4. **De-duplicate.** Unique on Gmail message ID, plus a hash of amount, date, merchant and card to catch the
   same purchase notified twice.
5. **Match recurring payments.** A debit order or other payment that matches an expected recurring payment
   (section 7) is linked to it and takes its category, skipping step 6.
6. **Categorise** (section 4), then either assign or send to the review inbox.
7. **Match payments.** An incoming payment is offered as the settlement for open receivables of the same
   amount (section 6).

Queued work uses Laravel's `database` queue, drained by the scheduler with
`queue:work --stop-when-empty --max-time=50`, so nothing runs longer than one cron cycle.

### Banks

The household starts with two banks, both emailing the same Gmail:

| Bank | Account | Parser | Notifications to read |
|---|---|---|---|
| Discovery Bank | Credit card (main and any secondary cards) | `DiscoveryBankParser` | Card purchases, refunds, declined purchases (ignored), repayments received |
| Discovery Bank | Dewan's credit card (outside the budget, see "A card kept for someone else" in section 6) | `DiscoveryBankParser` | As above |
| Standard Bank | Cheque account | `StandardBankParser` | Card purchases, debit orders, EFTs and transfers out, payments in (salary, reimbursements) |

**Transfers between your own accounts** do not count as spending. The monthly repayment of the Discovery
credit card from the Standard Bank cheque account shows up twice: as a payment out at Standard Bank and a
payment received at Discovery Bank. The two are matched on amount and date (within 3 days) and marked as a
transfer, which leaves the budget untouched; the purchases on the card are what count. A repayment that
cannot be matched goes to the review inbox.

Every bank is handled the same way, so adding a third later is a code change of a known size:

1. Add its sender addresses to the `Budgeteer` Gmail filter.
2. Add a parser in `app/Parsers` and register its senders in `email_sources`.
3. Add redacted sample emails for every notification type the bank sends (purchase, refund, payment received,
   debit order, transfer) to `tests/Fixtures/emails/<bank>` with a Pest test for each.
4. Cards and accounts at the new bank are created the first time a parsed email names a new number ending,
   and are confirmed in the review inbox, with who uses each card (see "Who spent it" in section 6).

Banks do not all email every kind of transaction; some send most alerts only by SMS or app notification.
Before writing a parser, switch on email notifications in the Discovery Bank app and in Standard Bank's
notification settings (where offered), then check a month of each bank's emails to see what arrives. Anything a bank does not email is
covered by recurring payments marked paid by hand (section 7) or by manual entry.

---

## 4. Categorising and learning

Each step runs only if the one before did not decide:

| Step | How |
|---|---|
| 1. Rules | The household's explicit rules, for example "merchant contains WOOLWORTHS → Groceries" |
| 2. Merchant memory | The merchant name is cleaned (`POS*`, branch codes, card numbers stripped) and looked up in `merchants`; the last category chosen for it wins |
| 3. Word model | A naive Bayes model over merchant-name words and amount band, trained from confirmed transactions and stored in MariaDB (`category_tokens`) |
| 4. Claude (optional) | Chooses from the household's category list |

- Confidence 0.85 or more is assigned automatically; anything lower goes to the review inbox.
- Every confirmation or correction updates merchant memory and the word model, and offers to create a rule.
- The same steps suggest "bought for someone else" for merchants often marked that way.

---

## 5. Claude fallback (optional)

- Off until switched on in settings. Without it, unknown emails and unsure categories go to the review inbox.
- When on, only the text of an email no parser recognised is sent, with the Anthropic API key held in `.env`.
  Structured output returns the same fields a parser would.
- Every result it produces goes to the review inbox the first time, and a confirmed result can be saved as a
  new parser template for that sender.

---

## 6. Sharing and reimbursements

- **Household:** two users who see exactly the same data: every transaction, budget, recurring payment and
  receivable. Only one of them links Gmail, because that is where the bank emails arrive; the other signs in
  with Google and needs no Gmail link. There are no private transactions.
- **Both can act:** either person can review, categorise, split and confirm. A change made by one shows for the
  other on the next page load, and each change records who made it.
- **Who spent it:** each card is assigned to the person who uses it (for example a secondary card in the
  other person's name on the same account), so transactions show whose spending they were even though all
  the emails come to one mailbox.
- **Bought for someone else:** a transaction can be split, fully or partly, to a person. That part goes to
  `receivables` and does not count against the budget.
- **Owed to me:** totals per person and age of each item; a one-tap WhatsApp request (`wa.me` link), as in
  Bowls Buddy.
- **Settling:** a "payment received" email of the same amount is offered as the match; confirming it closes the
  receivable.

### A card kept for someone else

Dewan has his own Discovery credit card, and its notifications come to the same Gmail. The household pays
his card from the Standard Bank cheque account and Dewan pays the household back. His spending must not
count against the household budget, but what he owes has to be visible on its own.

- **Card setting "Charge to a person".** Dewan's card, recognised by its number ending, is set to charge
  to the person Dewan. Every transaction on it goes straight to his receivables, in full, and never
  reaches the budget or the review inbox. A category is still guessed and can be corrected, so his
  spending can be broken down, but it is shown only on his page.
- **Refunds** on his card reduce what he owes.
- **Repayments of his card** from the Standard Bank cheque account are transfers (section 3), so they do
  not count as household spending either; paying his card does not clear what he owes you.
- **Money from Dewan** (an EFT into the cheque account, recognised by his name or reference) is offered as
  a settlement against his balance; confirming it reduces what he owes, oldest items first.
- **His page** shows his current balance, this month's spending on his card by category, and the list of
  transactions and settlements, with the same WhatsApp request as any other person. Both of you see it.
- **Home screen and budgets** leave his card out of every total. His balance appears only as a single line
  in "Owed to me".

---

## 7. Recurring payments

Payments that happen every month (medical aid, insurance, bond or rent, school fees, cellphone contracts,
subscriptions) are set up once, so the budget knows about them before they go off.

| Field | Example |
|---|---|
| Name and category | Medical aid, Medical |
| Match text | Payee or debit order reference as it appears in the bank email, for example `DISCOVERY HEALTH` |
| Expected amount and tolerance | R4,500, within R50 or 2% |
| Day of the month | 1st (matched from 3 days before to 5 days after) |
| Account | The account it is paid from; optional |
| Frequency | Monthly, or yearly for things like a car licence |

- **Each budget period** creates an expected occurrence for every active recurring payment.
- **Matched:** a transaction with the match text, within the tolerance and the date window, is linked to the
  occurrence, marked paid and given its category. No review is needed.
- **Amount changed:** a match outside the tolerance (for example the January medical aid increase) goes to the
  review inbox with "Expected R4,500, was R4,850. Update the amount?".
- **Not seen:** an occurrence still open 5 days after its date goes to the review inbox. It can be marked
  paid by hand (for a bank that does not email debit orders), which creates the transaction, or marked
  skipped for that month.
- **Suggestions:** a cleaned merchant paid at a similar amount in each of the last three periods is offered
  as a new recurring payment.
- **Budget view:** the home screen shows what is already paid, what recurring payments are still due this
  period, and what is left to spend after both.

---

## 8. Database

One database, `bowlsbg5n9w0_budgeteer`, created by Laravel migrations. Every table except Laravel's own has a
`household_id`, and every query goes through a global scope on it.

| Tables | What they hold |
|---|---|
| `households`, `users` | The household, its members and their allowlisted Google emails |
| `gmail_connections` | Encrypted refresh token, last history ID, last sync and status for the linked Gmail account |
| `email_sources` | Known senders and the parser each uses |
| `ingested_emails` | Gmail message ID, sender, received time, parse status, error |
| `accounts` | Cards and bank accounts, identified by the number ending, with the person who uses each |
| `transactions`, `transaction_splits` | Transactions, with their source (email or entered by hand), and their parts (category, or person for a receivable) |
| `recurring_payments`, `recurring_occurrences` | Expected monthly or yearly payments, and each period's occurrence with its status (due, paid, not seen, skipped) and linked transaction |
| `categories`, `budgets`, `budget_periods` | Category tree with icons, amount per category per period |
| `rules`, `merchants`, `category_tokens` | Explicit rules, merchant memory, the word model |
| `people`, `receivables`, `settlements` | People who owe money, what they owe, how it was paid; `accounts` can name a person to charge every transaction to |
| `push_subscriptions` | Web push endpoints per device |
| `bg_sessions`, `bg_cache`, `bg_cache_locks`, `bg_jobs`, `bg_failed_jobs`, `bg_migrations` | Laravel's own tables |

- Character set `utf8mb4` everywhere.
- Money columns are `BIGINT` cents.

| Environment | Database |
|---|---|
| Production (Afrihost) | MariaDB 10.11, `localhost`, `bowlsbg5n9w0_budgeteer` (one of the package's 20 databases) |
| CI (GitHub Actions) | MySQL 8.0 service container |
| Development and tests | MariaDB: `budgeteer` (dev), `budgeteer_test` (Pest), `budgeteer_e2e` (Playwright) |

---

## 9. Hosting: Afrihost

| What | Value |
|---|---|
| Package | The existing Afrihost Bronze Pro Linux Hosting that runs Bowls Buddy |
| Subdomain | `budget.bowlsbuddy.co.za`, document root `budgeteer/public` |
| PHP | 8.3 (`ea-php83`) in MultiPHP Manager, with `intl`, `zip`, `sodium` and `gmp` (web push) |
| HTTPS | Free AutoSSL certificate for the subdomain; Force HTTPS Redirect on |
| Cron | cPanel Cron Jobs: `*/5 * * * * cd ~/budgeteer && php artisan schedule:run >> /dev/null 2>&1` |
| Backups | Afrires, 14 days (restore by support ticket), plus a nightly scheduled SQL dump outside `public/` |

**Production settings:** sessions and cache in the database, queue `database` drained by the scheduler, logs to
a single file at level `error`, debug off.

**Scheduled jobs:**

| Job | Every |
|---|---|
| Gmail sync and parse | 5 minutes |
| Queue drain | 5 minutes (with the scheduler) |
| Budget alerts and review reminders (web push) | Hourly |
| Create recurring occurrences for a new period, flag ones not seen | Daily |
| Refresh a Gmail link that failed, and notify both people | Daily |
| SQL backup | Nightly |

**Deploying:** `scripts/build-afrihost.sh` builds a ready-to-upload zip with `vendor/` included, as in Bowls
Buddy. Upload and extract it in the cPanel File Manager, then run `php artisan migrate` over SSH or cPanel
Terminal.

**Shared account risk:** Budgeteer and the Bowls Buddy clubs run as the same cPanel user, so a flaw in any one
of them could read the others' files, including Budgeteer's `.env`. Keeping Bowls Buddy patched protects
Budgeteer too. A separate Afrihost hosting account removes this link if it becomes a concern.

---

## 10. Google setup

| What | Value |
|---|---|
| Project | One Google Cloud project, OAuth consent screen "External", publishing status "In production", not verified |
| Scopes | `openid`, `email`, `profile`; `gmail.readonly` only when linking Gmail |
| Authorised domain | `bowlsbuddy.co.za` |
| Redirect URIs | `https://budget.bowlsbuddy.co.za/auth/google/callback`, `https://budget.bowlsbuddy.co.za/gmail/callback` |
| Users | Under 100, so the unverified-app warning is clicked through once per person |

"In production" is needed because in "Testing" status the refresh tokens expire after 7 days.

---

## 11. Development and checks

| Tool | What it checks | Command |
|---|---|---|
| Laravel Pint | Code style | `vendor/bin/pint --test` |
| Larastan (PHPStan 2) | Static analysis, level 6 | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` |
| Pest 3 | Unit and feature tests against a real MariaDB or MySQL; parsers tested against redacted sample emails in `tests/Fixtures/emails` | `vendor/bin/pest` |
| Playwright | Review inbox, splitting, recurring payments, owed to me, budgets in Chromium at 390 px | `scripts/e2e.sh` |
| `composer audit` | Known security advisories in dependencies | `composer audit` |

`composer check` runs the first three. **GitHub Actions** runs Pint, Larastan, Pest on MySQL 8 and
`composer audit` on every push and pull request.

**Development environment:** Claude Code on the web. `.claude/hooks/session-start.sh` installs MariaDB, creates
the three databases, installs Composer and npm packages and prepares `.env`, as in Bowls Buddy. Gmail is faked
in tests; no real mailbox is read during development.

---

## 12. Costs

| Item | Cost |
|---|---|
| Afrihost Bronze Pro hosting | Already paid for Bowls Buddy; R0 extra |
| `budget.bowlsbuddy.co.za` | Subdomain of the existing domain; R0 |
| Gmail API, Google sign-in | Free |
| Laravel, Filament and all other libraries | Free, open source |
| GitHub and GitHub Actions | Free tier |
| HTTPS certificate | Free (AutoSSL) |
| Claude fallback (optional) | Pay per use; well under $1 a month once your banks have parsers |
