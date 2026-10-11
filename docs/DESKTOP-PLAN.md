# Desktop overview: plan

A one-screen picture of the budget month for a computer screen, modelled on a spreadsheet budget dashboard:
the month's totals at the top, three charts beside them, a table per group of budget lines, the biggest
spending and a transaction filter. The phone screens stay as they are.

This is a plan, not built yet. Questions still to answer are at the end.

---

## 1. What the reference shows, and what Budgeteer has for it

| Reference panel | What it shows | In Budgeteer today | Missing |
|---|---|---|---|
| Month heading | "January 2024" | `BudgetPeriod::label()`, the period switcher | – |
| Total income, Total expenses | Money in and out this month | `received`, `spent` on Home | – |
| Left to spend | Budget minus spent | `budgeted − spentOnLines` (the Home hero) | – |
| Left to budget | Expected income minus everything budgeted | – | A budget (expected amount) on income lines |
| Balance overview | A line through the month | Transactions carry `balance_after_cents` from statements | Emailed transactions have no balance; see 4.3 |
| Spending overview (pie) | Share of spending per group | Categories are one flat list | A **group** per budget line |
| Budget vs real (bars) | Budget and real per group | Per line on Home | The group |
| Budget summary | Income, Bills, Expenses, Subscriptions, Savings: budget, real, diff | Per line | The group |
| Bills, Expenses, Subscriptions, Savings, Income tables | Each line's budget, real, diff and a tick | Per line on Home and Budget; recurring payments know paid, due or late | The group; savings (4.4) |
| Top 5 sub-categories | The biggest lines, with a bar and % of all spending | Home sorts lines by share used | Only the sort and the % |
| Transaction filter | Dates, min and max amount, line, group, sort | Transactions filters by month, account, line, search; `from`/`to` already read | Amount range, group, sort, a table layout |

So most of the numbers exist already; the dashboard needs one new idea (the group of a budget line), income
budgets, and a way to draw charts.

## 2. Where it lives

A new screen, **Overview** (`GET /overview`, `OverviewController`), listed in the sidebar only, like
Recurring and Statements, between Home and Transactions. It takes the same `?in=` date as Home, so the period
switcher and "Last month" work the same way.

Why a new screen and not a wider Home: Home is phone-first and its order (what is left, what needs attention)
is right for a quick look. The overview is for sitting down at the computer once a week or at month end, and
it is dense on purpose. Keeping them apart means neither gets worse. Below 64rem the Overview still works,
stacked in one column, but no phone tab links to it.

At 64rem and wider the page grows past the current `max-width: 72rem` to about 96rem for this screen only
(`.page.wide`), so the 4-column layout below fits a 1440px screen without scrolling sideways.

```
┌──────────────┬───────────────────┬───────────────────┬───────────────────┐
│ Month, period│ Spending this     │ Spending by group │ Budget vs real    │
│ switcher     │ month (line)      │ (donut)           │ (grouped bars)    │
│ 4 total tiles│                   │                   │                   │
├──────────────┼───────────────────┼───────────────────┼───────────────────┤
│ Summary      │ Bills             │ Everyday          │ Savings           │
├──────────────┴─────┬─────────────┴───────┬───────────┴───────────────────┤
│ Income             │ Subscriptions       │ Top 5 by spend                │
├────────────────────┴─────────────────────┴───────────────────────────────┤
│ Find transactions: dates · min/max amount · line · group · sort  [Show]  │
└──────────────────────────────────────────────────────────────────────────┘
```

## 3. Changes to the data

### 3.1 A group for each budget line

New column `categories.group` (string, nullable), cast to a new enum `App\Enums\CategoryGroup`:

| Value | Shown as | Meant for |
|---|---|---|
| `bills` | Bills | Fixed monthly costs: bond or rent, levies, insurance, phone, electricity |
| `everyday` | Everyday | Groceries, fuel, eating out, clothing (the reference calls these Expenses) |
| `subscriptions` | Subscriptions | Netflix, Spotify, gym, cloud storage |
| `savings` | Savings | Money put away (4.4) |

Income lines need no group: `kind = income` already says so. An expense line with no group counts as
Everyday.

