@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages">
        @if ($paginator->onFirstPage())<span class="button outline placeholder" aria-hidden="true"></span>@else<a class="button outline" href="{{ $paginator->previousPageUrl() }}" rel="prev"><x-icon name="back" :size="20" /> Newer</a>@endif
        <span class="muted small">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())<a class="button outline" href="{{ $paginator->nextPageUrl() }}" rel="next">Older <x-icon name="forward" :size="20" /></a>@else<span class="button outline placeholder" aria-hidden="true"></span>@endif
    </nav>
@endif
