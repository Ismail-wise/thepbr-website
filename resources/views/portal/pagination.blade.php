@if($paginator->hasPages())
<nav class="page-actions" aria-label="စာမျက်နှာများ">
    @unless($paginator->onFirstPage())<a class="btn secondary sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">← အရှေ့စာမျက်နှာ</a>@endunless
    <span class="small muted">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="btn secondary sm" href="{{ $paginator->nextPageUrl() }}" rel="next">နောက်စာမျက်နှာ →</a>@endif
</nav>
@endif
