@props([
    'username' => null,
])

@php
    $data = $username ? \App\Services\GithubContributions::fetch($username) : null;
@endphp

@if ($username)
    <section class="surface p-6 sm:p-10" data-reveal>
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-6">
            <div>
                <h2 class="display display--md mb-1" style="font-size: 1.375rem; font-weight: 700;">
                    GitHub Contributions
                </h2>
                <p class="text-sm" style="color: var(--color-ink-4);">
                    @if ($data)
                        {{ number_format($data['total']) }} contributions in the last year
                    @else
                        @{{ $username }}
                    @endif
                </p>
            </div>
            <a href="https://github.com/{{ $username }}" target="_blank" rel="noopener"
                class="text-sm font-medium inline-flex items-center gap-1 hover:underline self-start sm:self-auto"
                style="color: var(--color-ink-3);">
                View GitHub
                <span class="material-symbols-outlined text-[14px]">arrow_outward</span>
            </a>
        </div>

        @if ($data)
            {{-- Heatmap --}}
            <div class="overflow-x-auto scrollbar-hide -mx-2 px-2">
                <div class="gh-heatmap">
                    <div class="gh-months">
                        @foreach ($data['months'] as $m)
                            <span class="gh-month" style="--col: {{ $m['col'] }};">{{ $m['label'] }}</span>
                        @endforeach
                    </div>
                    <div class="gh-days">
                        <span style="grid-row: 2;">Mon</span>
                        <span style="grid-row: 4;">Wed</span>
                        <span style="grid-row: 6;">Fri</span>
                    </div>
                    <div class="gh-grid">
                        @foreach ($data['weeks'] as $week)
                            @foreach ($week as $day)
                                @if (empty($day['empty']))
                                    <span class="gh-cell" data-level="{{ $day['level'] }}"
                                        title="{{ $day['count'] }} contributions on {{ $day['date'] }}"></span>
                                @else
                                    <span class="gh-cell gh-cell--empty"></span>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between mt-5 text-xs" style="color: var(--color-ink-5);">
                <a href="https://docs.github.com/en/account-and-profile/setting-up-and-managing-your-github-profile/managing-contribution-settings-on-your-profile/viewing-contributions-on-your-profile"
                    target="_blank" rel="noopener" class="hover:underline" style="color: var(--color-ink-4);">
                    Learn how GitHub counts contributions
                </a>
                <div class="flex items-center gap-1.5">
                    <span>Less</span>
                    <span class="gh-cell gh-cell--legend" data-level="0"></span>
                    <span class="gh-cell gh-cell--legend" data-level="1"></span>
                    <span class="gh-cell gh-cell--legend" data-level="2"></span>
                    <span class="gh-cell gh-cell--legend" data-level="3"></span>
                    <span class="gh-cell gh-cell--legend" data-level="4"></span>
                    <span>More</span>
                </div>
            </div>
        @else
            {{-- Fallback when fetch fails --}}
            <div class="py-12 text-center">
                <span class="material-symbols-outlined text-4xl mb-3 block"
                    style="color: var(--color-ink-5);">cloud_off</span>
                <p class="text-sm mb-1" style="color: var(--color-ink-3);">Couldn't load contribution data right now.
                </p>
                <p class="text-xs" style="color: var(--color-ink-5);">
                    Check <a href="https://github.com/{{ $username }}" target="_blank" rel="noopener"
                        class="underline">github.com/{{ $username }}</a> directly,
                    or run <code class="px-1.5 py-0.5 rounded text-[11px]"
                        style="background: var(--color-card-soft);">php artisan github:diagnose</code> on your server.
                </p>
            </div>
        @endif
    </section>
@endif
