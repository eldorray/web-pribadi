<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'Tentang — ' . ($settings['site_name'] ?? 'Portfolio')">
    @php
        $displayName = trim(explode(',', $settings['hero_title_2'] ?? $settings['site_name'] ?? 'Fahmie Al Khudhorie')[0]);
        $portrait = ($settings['about_page_portrait'] ?? '') ?: ($settings['about_portrait'] ?? '');
    @endphp
    <section class="pf-panel pf-page-intro">
        <a href="{{ route('home') }}" class="pf-back"><x-icon name="arrow_back" :size="16" /> Kembali ke beranda</a>
        <div class="pf-page-intro__row"><h1>Di balik karya.</h1><p>Mengenal lebih dekat perjalanan, cara berpikir, dan hal-hal yang saya kerjakan.</p></div>
    </section>

    <section class="pf-panel pf-profile">
        <div class="pf-profile__portrait">
            @if ($portrait)
                <img src="{{ $portrait }}" alt="Potret {{ $displayName }}" width="480" height="560" fetchpriority="high" decoding="async" />
            @else
                <span class="pf-profile__initial" aria-hidden="true">{{ mb_substr($displayName, 0, 1) }}</span>
            @endif
            <span class="pf-profile__caption">Pendidikan & Teknologi</span>
        </div>
        <div class="pf-profile__copy">
            <p class="pf-eyebrow">Halo, saya {{ $displayName }}.</p>
            <h2>Berangkat dari pendidikan.<br />Berkarya lewat teknologi.</h2>
            <p class="pf-prose">{{ ($settings['about_page_intro'] ?? '') ?: 'Saya merancang dan membangun produk digital untuk membuat proses pendidikan dan pekerjaan sehari-hari lebih sederhana.' }}</p>
            @if (!empty($settings['about_page_bio']))
                <p class="pf-profile__belief">{{ $settings['about_page_bio'] }}</p>
            @endif
            @if (!empty($settings['about_text_2']))
                <p class="pf-prose pf-profile__more">{{ $settings['about_text_2'] }}</p>
            @endif
            <a href="{{ route('projects') }}" class="pf-text-link">Jelajahi karya saya <x-icon name="arrow_outward" :size="16" /></a>
        </div>
    </section>

    <section class="pf-stats" aria-label="Perjalanan dalam angka">
        <div class="pf-panel pf-stat"><strong>{{ $settings['about_page_years'] ?? '3+' }}</strong><span>Tahun berkarya</span><span class="pf-stat__index" aria-hidden="true">01</span></div>
        <div class="pf-panel pf-stat"><strong>{{ $settings['about_page_projects'] ?? '10+' }}</strong><span>Proyek dikerjakan</span><span class="pf-stat__index" aria-hidden="true">02</span></div>
        <div class="pf-panel pf-stat"><strong>{{ $settings['stat_3_value'] ?? '—' }}</strong><span>{{ $settings['stat_3_label'] ?? 'Jangkauan kolaborasi' }}</span><span class="pf-stat__index" aria-hidden="true">03</span></div>
    </section>

    @if ($skills->isNotEmpty())
        <section class="pf-expertise" aria-labelledby="skills-title">
            <div class="pf-section-heading"><h2 id="skills-title">Yang saya kerjakan</h2><p>Memadukan pemikiran, desain, dan kode.</p></div>
            <div class="pf-skill-grid">
                @foreach ($skills as $skill)
                    <article class="pf-panel pf-skill">
                        <div class="pf-skill__top"><span class="pf-skill__icon"><x-icon :name="$skill->icon" :size="24" /></span><span class="pf-eyebrow">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
                        <h3>{{ $skill->title }}</h3><p>{{ $skill->description }}</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($experiences->isNotEmpty())
        <section class="pf-panel pf-experience" aria-labelledby="experience-title">
            <div class="pf-experience__intro"><p class="pf-eyebrow">Sebuah perjalanan</p><h2 id="experience-title">Belajar, bertumbuh,<br />dan memberi dampak.</h2></div>
            <ol class="pf-timeline">
                @foreach ($experiences as $experience)
                    <li>
                        <span class="pf-timeline__dot" aria-hidden="true"></span>
                        <p class="pf-timeline__date">{{ $experience->start_year }} — {{ $experience->end_year }}</p>
                        <h3>{{ $experience->title }}</h3><p class="pf-timeline__company">{{ $experience->company }}</p>
                        @if ($experience->description)<p class="pf-timeline__description">{{ $experience->description }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <x-contact-banner title="Mari membuat sesuatu yang bermanfaat." />
</x-layouts.frontend>
