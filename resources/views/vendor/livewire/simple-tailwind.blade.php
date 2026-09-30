@php
    $scrollTo ??= 'body';
    $scrollIntoView = $scrollTo === false
        ? ''
        : "(\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView({ behavior: 'smooth', block: 'start' })";
    $pageName = $paginator->getPageName();
@endphp

@if ($paginator->hasPages())
    <nav class="clinic-pagination" role="navigation" aria-label="التنقل بين الصفحات">
        <div class="pagination-pages w-full justify-between">
            @if ($paginator->onFirstPage())
                <span class="pagination-btn pagination-btn-wide" aria-disabled="true">السابق</span>
            @else
                <button class="pagination-btn pagination-btn-wide" type="button" wire:click="previousPage('{{ $pageName }}')" wire:loading.attr="disabled" x-on:click="{{ $scrollIntoView }}">السابق</button>
            @endif
            @if ($paginator->hasMorePages())
                <button class="pagination-btn pagination-btn-wide" type="button" wire:click="nextPage('{{ $pageName }}')" wire:loading.attr="disabled" x-on:click="{{ $scrollIntoView }}">التالي</button>
            @else
                <span class="pagination-btn pagination-btn-wide" aria-disabled="true">التالي</span>
            @endif
        </div>
    </nav>
@endif
