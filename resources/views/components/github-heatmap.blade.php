@props(['username' => null])
@php
    $data = $username ? \App\Services\GithubContributions::fetch($username) : null;
@endphp
@if ($username)
    <section class="pf-panel pf-github" aria-labelledby="github-title">
        <div class="pf-github__heading">
            <h2 id="github-title">GitHub Contributions</h2>
            <a href="https://github.com/{{ $username }}" target="_blank" rel="noopener noreferrer" class="pf-text-link"><span>&#64;{{ $username }}</span> <x-icon name="arrow_outward" :size="13" /></a>
        </div>
        @if ($data)
            <p class="pf-github__total">{{ number_format($data['total'], 0, ',', '.') }} kontribusi dalam setahun terakhir</p>
            <div class="pf-gh-grid" style="--week-count: {{ count($data['weeks']) }}; --mobile-week-count: {{ min(26, count($data['weeks'])) }};" role="img" aria-label="Aktivitas kontribusi GitHub {{ $username }}. Semakin gelap warna, semakin banyak kontribusi.">
                @foreach ($data['weeks'] as $week)
                    <div @class(['pf-gh-week', 'pf-gh-week--older' => $loop->index < count($data['weeks']) - 26])>
                        @foreach ($week as $day)
                            @if (empty($day['empty']))
                                <span class="pf-gh-cell" data-level="{{ $day['level'] }}" title="{{ $day['count'] }} kontribusi pada {{ $day['date'] }}"></span>
                            @else
                                <span class="pf-gh-cell pf-gh-cell--empty"></span>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="pf-github__foot">
                <p><span class="pf-github__mobile-note">{{ min(26, count($data['weeks'])) }} minggu terakhir · </span>Snapshot dari website pribadi</p>
                <div class="pf-gh-legend" aria-hidden="true"><span>Sedikit</span>@for ($level = 0; $level < 5; $level++)<span class="pf-gh-cell" data-level="{{ $level }}"></span>@endfor<span>Banyak</span></div>
            </div>
        @else
            <div class="pf-github__empty">
                <x-icon name="cloud_off" :size="28" />
                <p>Aktivitas GitHub belum dapat dimuat.</p>
                <a href="https://github.com/{{ $username }}" target="_blank" rel="noopener noreferrer" class="pf-text-link">Lihat aktivitas di GitHub <x-icon name="arrow_outward" :size="14" /></a>
            </div>
        @endif
    </section>
@endif
