<?php

namespace App\Http\Controllers;

use App\Enums\TransactionKind;
use App\Models\Category;
use App\Models\Merchant;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Categorise by merchant, not by transaction: everything not yet categorised is grouped by
 * merchant, biggest first, and one choice applies to the whole group and is remembered.
 */
class CategoriseController extends Controller
{
    public const TRANSFER = 'transfer';

    /** Choice value prefix for a project: "project:3". */
    public const PROJECT = 'project:';

    public function index(): View
    {
        $groups = Transaction::query()
            ->whereNull('category_id')
            ->where('is_transfer', false)
            ->whereNull('person_id')->whereNull('project_id')
            ->selectRaw('merchant_key, amount_cents > 0 as money_in, count(*) as n, sum(amount_cents) as total, min(posted_on) as first_on, max(posted_on) as last_on, min(description) as example')
            ->groupBy('merchant_key', 'money_in')
            ->orderByRaw('abs(sum(amount_cents)) desc')
            ->limit(30)
            ->get();

        $spendingCategories = Category::query()->where('kind', 'expense')->get();
        $groups->each(function ($group) use ($spendingCategories) {
            $group->suggested = $group->money_in ? null : self::suggest((string) $group->merchant_key, (string) $group->example, $spendingCategories);
        });

        return view('categorise', [
            'groups' => $groups,
            'remaining' => Transaction::query()->whereNull('category_id')->where('is_transfer', false)->whereNull('person_id')->whereNull('project_id')->count(),
            'categories' => Category::query()->orderBy('kind')->orderBy('sort')->get()->groupBy(fn (Category $c) => $c->kind->value),
            'projects' => Project::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'merchant_key' => ['required', 'string', 'max:100'],
            'money_in' => ['required', 'boolean'],
            'category' => ['required', Rule::in([
                self::TRANSFER,
                ...Category::query()->pluck('id')->map(fn ($id) => (string) $id)->all(),
                ...Project::query()->pluck('id')->map(fn ($id) => self::PROJECT.$id)->all(),
            ])],
        ]);

        $transactions = Transaction::query()
            ->whereNull('category_id')
            ->where('is_transfer', false)
            ->whereNull('person_id')->whereNull('project_id')
            ->where('merchant_key', $data['merchant_key'])
            ->where('amount_cents', $data['money_in'] ? '>' : '<=', 0);

        if (str_starts_with((string) $data['category'], self::PROJECT)) {
            $project = Project::query()->findOrFail((int) substr((string) $data['category'], strlen(self::PROJECT)));
            $count = $transactions->update(['project_id' => $project->id, 'updated_by' => $request->user()->id]);
            Merchant::updateOrCreate(['key' => $data['merchant_key']], ['project_id' => $project->id]);

            return back()->with('status', "{$count} moved to the {$project->name} project; later ones from the same place go there too.");
        }

        if ($data['category'] === self::TRANSFER) {
            $count = $transactions->update(['is_transfer' => true, 'kind' => TransactionKind::Transfer, 'updated_by' => $request->user()->id]);

            return back()->with('status', "{$count} marked as moving money between your own accounts.");
        }

        $count = $transactions->update(['category_id' => (int) $data['category'], 'updated_by' => $request->user()->id]);
        $merchant = Merchant::firstOrNew(['key' => $data['merchant_key']]);
        $merchant->fill(['category_id' => (int) $data['category'], 'times_confirmed' => $merchant->times_confirmed + 1])->save();

        return back()->with('status', "{$count} ".($count === 1 ? 'transaction' : 'transactions').' categorised.');
    }

    /**
     * The budget line whose name shares a distinctive word with the merchant, for example PPS with
     * "PPS (life cover / risk component)" or AFRIHOST with "Afrihost internet and cellphones".
     *
     * @param  Collection<int, Category>  $categories
     */
    public static function suggest(string $key, string $example, $categories): ?int
    {
        $words = fn (string $text) => array_filter(
            preg_split('/[^\p{L}\p{N}]+/u', mb_strtoupper($text)) ?: [],
            fn (string $w) => mb_strlen($w) >= 3 && ! in_array($w, ['THE', 'AND', 'FOR', 'VAN', 'DIE', 'CAPE', 'TOWN', 'PTY', 'LTD', 'SERVICES', 'PAYMENT', 'MONTHLY'], true),
        );
        $merchant = array_unique([...$words($key), ...array_slice(array_values($words($example)), 0, 3)]);
        foreach ($categories as $category) {
            foreach ($words($category->name) as $word) {
                foreach ($merchant as $m) {
                    if ($m === $word || (mb_strlen($word) >= 5 && str_starts_with($m, $word)) || (mb_strlen($m) >= 5 && str_starts_with($word, $m))) {
                        return $category->id;
                    }
                }
            }
        }

        return null;
    }
}
