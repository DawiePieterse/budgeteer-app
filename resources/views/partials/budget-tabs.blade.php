<header class="page-head">
    <h1>Budget</h1>
</header>
<nav class="segmented" aria-label="Budget sections">
    <a href="{{ route('budget') }}" @if ($current === 'monthly') aria-current="page" @endif>Monthly</a>
    <a href="{{ route('recurring.index') }}" @if ($current === 'recurring') aria-current="page" @endif>Recurring</a>
    <a href="{{ route('projects.index') }}" @if ($current === 'projects') aria-current="page" @endif>Projects</a>
</nav>
