<?php

namespace App\Http\Controllers;

use App\Enums\CategoryKind;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Merchant;
use App\Models\Transaction;
use App\Services\BudgetList;
use App\Services\BudgetPeriod;
use App\Statements\Money;
use App\Support\CategoryIcons;
use App\Support\GroupColours;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BudgetController extends Controller
{
    public function edit(Request $request): View
    {
        $period = BudgetPeriod::containing(CarbonImmutable::today(), $request->user()->household->period_start_day);
        $categories = Category::query()->orderBy('kind')->orderBy('sort')->orderBy('name')->get();
        $used = Transaction::query()->whereNotNull('category_id')->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        return view('budget', [
            'categories' => $categories,
            'groups' => CategoryGroup::query()->orderBy('sort')->orderBy('name')->get(),
            'used' => $used,
            'total' => (int) $categories->where('kind', CategoryKind::Expense)->sum('budget_cents'),
            'period' => $period,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'budget' => ['array'],
            'budget.*' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'name' => ['array'],
            'name.*' => ['required', 'string', 'max:100'],
            'icon' => ['array'],
            'icon.*' => ['nullable', Rule::in(array_keys(CategoryIcons::ICONS))],
            'group' => ['array'],
            'group.*' => ['nullable', Rule::in(CategoryGroup::query()->pluck('id')->all())],
            'new_name' => ['nullable', 'string', 'max:100'],
            'new_kind' => ['nullable', Rule::enum(CategoryKind::class)],
            'new_budget' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        foreach (Category::query()->whereIn('id', array_keys($data['name'] ?? []))->get() as $category) {
            $amount = $data['budget'][$category->id] ?? null;
            $category->update([
                'name' => trim($data['name'][$category->id]),
                'budget_cents' => $amount === null || $amount === '' ? null : (int) round(((float) $amount) * 100),
                'icon' => array_key_exists($category->id, $data['icon'] ?? []) ? ($data['icon'][$category->id] ?: null) : $category->icon,
                'category_group_id' => array_key_exists($category->id, $data['group'] ?? []) ? ((int) $data['group'][$category->id] ?: null) : $category->category_group_id,
            ]);
        }
        if (filled($data['new_name'] ?? null)) {
            Category::firstOrCreate(['name' => trim((string) $data['new_name'])], [
                'kind' => $data['new_kind'] ?? CategoryKind::Expense->value,
                'budget_cents' => filled($data['new_budget'] ?? null) ? (int) round(((float) $data['new_budget']) * 100) : null,
                'sort' => (int) Category::query()->max('sort') + 1,
            ]);
        }

        return back()->with('status', 'Budget saved.');
    }

    /** A budget pasted from a spreadsheet: each line becomes a category with its monthly amount. */
    public function paste(Request $request): RedirectResponse
    {
        $request->validate(['list' => ['required', 'string', 'max:20000']]);
        $items = BudgetList::parse($request->string('list'));
        if ($items === []) {
            return back()->with('error', 'No lines with an amount were found. Put each item on its own line with the amount at the end.');
        }

        $created = 0;
        $sort = (int) Category::query()->max('sort');
        foreach ($items as $item) {
            $category = Category::query()->whereRaw('lower(name) = ?', [mb_strtolower($item['name'])])->first();
            if ($category === null) {
                $category = Category::create(['name' => mb_substr($item['name'], 0, 100), 'kind' => CategoryKind::Expense, 'sort' => ++$sort]);
                $created++;
            }
            $category->update(['budget_cents' => $item['cents']] + ($item['group'] !== null ? ['category_group_id' => CategoryGroup::named($item['group'])->id] : []));
        }

        $total = array_sum(array_column($items, 'cents'));

        return back()->with('status', count($items)." budget lines read ({$created} new categories), R".number_format($total / 100, 2, '.', ',').' a month in all. Now move the starter categories you no longer need into yours, below.');
    }

    /**
     * Starts the spending categories again from a pasted budget: every spending category and every
     * remembered merchant choice goes, all transactions are uncategorised (transactions themselves
     * stay), and each budget line becomes a category. Money-in categories and projects are kept.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'list' => ['required', 'string', 'max:20000'],
            'confirm' => ['accepted'],
        ]);
        $items = BudgetList::parse($request->string('list'));
        if ($items === []) {
            return back()->with('error', 'No lines with an amount were found, so nothing was changed.');
        }

        DB::transaction(function () use ($items) {
            $spending = Category::query()->where('kind', CategoryKind::Expense)->pluck('id');
            Transaction::query()->whereNotNull('category_id')->whereIn('category_id', $spending)->update(['category_id' => null]);
            Merchant::query()->whereNull('project_id')->delete();
            Merchant::query()->update(['category_id' => null]);
            Category::query()->whereIn('id', $spending)->delete();
            foreach ($items as $sort => $item) {
                Category::create(['name' => mb_substr($item['name'], 0, 100), 'kind' => CategoryKind::Expense, 'budget_cents' => $item['cents'], 'sort' => $sort,
                    'category_group_id' => $item['group'] !== null ? CategoryGroup::named($item['group'])->id : null]);
            }
        });

        return redirect()->route('categorise')->with('status', count($items).' budget lines are now your categories. Assign each group below; Budgeteer suggests a line where the names match.');
    }

    /**
     * The household's groups: renamed, recoloured and added in one go; and, with the button pressed,
     * one moved up or removed. Removing a group leaves its lines without a group.
     */
    public function groups(Request $request): RedirectResponse
    {
        $groups = CategoryGroup::query()->orderBy('sort')->orderBy('name')->get();
        $data = $request->validate([
            'group_name' => ['array'],
            'group_name.*' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'group_colour' => ['array'],
            'group_colour.*' => [Rule::in(array_keys(GroupColours::CHOICES))],
            'new_group' => ['nullable', 'string', 'max:60'],
            'action' => ['nullable', 'string', 'regex:/^(up|remove):\d+$/'],
        ], ['group_name.*.distinct' => 'Two groups cannot have the same name.']);

        $names = collect($data['group_name'] ?? [])->map(fn ($name) => trim((string) $name));
        $new = trim((string) ($data['new_group'] ?? ''));
        $taken = $groups->mapWithKeys(fn (CategoryGroup $g) => [$g->id => mb_strtolower($names->get($g->id, $g->name))]);
        if ($new !== '' && $taken->contains(mb_strtolower($new))) {
            return back()->withInput()->withErrors(['new_group' => "There is already a group called \"{$new}\"."]);
        }

        DB::transaction(function () use ($groups, $names, $data, $new) {
            // Renamed groups first get a name no one types, so two can swap names without a clash.
            foreach ($groups->filter(fn (CategoryGroup $g) => $names->get($g->id, $g->name) !== $g->name) as $group) {
                $group->update(['name' => "\u{2060}{$group->id}"]);
            }
            foreach ($groups as $i => $group) {
                $group->update([
                    'name' => $names->get($group->id, $group->name),
                    'colour' => $data['group_colour'][$group->id] ?? $group->colour,
                    'sort' => $i, // numbered afresh, so moving up is a swap with the one before
                ]);
            }
            if ($new !== '') {
                CategoryGroup::create(['name' => $new]);
            }
        });

        [$action, $id] = isset($data['action']) ? explode(':', $data['action']) : [null, null];
        $group = $id !== null ? $groups->firstWhere('id', (int) $id) : null;
        if ($action === 'up' && $group !== null && $group->sort > 0) {
            $above = $groups->firstWhere('sort', $group->sort - 1);
            $above?->update(['sort' => $group->sort]);
            $group->update(['sort' => $group->sort - 1]);
        }
        if ($action === 'remove' && $group !== null) {
            $group->delete();

            return back()->with('status', "Group \"{$group->name}\" removed; its lines are now without a group.");
        }

        return back()->with('status', $new !== '' ? "Group \"{$new}\" added. Choose it under a line's name above." : 'Groups saved.');
    }

    /** Moves everything in one category into another and removes the first. */
    public function merge(Request $request): RedirectResponse
    {
        $ids = Category::query()->pluck('id')->all();
        $data = $request->validate([
            'from' => ['required', Rule::in($ids)],
            'into' => ['required', Rule::in($ids), 'different:from'],
        ]);
        $from = Category::query()->findOrFail($data['from']);
        $into = Category::query()->findOrFail($data['into']);

        DB::transaction(function () use ($from, $into) {
            Transaction::query()->where('category_id', $from->id)->update(['category_id' => $into->id]);
            Merchant::query()->where('category_id', $from->id)->update(['category_id' => $into->id]);
            // Fees, cash and interest are filed in their starter category by name; remember the new home instead.
            $kindKey = ['Bank fees' => 'BANK FEES', 'Cash' => 'CASH', 'Interest' => 'INTEREST'][$from->name] ?? null;
            if ($kindKey !== null) {
                Merchant::updateOrCreate(['key' => $kindKey], ['category_id' => $into->id]);
            }
            // The two budgets add up, so combining lines keeps the same total.
            if ($from->budget_cents !== null) {
                $into->update(['budget_cents' => (int) $into->budget_cents + $from->budget_cents]);
            }
            $from->delete();
        });

        return back()->with('status', "\"{$from->name}\" is now part of \"{$into->name}\"".($into->budget_cents !== null ? ', budget '.Money::format($into->budget_cents).'.' : '.'));
    }

    /** Removes categories that have no budget, no transactions and no remembered merchants. */
    public function tidy(): RedirectResponse
    {
        $removed = Category::query()
            ->whereNull('budget_cents')
            ->whereNotIn('id', Transaction::query()->whereNotNull('category_id')->select('category_id'))
            ->whereNotIn('id', Merchant::query()->whereNotNull('category_id')->select('category_id'))
            ->whereNotIn('name', ['Bank fees', 'Cash', 'Interest'])
            ->get();
        $removed->each->delete();

        return back()->with('status', $removed->count().' unused categories removed.');
    }
}
