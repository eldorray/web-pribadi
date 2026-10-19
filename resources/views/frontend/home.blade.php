<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="($settings['site_name'] ?? 'Fahmie Al Khudhorie') . ' — Desain & Teknologi'">
    @php
        $displayName = trim(explode(',', $settings['hero_title_2'] ?? $settings['site_name'] ?? 'Fahmie Al Khudhorie')[0]);
    @endphp
    <section class="pf-panel pf-hero">
        <div class="pf-identity">
            <div class="pf-avatar">
                @if (!empty($settings['about_portrait']))
                    <img src="{{ $settings['about_portrait'] }}" alt="Potret {{ $displayName }}" width="48" height="48" fetchpriority="high" decoding="async" />
                @else
                    <span>{{ mb_substr($displayName, 0, 1) }}</span>
                @endif
            </div>
            <div><p class="pf-identity__name">{{ $displayName }}</p><p class="pf-identity__role">Design Engineer · Pendidikan & Teknologi</p></div>
        </div>
        <h1>Desain bermakna.<br class="pf-mobile-break" /> Teknologi berguna.</h1>
        <div class="pf-hero__bottom">
            <p>{{ ($settings['hero_subtitle'] ?? '') ?: 'Saya membangun produk digital untuk menyederhanakan pendidikan dan pekerjaan sehari-hari.' }}</p>
            <a class="pf-explore" href="#karya">Jelajahi karya <x-icon name="arrow_downward" :size="20" /></a>
        </div>
    </section>

    <section class="pf-works" id="karya" aria-labelledby="featured-title">
        <div class="pf-section-heading"><h2 id="featured-title">Karya pilihan</h2><a class="pf-text-link" href="{{ route('projects') }}">Lihat semua karya <x-icon name="arrow_outward" :size="14" /></a></div>
        @if ($featuredProjects->isNotEmpty())
            <div class="pf-project-grid">
                @foreach ($featuredProjects as $project)
                    <x-project-card :project="$project" :index="$loop->iteration" />
                @endforeach
            </div>
        @else
            <div class="pf-panel pf-empty"><x-icon name="folder_open" :size="32" /><h3>Karya baru sedang disiapkan.</h3><p>Sementara itu, mari berkenalan dan bicarakan ide Anda.</p><a href="{{ route('about') }}" class="pf-text-link">Tentang saya <x-icon name="arrow_outward" :size="15" /></a></div>
        @endif
    </section>

    @if (!empty($settings['github_username']) || $tools->isNotEmpty())
        <div @class(['pf-activity', 'pf-activity--single' => empty($settings['github_username']) || $tools->isEmpty()])>
            <x-github-heatmap :username="$settings['github_username'] ?? null" />
            @if ($tools->isNotEmpty())
                <section class="pf-panel pf-tools" aria-labelledby="tools-title">
                    <h2 id="tools-title">Tools I Use</h2>
                    <p>Untuk merancang, membangun, dan bereksperimen.</p>
                    <div class="pf-tool-grid">
                        @foreach ($tools as $tool)
                            <div class="pf-tool" data-tool="{{ Str::slug($tool->name) }}">
                                <div class="pf-tool__icon">
                                    @if ($tool->icon_url)
                                        <img src="{{ $tool->icon_url }}" alt="" width="26" height="26" loading="lazy" decoding="async" />
                                    @elseif ($tool->icon_svg)
                                        {!! $tool->icon_svg !!}
                                    @else
                                        <x-icon name="code" :size="24" />
                                    @endif
                                </div>
                                <span>{{ $tool->name }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    @endif

    <section class="pf-panel pf-about-preview">
        <div><p class="pf-eyebrow">Sedikit tentang saya</p><h2>Berangkat dari pendidikan.<br />Berkarya lewat teknologi.</h2></div>
        <div><p>{{ Str::limit(($settings['about_text_1'] ?? '') ?: (($settings['about_page_intro'] ?? '') ?: 'Saya merancang dan membangun produk digital untuk membuat proses pendidikan dan administrasi lebih sederhana, efisien, dan bermanfaat.'), 260) }}</p><div class="pf-about-preview__bottom"><p>Web Design <span>/</span> Front-end <span>/</span> AI-assisted Development</p><a href="{{ route('about') }}" class="pf-text-link">Kenali lebih dekat <x-icon name="arrow_outward" :size="14" /></a></div></div>
    </section>

    <x-contact-banner />
</x-layouts.frontend>
