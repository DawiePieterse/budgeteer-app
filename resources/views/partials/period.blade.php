<nav @class(['period', $class ?? '']) aria-label="Budget month">
    <a href="{{ route($route, ['in' => $period->previous()->from->toDateString()]) }}" aria-label="Previous month"><x-icon name="back" /></a>
    <span class="period-label">{{ $period->label() }}</span>
    <a href="{{ route($route, ['in' => $period->next()->from->toDateString()]) }}" aria-label="Next month"><x-icon name="forward" /></a>
</nav>
