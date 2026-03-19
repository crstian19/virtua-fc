@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="flex items-center justify-between gap-4">
        {{-- Mobile: prev/next only --}}
        <div class="flex flex-1 items-center justify-between sm:hidden">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-text-muted bg-surface-800 border border-border-default rounded-lg cursor-not-allowed min-h-[44px]">
                    {!! __('pagination.previous') !!}
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center px-3 py-2 text-sm font-medium text-text-secondary bg-surface-700 border border-border-default rounded-lg hover:bg-surface-600 transition min-h-[44px]">
                    {!! __('pagination.previous') !!}
                </a>
            @endif

            <span class="text-sm text-text-muted">
                {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center px-3 py-2 text-sm font-medium text-text-secondary bg-surface-700 border border-border-default rounded-lg hover:bg-surface-600 transition min-h-[44px]">
                    {!! __('pagination.next') !!}
                </a>
            @else
                <span class="inline-flex items-center px-3 py-2 text-sm font-medium text-text-muted bg-surface-800 border border-border-default rounded-lg cursor-not-allowed min-h-[44px]">
                    {!! __('pagination.next') !!}
                </span>
            @endif
        </div>

        {{-- Desktop: full pagination --}}
        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
            <p class="text-sm text-text-muted">
                @if ($paginator->firstItem())
                    <span class="font-medium text-text-secondary">{{ $paginator->firstItem() }}</span>
                    &ndash;
                    <span class="font-medium text-text-secondary">{{ $paginator->lastItem() }}</span>
                    {{ __('of') }}
                    <span class="font-medium text-text-secondary">{{ $paginator->total() }}</span>
                @else
                    {{ $paginator->count() }} {{ __('of') }} {{ $paginator->total() }}
                @endif
            </p>

            <span class="inline-flex items-center gap-1">
                {{-- Previous --}}
                @if ($paginator->onFirstPage())
                    <span aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                        <span class="inline-flex items-center justify-center size-9 text-text-muted bg-surface-800 border border-border-default rounded-lg cursor-not-allowed" aria-hidden="true">
                            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                        </span>
                    </span>
                @else
                    <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="inline-flex items-center justify-center size-9 text-text-secondary bg-surface-700 border border-border-default rounded-lg hover:bg-surface-600 transition" aria-label="{{ __('pagination.previous') }}">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                    </a>
                @endif

                {{-- Page Numbers --}}
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-disabled="true">
                            <span class="inline-flex items-center justify-center size-9 text-sm text-text-muted cursor-default">{{ $element }}</span>
                        </span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page">
                                    <span class="inline-flex items-center justify-center size-9 text-sm font-semibold text-text-primary bg-accent-blue/20 border border-accent-blue/30 rounded-lg cursor-default">{{ $page }}</span>
                                </span>
                            @else
                                <a href="{{ $url }}" class="inline-flex items-center justify-center size-9 text-sm text-text-secondary bg-surface-700 border border-border-default rounded-lg hover:bg-surface-600 transition" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">
                                    {{ $page }}
                                </a>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next --}}
                @if ($paginator->hasMorePages())
                    <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="inline-flex items-center justify-center size-9 text-text-secondary bg-surface-700 border border-border-default rounded-lg hover:bg-surface-600 transition" aria-label="{{ __('pagination.next') }}">
                        <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                    </a>
                @else
                    <span aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                        <span class="inline-flex items-center justify-center size-9 text-text-muted bg-surface-800 border border-border-default rounded-lg cursor-not-allowed" aria-hidden="true">
                            <svg class="size-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" /></svg>
                        </span>
                    </span>
                @endif
            </span>
        </div>
    </nav>
@endif
