{{-- How a tile's number compares with last month: "4% less than Sep" (up to the same day while this month runs). Nothing when last month had none. --}}
@if ($change !== null)
    <span @class(['ov-chip', $change['tone']]) @if ($soFar) title="Compared with {{ $lastMonthName }} up to the same day" @endif>{{ $change['pct'] === 0 ? 'Same as '.$lastMonthName : abs($change['pct']).'% '.($change['pct'] > 0 ? $more : $less).' than '.$lastMonthName }}@if ($soFar)<span class="visually-hidden">{{ $soFar }}</span>@endif</span>
@else
    <span class="ov-note">Nothing to compare with in {{ $lastMonthName }}</span>
@endif
