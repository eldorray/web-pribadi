@php
    $stats = \App\Services\VisitorTracker::stats();
@endphp

<div class="visitor-counter" aria-label="Visitor counter">
    <span class="visitor-counter__icon material-symbols-outlined" aria-hidden="true">groups</span>
    <span class="visitor-counter__total">
        {{ number_format($stats['total']) }}
        <span class="visitor-counter__label">{{ $stats['total'] === 1 ? 'visitor' : 'visitors' }}</span>
    </span>
    @if (count($stats['countries']) > 0)
        <span class="visitor-counter__divider" aria-hidden="true">·</span>
        <span class="visitor-counter__flags" role="list" aria-label="Visitor countries">
            @foreach ($stats['countries'] as $country)
                <span class="visitor-counter__flag" role="listitem"
                    title="{{ $country['code'] }} — {{ number_format($country['count']) }}">
                    {{ $country['flag'] }}
                </span>
            @endforeach
        </span>
    @endif
</div>
