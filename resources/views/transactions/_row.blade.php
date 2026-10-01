{{-- One transaction in a list: a tile in the colour of whose it is (amber while it still needs a category),
     the name, what it is for, and the amount. --}}
@php
    $toReview = ! $t->is_transfer && $t->category_id === null && $t->person_id === null && $t->project_id === null;
    $colour = $t->ownerColour();
@endphp
<a class="item {{ $colour ? 'owner-'.$colour : 'owner-none' }}" href="{{ route('transactions.edit', $t) }}">
    <span @class(['tile', 'owned' => $colour !== null && ! $toReview, 'todo' => $toReview])>
        @if ($t->is_transfer)
            <x-icon name="transfer" />
        @elseif ($toReview)
            <x-icon name="question" />
        @elseif ($t->category)
            <x-category-icon :icon="$t->category->iconName()" :size="22" />
        @else
            <x-icon :name="$t->project_id ? 'folder' : 'people'" />
        @endif
    </span>
    <span class="item-main">
        <span class="item-title clip">{{ $t->displayName() }}</span>
        @if ($t->order)
            <span class="item-line clip">{{ $t->order->summary() }}</span>
        @endif
        <span class="item-sub clip">
            @if ($t->is_transfer) Between own accounts
            @elseif ($t->project) <span class="who">{{ $t->project->name }}</span>
            @elseif ($t->person) <span class="who">{{ $t->person->name }}</span>
            @elseif ($t->category) {{ $t->category->name }}
            @else <span class="who todo">Not categorised</span>
            @endif
            · {{ $showDate ?? false ? $t->posted_on->format('j M').' · ' : '' }}{{ $t->account->name }}
        </span>
    </span>
    <span @class(['amount', 'in' => $t->amount_cents > 0 && ! $t->is_transfer, 'muted' => $t->is_transfer])>{{ $t->amount_cents > 0 ? '+' : '' }}{{ money($t->amount_cents) }}</span>
</a>
