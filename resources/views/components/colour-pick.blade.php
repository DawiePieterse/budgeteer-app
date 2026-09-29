@props(['current', 'name' => 'colour'])
<fieldset class="colour-pick">
    <legend>Colour on transactions</legend>
    @foreach (\App\Support\OwnerColours::CHOICES as $colour => $label)
        <label class="owner-chip owner-{{ $colour }}">
            <input type="radio" name="{{ $name }}" value="{{ $colour }}" @checked($current === $colour)> {{ $label }}
        </label>
    @endforeach
</fieldset>
