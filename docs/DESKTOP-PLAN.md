# Desktop overview: plan

A high-level summary for the Mac: how this budget month is going and how the months compare, on one calm
screen in the style of Apple's own apps. It is for sitting down once a week or at month end, not for doing
the bookkeeping, which stays on the existing screens. The phone screens do not change.

This is a plan, not built yet. The mockup shows the look with made-up numbers:
[`docs/mockups/desktop-overview.html`](mockups/desktop-overview.html), with screenshots in
[light](mockups/overview-light.png) and [dark](mockups/overview-dark.png). Questions still open are at the end.

![Overview mockup, light](mockups/overview-light.png)

---

## 1. What the screen answers

| Panel | Question it answers | Built from |
|---|---|---|
| Four tiles: **Money in**, **Spent so far**, **Left to spend**, **Saved** | Where do we stand this month? Each with a small trend or a comparison with last month | This month's totals; the same day last month; the last 12 months |
| **Spending and budget** (12 bars, a money-in line, a dashed budget line) | Are we living within the budget and within what comes in, month after month? | One total per budget month |
| **Where it went** (donut by group) | What does the money go to? | This month by group |
| **This month's pace** (running total, last month in grey, the even pace dashed) | Are we spending faster than usual this month? | Spent per day, this month and last |
| **Budget vs real** (one bar per group and a total) | Which kind of spending is using the budget? | This month by group |
| **Biggest changes** | What has changed lately? | Last full month per line against its average over the 6 months before |
| **Budget lines** (table, filter by group) | How is each line doing, and is it usually like this? | This month per line, a 6-month sparkline, the recurring payment's status |

The range control (6 months, 12 months, All) sets how far back the bars and sparklines reach; the arrows step
through budget months, like the period switcher on Home.

## 2. Where it lives

A new screen, **Overview** (`GET /overview`, `OverviewController`), second in the sidebar after Home. Like
Recurring and Statements it is in the sidebar only, so no phone tab links to it; under 64rem it still works,
stacked in one column. It takes Home's `?in=` date, plus `range=6|12|all`.

The page is wider than the others (about 88rem against 72rem) and laid out on a 12-column grid: tiles of 3
columns, then 8 + 4, then 4 + 4 + 4, then the table across all 12. That fits a 13-inch MacBook (1440 by 900
points) with the tiles and the first charts above the fold, and stays balanced up to a 27-inch screen.

## 3. The look

Apple's apps (Health, Stocks, Screen Time, System Settings) are the model:

- **Surfaces:** a light grey page (`#f5f5f7`), white cards with an 18px radius and a very soft shadow, no
  borders; in dark mode a black page and `#1c1c1e` cards without shadow, following the Mac's appearance
  setting as the app already does.
- **Colour:** Apple's system colours, used sparingly and always with a word or number beside them: blue for
  spending, green for money in and savings, orange for what is left, red only for over budget. The four
  groups get indigo (Bills), orange (Everyday), pink (Subscriptions) and green (Savings), and keep them
  everywhere: donut, bars, table dots.
- **Type:** one large bold title, big figures in the tiles, small grey labels, tabular figures so amounts line
  up. The app's Geist font is already close to SF Pro (see question 3).
- **Controls:** a segmented control for the range and the table filter, small rounded arrows for the month.
- **Charts:** rounded bar tops, a soft gradient under the running total, thin hairline grids, a dot on the
  latest value, no chart borders or 3D.
- **Calm:** white space instead of lines, at most four colours per chart, and the over-budget red only where
  something is really over (a month more than 3% over its budget, a line over its amount).

The new colours and the card style are tokens in `public/css/app.css` with light and dark values, scoped to
`.overview` so the phone screens look exactly as now. The sidebar gets a light touch to match (selected item
in a soft blue pill, as in the mockup), which phones never see.

## 4. Changes to the data

### 4.1 A group for each budget line

New column `categories.group` (string, nullable), cast to a new enum `App\Enums\CategoryGroup`:

| Value | Shown as | Meant for |
|---|---|---|
| `bills` | Bills | Fixed monthly costs: bond or rent, levies, insurance, phone, electricity |
| `everyday` | Everyday | Groceries, fuel, eating out, clothing |
| `subscriptions` | Subscriptions | Streaming, apps, gym, cloud storage |
| `savings` | Savings | Money put away (4.2) |

An expense line without a group counts as Everyday; income lines need none.

- The migration fills it once with a guess, kept in `App\Support\CategoryGroups::guess()` beside
  `CategoryIcons::guess()` so new lines get it too: a line linked to a monthly recurring payment is Bills; a
  name like `netflix|spotify|showmax|dstv|youtube|icloud|google one|gym|subscription` Subscriptions;
  `savings|invest|retirement|emergency|unit trust|tfsa` Savings; anything else Everyday.
- On the Budget screen each line gets a small group select beside its amount, saved with the existing
  **Save**. A pasted budget may have heading lines ("Bills", "Savings") that set the group of the lines below.

### 4.2 Savings

Money moved to the money market account is a transfer between own accounts, so it is (rightly) not spending.
For **Saved** and the Savings group, a savings line's real amount is what was categorised to it plus transfers
in the month into the account chosen for it (a new nullable `categories.savings_account_id`, offered on the
Budget screen for Savings lines, listing money market accounts). Those transfers stay transfers: Home and
*Left to spend* never count them as spending.

