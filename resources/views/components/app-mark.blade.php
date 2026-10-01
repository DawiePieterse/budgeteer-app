@props(['small' => false])
<span @class(['app-mark', 'sm' => $small]) aria-hidden="true"><svg width="{{ $small ? 22 : 40 }}" height="{{ $small ? 22 : 40 }}" viewBox="0 0 40 40" fill="none" focusable="false"><circle cx="20" cy="20" r="13" stroke="#5ec4b4" stroke-width="5"/><path d="M20 7a13 13 0 0 1 12.4 16.9" stroke="#ffffff" stroke-width="5" stroke-linecap="round"/></svg></span>
