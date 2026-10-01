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
| Phone screens | Blade templates, Alpine.js and one hand-written stylesheet with light and dark colours; the Geist font served from `public/fonts`; no JavaScript build step |
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
| `minishlink/web-push` | 11 | Phone notifications (Web Push with VAPID keys, `aes128gcm`): late or changed recurring payments, budget warnings, Gmail stopped. Works without `gmp`/`bcmath`, only slower |
| `anthropic-ai/sdk` (PHP) | 0.51 | Optional fallback: reads a bank email no parser recognises (section 5) |
| `symfony/dom-crawler` | 7 or 8 | Reading values out of HTML bank emails |
| pdf.js (Mozilla) | 4.10 | Reading statement PDFs in the browser, vendored in `public/vendor/pdfjs` (no build step). The Standard Bank PDF is encrypted, which PHP PDF libraries refuse; pdf.js opens it, and also gives each amount's column position |

Gmail is called through Laravel's HTTP client against the Gmail REST API, not `google/apiclient`, which is
large and mostly unused here.

### Structure

| Where | What |
|---|---|
| `app/Models` | `Household`, `User`, `GmailConnection`, `IngestedEmail`, `Account`, `Transaction`, `TransactionSplit`, `Category`, `Budget`, `Rule`, `Merchant`, `Person`, `Receivable`, `Settlement`, `RecurringPayment`, `RecurringOccurrence` |
| `app/Services/Gmail` | OAuth tokens, `history.list` sync, message fetch |
| `app/Parsers` | One parser per bank or card sender (`DiscoveryBankParser`, `StandardBankParser`), plus `ClaudeParser` as the fallback |
| `app/Statements` | Statement readers per bank (`Readers/StandardBankReader`, `Readers/DiscoveryBankReader`) and the balance check |
| `app/Transactions` | Kind of each line (`Classifier`), merchant keys, the statement importer and transfer pairing |
| `public/js/statement-text.js`, `public/js/statement-upload.js` | Turn a PDF into positioned lines of text on the phone; only that text is sent |
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
2. **Sync (every 5 minutes).** `budgeteer:gmail-sync` (scheduled, `withoutOverlapping`) calls Gmail
   `history.list` from the last stored history ID, limited to the label. The first sync lists the label's
   messages from the last 30 days; linking Gmail runs a first sync straight away. Each run is time-boxed
   (40 seconds from cron, 20 from the "Check now" button); the history position only moves on once every
   message up to it is handled, so a run cut short simply continues next time. Messages are handled
   directly, without the queue.
3. **Parse.** Each message is fetched, matched to a parser by sender and subject (`DiscoveryEmailParser`), and
   turned into amount, date and time, merchant, account and card number endings, cardholder and kind
   (purchase, refund, cash, money in). Declined purchases are skipped. An email that is not understood is
   logged as "Not read" with the reason, visible under More › Bank emails › Latest bank emails.
4. **De-duplicate.** Every email is recorded once in `ingested_emails` by Gmail message ID. A purchase that is
   both emailed and on a statement is one transaction: whichever arrives second is matched to the first on
   account, exact amount and date (the statement may be up to 4 days later), never on the merchant name. The
   statement's wording then replaces the email's, so later overlapping statements recognise the line.
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
| Discovery Bank | Dewan's card: an extra card on the same credit card account, with its own number (outside the budget, see "A card kept for someone else" in section 6) | `DiscoveryBankParser` | As above; the card number ending in each email says whose card it was |
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
covered by the monthly statement (section 8), recurring payments marked paid by hand (section 7) or manual entry.

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

*Built:* a card's owner is set in More › Accounts and cards. Transactions on it get `person_id` and leave the budget
and the categorise list. What the person owes is `opening_balance_cents` (as of `opening_balance_on`) plus
their transactions after that date, less `settlements`. Transactions from before the opening date leave the
budget but are not added again, because the opening balance already includes them. A payment into an own
account whose description contains the person's `payment_reference` is offered as a repayment; confirming it
creates a settlement linked to that transaction and takes it out of household income. There is no separate
`receivables` table: the charged transactions are the receivables.

Dewan has an extra card on the household's Discovery credit card account, with its own card number, and
its notifications come to the same Gmail. His purchases are paid off with the rest of the account, and Dewan
pays the household back. His spending must not
count against the household budget, but what he owes has to be visible on its own.

