<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Models\Project;
use App\Models\Transaction;
use App\Support\OwnerColours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
        ]);
        $project = Project::firstOrCreate(['name' => trim($data['name'])], [
            'budget_cents' => filled($data['budget'] ?? null) ? (int) round(((float) $data['budget']) * 100) : null,
        ]);

        return redirect()->route('projects.show', $project)->with('status', "Project {$project->name} added. Move its payments here from Categorise or a transaction's page.");
    }

    public function index(): View
    {
        return view('projects.index', ['projects' => Project::query()->orderBy('name')->get()]);
    }

    public function show(Project $project): View
    {
        $transactions = $project->transactions()->with('account')->orderByDesc('posted_on')->orderByDesc('id')->get();

        return view('projects.show', [
            'project' => $project,
            'transactions' => $transactions,
            'spent' => -(int) $transactions->sum('amount_cents'),
            'byMonth' => $transactions->groupBy(fn (Transaction $t) => $t->posted_on->format('Y-m'))
                ->map(fn ($g) => -(int) $g->sum('amount_cents'))->sortKeysDesc(),
            'merchants' => Merchant::query()->where('project_id', $project->id)->pluck('key'),
        ]);
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'colour' => ['nullable', Rule::in(array_keys(OwnerColours::CHOICES))],
        ]);
        $project->update([
            'name' => trim($data['name']),
            'colour' => $data['colour'] ?? $project->colour,
            'budget_cents' => filled($data['budget'] ?? null) ? (int) round(((float) $data['budget']) * 100) : null,
        ]);

        return back()->with('status', 'Saved.');
    }

    /** Stop sending a merchant's payments to the project; those already there stay. */
    public function forgetMerchant(Project $project, string $key): RedirectResponse
    {
        Merchant::query()->where('project_id', $project->id)->where('key', $key)->update(['project_id' => null]);

        return back()->with('status', "New payments to {$key} no longer go to {$project->name}.");
    }
}
