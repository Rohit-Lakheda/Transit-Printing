@if ($paginator->hasPages())
    <nav class="app-pagination" aria-label="{{ __('Pagination Navigation') }}">
        <div class="app-pagination__row">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary app-pagination__btn is-disabled">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary app-pagination__btn" rel="prev">Previous</a>
            @endif

            <span class="app-pagination__info">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary app-pagination__btn" rel="next">Next</a>
            @else
                <span class="btn btn-secondary app-pagination__btn is-disabled">Next</span>
            @endif
        </div>

        @if ($paginator->lastPage() > 1)
            <div class="app-pagination__pages">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="app-pagination__ellipsis">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="app-pagination__page is-active" aria-current="page">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" class="app-pagination__page" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>
        @endif
    </nav>
@endif