- **Card setting "Charge to a person".** Dewan's card, recognised by its number ending, is set to charge
  to the person Dewan. Every transaction on it goes straight to his receivables, in full, and never
  reaches the budget or the review inbox. A category is still guessed and can be corrected, so his
  spending can be broken down, but it is shown only on his page.
- **Refunds** on his card reduce what he owes.
- **There is no separate repayment of his card.** The account's single repayment covers both cards and is a
  transfer (section 3); it does not clear what he owes you.
- **Only the notification emails say which card was used.** The Discovery statement lists every purchase on
  the account without the card number (section 8). A statement line that matches an email transaction takes
  the card from the email. A statement line with no matching email goes to the review inbox with "Yours or
  Dewan's?", and the answer is remembered for that merchant as a suggestion next time.
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
| Match text | The stable start of the payee as it appears in the bank email or statement, for example `DISC PREM` or `MOMENTUM`; the reference after it changes every month and is ignored |
| Expected amount and tolerance | R4,500, within R50 or 2%; or "amount varies" for payments like a municipal account, matched on text and date only |
| Day of the month | 1st (matched from 3 days before to 5 days after) |
| Account | The account it is paid from; optional |
| Frequency | Weekly (for example a cleaner), monthly, or yearly for things like a car licence |

- **Each budget period** creates an expected occurrence for every active recurring payment.
- **Matched:** a transaction with the match text, within the tolerance and the date window, is linked to the
  occurrence, marked paid and given its category. No review is needed.
- **Amount changed:** a match outside the tolerance (for example the January medical aid increase) goes to the
  review inbox with "Expected R4,500, was R4,850. Update the amount?".
- **Not seen:** an occurrence still open 5 days after its date goes to the review inbox. It can be marked
  paid by hand (for a bank that does not email debit orders), which creates the transaction, or marked
  skipped for that month.
- **Suggestions:** a cleaned merchant paid at a similar amount in each of the last three periods (or every
  week) is offered as a new recurring payment. Matching uses the payee, not the transaction type, because
  the bank can relabel the same debit order (seen: a debit order whose type changed from one month to the
  next with the same payee and amount).
- **Budget view:** the home screen shows what is already paid, what recurring payments are still due this
  period, and what is left to spend after both.

---

## 8. Statements

Both banks send a monthly statement. Statements are used twice: to fill in the history when Budgeteer
starts, and every month to catch anything the notification emails missed.

### Getting started from past statements

1. **Upload** the last three to six statements from each bank on the Statements screen: the Standard Bank
   cheque account and the Discovery credit card account (which includes Dewan's card). A CSV or OFX export from online banking is
   read in preference to a PDF where the bank offers one, because it has no layout to guess.
2. **Read on the phone.** pdf.js turns the PDF into lines of text with each item's position, in the browser.
   Only that text is sent to the server; the PDF itself is never uploaded. A password-protected PDF asks for
   its password, which is used on the phone and never sent.
3. **Read and preview.** The statement reader for that bank lists the transactions it found with the
   statement's opening and closing balance. The import is refused if the transactions do not add up to the
   difference between the two, so a misread line is caught before anything is saved.
4. **Categorise by merchant, not by transaction.** Imported transactions are grouped by cleaned merchant,
   largest first, and each group is categorised once ("all 23 WOOLWORTHS → Groceries"). Each choice goes
   into merchant memory and the word model, so the first real email is usually categorised automatically.
5. **Set things up from what was found.** Recurring payments are suggested from debit orders that appear
   every month (medical aid, insurance), the card repayments are matched as transfers (section 3), and
   Dewan's opening balance is typed in once: whatever he had not paid back at the start of the imported
   period. Because the Discovery statement does not show which card was used, past purchases are not
   split between the cards; his receivables start from the opening balance and build up from the
   notification emails from then on. Past purchases can still be moved to him one by one if wanted.

### Every month after that

When a statement is uploaded, each line is matched to a transaction already captured from email (same
account, same amount, date within 3 days; the merchant text is compared loosely because statements and
emails describe it differently):

| Result | What happens |
|---|---|
| Matched | Nothing; the email transaction is confirmed |
| On the statement only | Added as a transaction from the statement and categorised as usual. Typical for debit orders the bank does not email; a matching recurring payment is marked paid |
| In Budgeteer only | Listed for review: usually a declined or reversed purchase, which is then removed |

The statement's closing balance is kept, so the app can show that each account agreed with the bank up to
that date.

### Standard Bank statement (checked against a real 6-month statement)

A 6-month PDF statement for the cheque account was read in full: 173 transactions over 11 pages, and the
opening balance plus every line matched both the running balance on each line and the statement summary
to the cent. What it showed:

- **Layout.** Each transaction is three lines: date and payee (`02 Apr 26 <payee>`), then the transaction
  type, then the amount and the running balance. Payments are printed negative, deposits positive, and the
  balance can go negative (overdraft). The page header and footer repeat on every page and are skipped.
  The opening balance is on the first transactions page; the summary (total payments and deposits) is on
  the last. The file is encrypted but opens without a password, so no password is needed at upload.
- **Afrikaans.** Transaction types are in the account's language, here Afrikaans (`IB-BETALING NA`,
  `DEBIETOORPLASING`, `MEDIESEFONSBYDRAE`, `VERSEKERINGSPREMIE`, `KREDIETOORPLASING`, `VASTE MAANDELIKSE
  FOOI`, and others). The reader maps both the Afrikaans and English names to the same kinds: payment out,
  debit order, transfer in or out, deposit, cash withdrawal, bank fee, interest. Notification emails are
  likely to be in the same language, so the email parser uses the same list.
