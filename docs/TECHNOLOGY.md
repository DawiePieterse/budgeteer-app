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
| Email source | Gmail API, read-only, one linked Gmail account per person |
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
| `app/Models` | `Household`, `User`, `GmailConnection`, `IngestedEmail`, `Account`, `Transaction`, `TransactionSplit`, `Category`, `Budget`, `Rule`, `Merchant`, `Person`, `Receivable`, `Settlement` |
| `app/Services/Gmail` | OAuth tokens, `history.list` sync, message fetch |
| `app/Parsers` | One parser per bank or card sender (for example `FnbParser`), plus `ClaudeParser` as the fallback |
| `app/Services` | Merchant clean-up, categorising, learning, budget periods, reimbursements, payment matching |
| `app/Filament` | Settings pages and resources |
| `app/Http/Controllers` | Phone screens: home, review inbox, transactions, owed to me |
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
- **Privacy (POPIA):** data is stored in South Africa (Afrihost); each person can unlink Gmail, and
  download or delete their own data.

---

## 3. How a transaction gets in

1. **Gmail filter.** Each person adds a Gmail filter that labels bank and card notifications `Budgeteer`.
   The app only lists messages with that label.
2. **Sync (every 5 minutes).** A scheduled job calls Gmail `history.list` from the last stored history ID for
   each linked account and queues new message IDs. The first link does a full `messages.list` of the label.
3. **Parse.** Each message is fetched, matched to a parser by sender, and turned into amount, date, merchant,
   card number ending, and type (purchase, refund, payment received). No match goes to the Claude fallback if
   it is on, otherwise to the review inbox as "could not read".
4. **De-duplicate.** Unique on Gmail message ID, plus a hash of amount, date, merchant and card to catch the
   same purchase notified twice.
5. **Categorise** (section 4), then either assign or send to the review inbox.
6. **Match payments.** An incoming payment is offered as the settlement for open receivables of the same
   amount (section 6).

Queued work uses Laravel's `database` queue, drained by the scheduler with
`queue:work --stop-when-empty --max-time=50`, so nothing runs longer than one cron cycle.

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

- **Household:** two users, each linking their own Gmail and cards. Everything is shared; a transaction can be
  marked private, which shows only its amount and category to the other person.
- **Bought for someone else:** a transaction can be split, fully or partly, to a person. That part goes to
  `receivables` and does not count against the budget.
- **Owed to me:** totals per person and age of each item; a one-tap WhatsApp request (`wa.me` link), as in
  Bowls Buddy.
- **Settling:** a "payment received" email of the same amount is offered as the match; confirming it closes the
  receivable.

---

## 7. Database

One database, `bowlsbg5n9w0_budgeteer`, created by Laravel migrations. Every table except Laravel's own has a
`household_id`, and every query goes through a global scope on it.

| Tables | What they hold |
|---|---|
| `households`, `users` | The household, its members and their allowlisted Google emails |
| `gmail_connections` | Encrypted refresh token, last history ID, last sync, status per linked Gmail account |
| `email_sources` | Known senders and the parser each uses |
| `ingested_emails` | Gmail message ID, sender, received time, parse status, error |
| `accounts` | Cards and bank accounts, identified by the number ending, with an owner |
| `transactions`, `transaction_splits` | Transactions and their parts (category, or person for a receivable) |
| `categories`, `budgets`, `budget_periods` | Category tree with icons, amount per category per period |
| `rules`, `merchants`, `category_tokens` | Explicit rules, merchant memory, the word model |
| `people`, `receivables`, `settlements` | People who owe money, what they owe, how it was paid |
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

## 8. Hosting: Afrihost

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
| Refresh a Gmail link that failed, and notify its owner | Daily |
| SQL backup | Nightly |

**Deploying:** `scripts/build-afrihost.sh` builds a ready-to-upload zip with `vendor/` included, as in Bowls
Buddy. Upload and extract it in the cPanel File Manager, then run `php artisan migrate` over SSH or cPanel
Terminal.

**Shared account risk:** Budgeteer and the Bowls Buddy clubs run as the same cPanel user, so a flaw in any one
of them could read the others' files, including Budgeteer's `.env`. Keeping Bowls Buddy patched protects
Budgeteer too. A separate Afrihost hosting account removes this link if it becomes a concern.

---

## 9. Google setup

| What | Value |
|---|---|
| Project | One Google Cloud project, OAuth consent screen "External", publishing status "In production", not verified |
| Scopes | `openid`, `email`, `profile`; `gmail.readonly` only when linking Gmail |
| Authorised domain | `bowlsbuddy.co.za` |
| Redirect URIs | `https://budget.bowlsbuddy.co.za/auth/google/callback`, `https://budget.bowlsbuddy.co.za/gmail/callback` |
| Users | Under 100, so the unverified-app warning is clicked through once per person |

"In production" is needed because in "Testing" status the refresh tokens expire after 7 days.

---

## 10. Development and checks

| Tool | What it checks | Command |
|---|---|---|
| Laravel Pint | Code style | `vendor/bin/pint --test` |
| Larastan (PHPStan 2) | Static analysis, level 6 | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` |
| Pest 3 | Unit and feature tests against a real MariaDB or MySQL; parsers tested against redacted sample emails in `tests/Fixtures/emails` | `vendor/bin/pest` |
| Playwright | Review inbox, splitting, owed to me, budgets in Chromium at 390 px | `scripts/e2e.sh` |
| `composer audit` | Known security advisories in dependencies | `composer audit` |

`composer check` runs the first three. **GitHub Actions** runs Pint, Larastan, Pest on MySQL 8 and
`composer audit` on every push and pull request.

**Development environment:** Claude Code on the web. `.claude/hooks/session-start.sh` installs MariaDB, creates
the three databases, installs Composer and npm packages and prepares `.env`, as in Bowls Buddy. Gmail is faked
in tests; no real mailbox is read during development.

---

## 11. Costs

| Item | Cost |
|---|---|
| Afrihost Bronze Pro hosting | Already paid for Bowls Buddy; R0 extra |
| `budget.bowlsbuddy.co.za` | Subdomain of the existing domain; R0 |
| Gmail API, Google sign-in | Free |
| Laravel, Filament and all other libraries | Free, open source |
| GitHub and GitHub Actions | Free tier |
| HTTPS certificate | Free (AutoSSL) |
| Claude fallback (optional) | Pay per use; well under $1 a month once your banks have parsers |
