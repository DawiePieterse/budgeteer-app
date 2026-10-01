@php($p = $payment ?? null)
@php($prefix = $p ? 'r'.$p->id.'-' : 'new-')
<div class="pair">
    <div class="field"><label for="{{ $prefix }}name">Name</label><input type="text" name="name" id="{{ $prefix }}name" value="{{ $p?->name }}" maxlength="100" required></div>
    <div class="field"><label for="{{ $prefix }}match">Description contains</label><input type="text" name="match_text" id="{{ $prefix }}match" value="{{ $p?->match_text }}" maxlength="100" required placeholder="PPS"></div>
</div>
<div class="pair">
    <div class="field"><label for="{{ $prefix }}amount">Amount (R)</label><input type="number" class="money" name="amount" id="{{ $prefix }}amount" step="0.01" min="0" inputmode="decimal" value="{{ $p ? number_format($p->amount_cents / 100, 2, '.', '') : '' }}" required></div>
    <div class="field">
        <label for="{{ $prefix }}category">Budget line</label>
        <select name="category_id" id="{{ $prefix }}category">
            <option value="">None</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected($p?->category_id === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="pair">
    <div class="field">
        <label for="{{ $prefix }}frequency">How often</label>
        <select name="frequency" id="{{ $prefix }}frequency">
            @foreach (['monthly' => 'Monthly', 'weekly' => 'Weekly', 'yearly' => 'Yearly'] as $value => $label)
                <option value="{{ $value }}" @selected(($p?->frequency ?? 'monthly') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label for="{{ $prefix }}day">Day</label>
        <input type="number" name="day" id="{{ $prefix }}day" min="1" max="31" value="{{ $p?->day ?? 1 }}" required>
    </div>
</div>
<p class="hint">Day of the month; for weekly, 1 = Monday … 7 = Sunday.</p>
<div class="field">
    <label for="{{ $prefix }}month">Month (yearly only)</label>
    <input type="number" name="month" id="{{ $prefix }}month" min="1" max="12" value="{{ $p?->month }}">
</div>
<label class="check"><input type="hidden" name="amount_varies" value="0"><input type="checkbox" name="amount_varies" value="1" @checked($p?->amount_varies)> The amount changes every time (do not warn about it)</label>
@if ($p)
    <label class="check"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($p->active)> Still paying this</label>
@endif