- **Migration** `2026_10_12_000001_add_group_to_categories.php` adds the column and fills it once with a
  guess: a line linked to a monthly recurring payment becomes Bills; a name matching a short list
  (`netflix|spotify|showmax|dstv|youtube|apple|google one|icloud|gym|subscription`) Subscriptions; `savings|
  invest|retirement|emergency|unit trust|tfsa` Savings; everything else Everyday. The guess lives in
  `App\Support\CategoryGroups::guess()`, beside `CategoryIcons::guess()`, so new lines (pasted budgets,
  Budget › Add) get it too.
- **Changing it:** on the Budget screen each line gets a small group select next to its amount, saved with the
  existing one **Save**. The pasted-budget format can take an optional heading line ("Bills", "Savings" …)
  that sets the group of the lines under it, so a spreadsheet laid out like the reference pastes in one go.

### 3.2 Expected income

`categories.budget_cents` already exists for every line; only the Budget screen and `budgeted` ignore it on
income lines. The Budget screen gets an **Income** section listing the income lines with an expected amount
each. `budgeted` keeps meaning expense lines only, so Home does not change.

*Left to budget* = expected income − all expense budgets (including Savings). Shown only once at least one
income line has an expected amount; until then the tile links to the Budget screen ("Add expected income").

## 4. The numbers

### 4.1 One place to work them out

Today `HomeController` adds up the month itself. That moves into `App\Services\MonthSummary`, used by both
Home and Overview, so the two screens can never disagree:

```php
$summary = MonthSummary::for($household, $period);
$summary->lines;        // each category: name, icon, group, budget, real, diff, share
$summary->groups;       // per group and Income: budget, real, diff
$summary->received;     // money in
$summary->spent;        // money out on the household's own budget
$summary->budgeted;     // expense budgets
$summary->expectedIn;   // income budgets
$summary->dailySpent;   // [date => cents], for the line chart
```

Same rules as Home today: transfers between own accounts, purchases charged to people and special projects
are left out; not-yet-categorised spending shows as its own line ("Not categorised yet") in Everyday.
`HomeController` shrinks to reading from it; the existing Home tests (`NavigationTest`, `OwedTest`, `ProjectTest`, `RecurringTest`) must pass
unchanged, which is the check that the move changed nothing.

### 4.2 The tick column

The reference ticks lines by hand. Here the tick is worked out, so it never goes stale:

- **Bills and Subscriptions:** a line with recurring payments shows the month's state from
  `RecurringSchedule::occurrences()`: ✓ paid, a clock for due, red "late" or "changed". Lines without
  recurring payments show nothing.
- **Everyday:** no tick; the diff column (green left, red over) says it.
- **Savings:** ✓ once real reaches the budget.

### 4.3 The line chart

The reference's "Balance overview" is one balance through the month. Budgeteer has several accounts, and only
statement lines know the balance, so a balance line would be missing the days since the last statement.

Instead: **spending this month**, a line of cumulative spent per day (`dailySpent`) against a straight dashed
line from 0 to the month's budget (the even pace). Above the dashed line means spending faster than the budget
allows. It is built from every transaction, emailed ones included, so it is always current. An account
balance chart can follow later, per account, from `balance_after_cents`, if it turns out to be wanted.

### 4.4 Savings

Money moved to the money market account is a transfer between own accounts, so today it is (rightly) not
spending. For the Savings group:

- A savings line's **real** is what was categorised to it **plus** transfers into accounts of kind
  `money_market` in the month, when that account is chosen as where the line's savings go (a new nullable
  `categories.savings_account_id`, set on the Budget screen for Savings lines only).
- These transfers stay `is_transfer`; they are counted for the Savings table and *Left to budget* only, never
  as spending on Home.

This is the one part that needs a decision (question 3).

## 5. Drawing the charts

No chart library. The site's security policy allows only its own scripts and no inline styles, there is no
JavaScript build step, and the existing bars, rings and circles are already server-drawn SVG. The charts
follow the same pattern: Blade components that work out the geometry in PHP and give each shape a class; all
colours come from `app.css` tokens, with light and dark values.