- **No card purchases.** Day-to-day spending is on the Discovery card, not on this account. The cheque
  account carries the card repayment, debit orders (medical aid, insurance), payments to people (a weekly
  cleaner, levies, storage, church), cash withdrawals, bank fees and income.
- **A money market account.** Large transfers come in from, and go out to, the household's Standard Bank
  money market account, identified only by its number. It funds the cheque account and also pays the
  Discovery card directly. It is set up as one of the household's own accounts (its balance does not need
  to be tracked), so every movement to or from it is a transfer, never income or spending.
- **Income.** Consulting income and rent from the rental property arrive by EFT, and some card-machine
  settlements (SnapScan). All of it is household income, in its own categories (Consulting, Rental, Other),
  so the home screen can show money in against money out for the period; it does not change category
  budgets. The rental property's costs are ordinary household spending, not tracked separately.
- **Card repayment varies.** The Discovery card is paid with a different amount each month (the full card
  balance), so it is matched as a transfer by amount against the Discovery "payment received", never set
  up as a fixed recurring payment.
- **References change monthly.** Several debit orders carry a new reference every month (policy or
  collection numbers, the month name). Match text uses only the part that stays the same.
- **Fees are small but recurring.** Monthly account fee, overdraft service fee and interest, and a fee per
  instant payment are grouped under Bank fees automatically by type.

A made-up statement in the same layout, with invented names, numbers and amounts, is the Pest fixture for
`StandardBankStatement`. The real statement is never committed.

### Discovery Bank statement (checked against a real 3-month statement)

A 3-month PDF statement for the credit card account (11 pages, not encrypted) was read in full: 404
transactions, and every line's amount matched the change in the running balance.

- **Layout.** One line per transaction: ISO date, description, amount and running balance, with `R` and
  non-breaking spaces before the numbers (`2026-06-28 WOOLWORTHS CAPE TOWN R 356.10 R 51,397.39`). The
  debit and credit columns cannot be told apart in the extracted text, so the reader takes the sign from the
  change in balance; the first line's sign comes from the column position in the PDF. There is no opening
  balance line; it is worked out from the first transaction.
- **The account is kept in credit.** The balance is money available, not debt, and Discovery pays interest
  on it. Purchases lower it, payments raise it. The reader stores the balance as the bank shows it.
- **Payments in** are named after the account holder only (`DJ PIETERSE`). They come from the cheque
  account (matched by amount and date against the Standard Bank payment) or straight from the money market
  account; any payment in from the account holder is a transfer from an own account, never income.
- **Refunds** start with `Refund` and reduce the category they came from.
- **Foreign purchases** show the original amount and currency in the description (`250.00 KES`) and are
  followed by a separate `Intl payment fee` line, which goes to Bank fees.
