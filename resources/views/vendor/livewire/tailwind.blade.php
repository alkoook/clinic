@php
    $scrollTo ??= 'body';
    $scrollIntoView = $scrollTo === false
        ? ''
        : "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView({ behavior: 'smooth', block: 'start' })";
    $pageName = $paginator->getPageName();
@endphp

@if ($paginator->hasPages())
    <nav
        class="clinic-pagination"
        role="navigation"
        aria-label="التنقل بين الصفحات"
        wire:loading.class="is-loading"
        wire:target="previousPage,nextPage,gotoPage"
    >
        <p class="pagination-summary" aria-live="polite">
            عرض <strong>{{ $paginator->firstItem() }}</strong> إلى <strong>{{ $paginator->lastItem() }}</strong>
            من أصل <strong>{{ $paginator->total() }}</strong> نتيجة
        </p>

        <div class="pagination-pages">
            @if ($paginator->onFirstPage())
                <span class="pagination-btn pagination-btn-wide" aria-disabled="true">
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m7.5 4.5 5 5.5-5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    السابق
                </span>
            @else
                <button class="pagination-btn pagination-btn-wide" type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" x-on:click="{{ $scrollIntoView }}" aria-label="الصفحة السابقة">
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m7.5 4.5 5 5.5-5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    السابق
                </button>
            @endif

            <span class="pagination-numbers contents">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pagination-ellipsis" aria-hidden="true">{{ $element }}</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page === $paginator->currentPage())
                                <span class="pagination-btn" aria-current="page" aria-label="الصفحة الحالية، {{ $page }}">{{ $page }}</span>
                            @else
                                <button class="pagination-btn" type="button" wire:key="paginator-{{ $pageName }}-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $pageName }}')" wire:loading.attr="disabled" x-on:click="{{ $scrollIntoView }}" aria-label="الانتقال إلى الصفحة {{ $page }}">{{ $page }}</button>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            @if ($paginator->hasMorePages())
                <button class="pagination-btn pagination-btn-wide" type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" x-on:click="{{ $scrollIntoView }}" aria-label="الصفحة التالية">
                    التالي
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12.5 4.5-5 5.5 5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            @else
                <span class="pagination-btn pagination-btn-wide" aria-disabled="true">
                    التالي
                    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="m12.5 4.5-5 5.5 5 5.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
            @endif

            <span class="pagination-spinner" wire:loading wire:target="previousPage,nextPage,gotoPage" role="status" aria-label="جارٍ تحميل الصفحة"></span>
        </div>
    </nav>
@endif
