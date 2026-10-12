{{-- How a tile's number compares with last month: "4% less than Sep by now". Nothing when last month had none. --}}
@if ($change !== null)
    <p class="ov-foot"><span @class(['ov-chip', $change['tone']])>{{ $change['pct'] === 0 ? 'Same as '.$lastMonthName : abs($change['pct']).'% '.($change['pct'] > 0 ? $more : $less).' than '.$lastMonthName }}{{ $soFar }}</span></p>
@else
    <p class="ov-note">Nothing to compare with in {{ $lastMonthName }}</p>
@endif