- **Fees and interest.** Monthly account fee, monthly facility fee, card fee (with the card's number
  ending), ATM withdrawal fee and interest earned are recognised by their descriptions and go to Bank fees
  or Interest.
- **Which card was used is not shown.** Purchases carry no card number ending, so a statement cannot tell
  one cardholder's spending from another's on the same account. Dewan's card is on this account, so his
  spending is separated by the notification emails, which name the card (section 6).
- **`Pay` lines** (around R2,500 to R3,150 a month, no payee shown) are the Saldanha Bay Municipality account
  (rates and services) paid from the card. They are set up as a monthly recurring payment with "amount
  varies", matched on a Discovery description of exactly `Pay`, category Rates and services. Any other use of
  Discovery Pay would look the same, so a `Pay` line more than 30% off the previous month goes to the
  review inbox.

A made-up statement in the same layout is the Pest fixture for `DiscoveryBankStatement`.

### Kinds of bank email read (checked against real emails)

| Email | Layout | Recorded as |
|---|---|---|
| Discovery "Card payment" | Merchant – R amount, From ***acct, cardholder (extra cards only), Card ending | Purchase, with card |
| Discovery "Card refund" | Merchant – R amount, **To account ending** ***acct, Card ending | Refund |
| Discovery "ATM withdrawal" | At place - R amount, From **account ending** ***acct | Cash |
| Discovery "Incoming payment" | R amount on its own line, To account ending, Reference: payer | Money in, or a transfer when the payer is an own-account name |
| Discovery "Card declined" | | Skipped |
| Discovery card payment in another currency | Merchant – USD 23.00 (no rand amount) | Matched to the statement line whose description shows "23.00 USD", giving it the card; otherwise left for the statement |
| Standard Bank "MyUpdates Notification" | "An amount of R200.00 was paid from Standard Bank account ending in 3445 to PAYEE on 2026-09-28." | Payment (or money in) on the cheque account |

### Discovery Bank notification email (checked against a real email)

Subject `Transaction update — <date> <time>`, from Discovery Bank, one transaction per email. The body is a
list of short lines, which `DiscoveryBankParser` reads in order:

| Line | Example (invented values) | Stored as |
|---|---|---|
| Heading | `Card payment` | Type (purchase; other headings for refunds, payments received and declines) |
| Merchant and amount | `Checkers Sixty60 Cape To – R 469.88` | Merchant (the name is cut short, as on the statement) and amount |
| Account | `From ***1234` | Account, by the account number ending |
| Cardholder | `Jane Doe` | Who used the card. Only on extra cards: the main cardholder's emails leave this line out, so the parser treats it as optional and goes by the card ending |
| Card | `Card ending ***5678` | Card, by number ending |
| Date and time | `Sunday, 27 September at 16:19` | Transaction time; the year comes from the subject |
| Available balance | `Available balance: R 165,371.88` | Kept on the account as its latest available balance (balance plus credit limit) |

- The email names both the cardholder and the card, so Dewan's purchases are recognised as his from the
  email alone, as section 6 needs.
- A purchase is emailed straight away but appears on the statement a day or two later, sometimes under the
  next statement's period. Matching a statement line to an email allows a few days either way, as
  section 8 describes.
- The merchant name can differ between the email and the statement for the same purchase (seen: an email
  for `WOOLWORTHS TYGERVALLEY ZA` on the 26th was `WOOLWORTHS BELLVILLE` on the statement on the 27th, same
  amount). Matching therefore relies on account, exact amount and date; the merchant only breaks a tie
  between two purchases of the same amount.

**Later:** statements arrive by email, so they could be read from Gmail automatically like notifications.
The Standard Bank statement needs no password, so it can be read from Gmail once uploading works; a bank
whose statements need a password (possibly an ID number) stays manual rather than storing it. Statement files are not kept once they have been read.

---

## 9. Database

One database, `bowlsbg5n9w0_budgeteer`, created by Laravel migrations. Every table except Laravel's own has a
`household_id`, and every query goes through a global scope on it.