| Component | Draws | Notes |
|---|---|---|
| `x-chart.line` | Cumulative spend and the dashed pace line, 5 date ticks, 4 amount ticks | `viewBox` scaled, `vector-effect: non-scaling-stroke` |
| `x-chart.donut` | Share of spending per group | Arcs as circles with `stroke-dasharray`, labels with the % beside the chart, not only in colour |
| `x-chart.bars` | Budget and real per group, side by side | Real bars over budget drawn in the over colour |
| `x-bar` (exists) | Top 5 bars | Reused as is |

- Four group colours, added as tokens next to the owner colours (`--group-bills`, `--group-everyday`,
  `--group-subscriptions`, `--group-savings`) and checked for contrast in both themes. Each table's header
  strip uses its group's colour, as in the reference, so a table and its slice of the donut match.
- **Hover values without JavaScript:** each point, slice and bar has an SVG `<title>`, which the browser shows
  as a tooltip.
- **Accessible:** each chart has `role="img"` and an `aria-label` that says the numbers ("Everyday R8,400,
  41%; Bills …"); the tables below hold the same numbers. A `forced-colors` block like the one for bars.
- The geometry (scales, ticks, arcs) sits in small classes in `App\Support\Charts` so it is unit-tested
  without rendering views.

## 6. The transaction filter

The filter panel on the Overview is a GET form to `/transactions`, which learns:

| Parameter | Does |
|---|---|
| `from`, `to` | Already read; get date inputs on the form |
| `min`, `max` | Amount range in rand, on the size of the amount (money in or out) |
| `group` | All lines in a group |
| `sort` | `date` (default), `amount`, `merchant`; `dir` = `asc` or `desc` |

Day grouping and day totals only make sense sorted by date, so another sort shows a plain list. From 64rem the
Transactions list becomes a table (date, description, line, account, amount) with the same rows, so many fit
on screen; the phone list is unchanged. The existing pills keep working, and the filter values stay in the
query string so a filtered list can be bookmarked.

## 7. Steps

Each step is one branch merged into `main`, with `composer check` green, and leaves the app working.

| # | Step | Visible change | Tests |
|---|---|---|---|
| 1 | `MonthSummary` service; Home reads from it | None | Unit tests for the sums and exclusions; existing Home tests unchanged |
| 2 | `categories.group`, the guess, the select on Budget, headings in pasted budgets | Group select on Budget | Migration guess, `BudgetList` with headings, saving a group |
| 3 | Overview screen without charts: heading, four tiles, Summary, the four group tables, Income, Top 5; sidebar item; `.page.wide` layout | New screen | Feature test per panel; excluded transactions not counted; empty household |
| 4 | Charts: line, donut, bars | Charts on Overview | Unit tests for `Charts` geometry; feature test that each chart's label has the numbers |
| 5 | Expected income on Budget; *Left to budget* tile | Income section on Budget | Saving expected income; tile maths; Home unchanged |
| 6 | Savings lines with a savings account | Savings table real values | Transfers counted for Savings only, never as spending |
| 7 | Transaction filter: amount range, group, sort; table layout from 64rem | Filter panel; Transactions as a table on desktop | Each filter and sort; pagination keeps them |
| 8 | Polish: print stylesheet for the Overview (A4 landscape), check at 1280, 1440 and 1920px, light and dark | – | Browser screenshots at those widths |

Steps 1 to 4 give a usable dashboard; 5 to 8 can follow in any order.

## 8. Not planned

- Ticking lines by hand (the ticks are worked out, 4.2).
- Editing amounts in the overview tables; that stays on the Budget screen, one click away from each table.
- A chart library or JavaScript build step.
- "50+ rows per category": the tables list every line, however many.

## 9. Questions

1. **Separate Overview, or Home on a wide screen?** The plan adds an Overview screen and leaves Home as it is.
   The other way is to make Home itself switch to this layout at 64rem and wider.
2. **Groups:** are Bills, Everyday, Subscriptions and Savings the right four, and is "Everyday" a good name
   (the reference says "Expenses", which here would read as all spending)?
3. **Savings:** count transfers into the money market account as savings (4.4), or only what is categorised
   to a Savings line by hand?
4. **Line chart:** spending against the even pace (4.3), or account balances?