Saved as a share of income is shown only for months with money in.

## 5. The numbers

Two services, so Home and Overview can never disagree:

- **`App\Services\MonthSummary`** takes over the sums `HomeController` does today (money in, spent, each line,
  with the same rules: transfers, purchases charged to people and special projects left out, uncategorised
  spending as its own line) and adds groups, savings and spent per day. Home then reads from it; the existing
  Home tests (`NavigationTest`, `OwedTest`, `ProjectTest`, `RecurringTest`) must pass unchanged.
- **`App\Services\Trends`** gives the months for the range: per budget month, money in, spent, budget, saved,
  and spent per line. It runs one query over the whole range, grouped by day and category, and sorts the rows
  into budget months in PHP (`BudgetPeriod`), since months start on the household's own day. A few years of a
  household's transactions is a few thousand rows, so no caching is needed at first.

Rules the panels follow:

| Number | Rule |
|---|---|
| Spent so far against last month | Spent up to the same day of last month's period ("4% under Sep's pace") |
| Left to spend a day | Left to spend ÷ days to go, shown only for the current month |
| Budget line in the bars | The budget total as it is now; the app does not keep old budgets (question 4) |
| Months shown | From the first month with transactions (or the household's keep-from date); empty months are not drawn |
| Biggest changes | The last full month per line against the average of the 6 before it; lines with at least R300 a month and a change of at least R200; the two biggest rises and the two biggest falls |
| Status in the table | From `RecurringSchedule::occurrences()`: paid with date, due with date, late or changed; Savings lines say Done once their amount is reached |

## 6. Drawing the charts

No chart library. The site's security policy allows only its own scripts and no inline styles, there is no
JavaScript build step, and the bars, rings and circles the app already has are SVG drawn on the server. The
charts follow suit: Blade components work out the geometry in PHP and give every shape a class, so colours
come from the stylesheet and switch with dark mode. Gradients are SVG `<linearGradient>` definitions, which the
policy allows.

| Component | Draws |
|---|---|
| `x-chart.columns` | Monthly bars with a line and a dashed reference line (Spending and budget) |
| `x-chart.area` | Running totals with a gradient fill (This month's pace) |
| `x-chart.donut` | Shares as arcs (`stroke-dasharray`) with the total in the middle |
| `x-chart.spark` | Sparklines in the tiles and the table |
| `x-ring`, `x-bar` | Exist already; reused for Left to spend and Budget vs real |

- The geometry (scales, nice tick values like R20k, arcs) lives in `App\Support\Charts` and is unit-tested
  without rendering.
- **Hover:** every bar, point and slice has an SVG `<title>`, so the Mac shows its value as a tooltip with no
  JavaScript. A later step can add a small `public/js/overview.js` for an Apple-style hover readout (a
  vertical line with the month's numbers), still allowed by the policy.
- **Accessible:** each chart has `role="img"` and an `aria-label` that says its numbers; the table holds the
  per-line numbers; colours are never the only signal; a `forced-colors` block as for the bars.

## 7. Steps

Each step is a branch merged into `main` with `composer check` green, and leaves the app working.

| # | Step | Visible change | Tests |
|---|---|---|---|
| 1 | `MonthSummary`; Home reads from it | None | Sums and exclusions; Home tests unchanged |
| 2 | Groups: column, guess, select on Budget, headings in pasted budgets | Group select on Budget | Guess, saving, `BudgetList` with headings |
| 3 | Overview screen with the look: tiles, Budget vs real, Budget lines table (no sparklines yet); sidebar item | New screen | Each panel's numbers; excluded transactions; empty household |
| 4 | `Trends` and the charts: Spending and budget, Where it went, This month's pace, sparklines; range control | Charts | `Charts` geometry; `Trends` month bucketing with a start day of 25; chart labels carry the numbers |
| 5 | Savings with a savings account; the Saved tile | Saved tile, Savings real amounts | Transfers count as saved, never as spent |
| 6 | Biggest changes | Panel | The thresholds and the ordering |
| 7 | Polish: hover readout script, check at 1280, 1440 and 1920 wide in Safari, light and dark | Hover readout | Browser screenshots at those widths |

Steps 1 to 4 give the screen most of its value.

## 8. Not planned

- Editing amounts on the Overview; that stays on the Budget screen, one click away from each line.
- A transaction filter on the Overview; Transactions already filters, and a click on a line, bar or month
  opens Transactions filtered to it.
- A chart library or a JavaScript build step.

## 9. Questions

1. **Groups:** are Bills, Everyday, Subscriptions and Savings the right four for your budget lines?
2. **Savings:** count transfers into the money market account as saved (4.2), or only what is categorised to a
   Savings line by hand?
3. **Font:** keep Geist (as in the mockup), or use the Mac's own SF Pro (`-apple-system`) on the Overview, or
   on every screen, which would also change the look on iPhone?
4. **Past budgets:** the dashed budget line uses today's budget for every month. Keeping a copy of the budget
   each month (a small `budget_snapshots` table) would make it exact from now on; worth it?
5. **Landing page:** on the Mac, open on the Overview instead of Home?