| Tables | What they hold |
|---|---|
| `households`, `users` | The household, its members and their allowlisted Google emails |
| `gmail_connections` | Encrypted refresh token, last history ID, last sync and status for the linked Gmail account |
| `email_sources` | Known senders and the parser each uses |
| `ingested_emails` | Gmail message ID, sender, received time, parse status, error |
| `accounts` | Cards and bank accounts, identified by the number ending, with the person who uses each |
| `transactions`, `transaction_splits` | Transactions, with their source (email, statement or entered by hand), and their parts (category, or person for a receivable) |
| `statement_imports` | Each uploaded statement: account, period, opening and closing balance, and how many lines matched, were added or were flagged |
| `recurring_payments`, `recurring_occurrences` | Expected monthly or yearly payments, and each period's occurrence with its status (due, paid, not seen, skipped) and linked transaction |
| `categories`, `budgets`, `budget_periods` | Category tree with icons, amount per category per period |
| `rules`, `merchants`, `category_tokens` | Explicit rules, merchant memory, the word model |
| `people`, `receivables`, `settlements` | People who owe money, what they owe, how it was paid; `accounts` can name a person to charge every transaction to |
| `push_subscriptions`, `sent_notifications` | Web push endpoints per phone or browser, and each notification sent (so each goes once); `users.notify_*` hold each person's choices |
| `bg_sessions`, `bg_cache`, `bg_cache_locks`, `bg_jobs`, `bg_failed_jobs`, `bg_migrations` | Laravel's own tables |

- Character set `utf8mb4` everywhere.
- Money columns are `BIGINT` cents.

| Environment | Database |
|---|---|
| Production (Afrihost) | MariaDB 10.11, `localhost`, `bowlsbg5n9w0_budgeteer` (one of the package's 20 databases) |
| CI (GitHub Actions) | MySQL 8.0 service container |
| Development and tests | MariaDB: `budgeteer` (dev), `budgeteer_test` (Pest), `budgeteer_e2e` (Playwright) |

---

## 10. Hosting: Afrihost

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
| Phone notifications (`budgeteer:notify`), 07:00 to 20:30 | 15 minutes |
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

## 11. Google setup

| What | Value |
|---|---|
| Project | One Google Cloud project, OAuth consent screen "External", publishing status "In production", not verified |
| Scopes | `openid`, `email`, `profile`; `gmail.readonly` only when linking Gmail |
| Authorised domain | `bowlsbuddy.co.za` |
| Redirect URIs | `https://budget.bowlsbuddy.co.za/auth/google/callback`, `https://budget.bowlsbuddy.co.za/gmail/callback` |
| Users | Under 100, so the unverified-app warning is clicked through once per person |

"In production" is needed because in "Testing" status the refresh tokens expire after 7 days.

---

## 12. Development and checks

| Tool | What it checks | Command |
|---|---|---|
| Laravel Pint | Code style | `vendor/bin/pint --test` |
| Larastan (PHPStan 2) | Static analysis, level 6 | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` |
| Pest 3 | Unit and feature tests against a real MariaDB or MySQL; parsers tested against redacted sample emails in `tests/Fixtures/emails` and statement readers against redacted statements in `tests/Fixtures/statements` | `vendor/bin/pest` |
| Playwright | Review inbox, splitting, recurring payments, owed to me, budgets in Chromium at 390 px | `scripts/e2e.sh` |
| `composer audit` | Known security advisories in dependencies | `composer audit` |

`composer check` runs Pint and Pest. **GitHub Actions** runs Pint, Pest on MySQL 8 and `composer audit` on
every push and pull request. Larastan is not installed yet: the development sandbox cannot download
`phpstan/phpstan` (it is published only as a GitHub zip, and GitHub's download host is blocked there), so it
is added once that host is allowed.

**Development environment:** Claude Code on the web. `.claude/hooks/session-start.sh` installs MariaDB, creates
the three databases, installs Composer packages and prepares `.env` with the development sign-in switched on. Gmail is faked
in tests; no real mailbox is read during development.

---

## 13. Costs

| Item | Cost |
|---|---|
| Afrihost Bronze Pro hosting | Already paid for Bowls Buddy; R0 extra |
| `budget.bowlsbuddy.co.za` | Subdomain of the existing domain; R0 |
| Gmail API, Google sign-in | Free |
| Laravel, Filament and all other libraries | Free, open source |
| GitHub and GitHub Actions | Free tier |
| HTTPS certificate | Free (AutoSSL) |
| Claude fallback (optional) | Pay per use; well under $1 a month once your banks have parsers |
