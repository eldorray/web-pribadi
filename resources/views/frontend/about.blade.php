<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'About — ' . ($settings['site_name'] ?? 'Studio')">

    {{-- HERO --}}
    <section class="text-center pt-4 sm:pt-12 pb-6 sm:pb-8">
        <div class="animate-reveal delay-150 inline-block mb-5 sm:mb-6">
            <div class="w-16 h-16 sm:w-20 sm:h-20 lg:w-24 lg:h-24 rounded-full overflow-hidden mx-auto"
                style="background: var(--color-card-soft); border: 4px solid var(--color-card); box-shadow: var(--shadow-card), 0 0 0 1px var(--color-line);">
                @if (!empty($settings['about_page_portrait']))
                    <img src="{{ $settings['about_page_portrait'] }}" alt="Portrait" width="96" height="96"
                        fetchpriority="high" decoding="async" class="w-full h-full object-cover" />
                @elseif(!empty($settings['about_portrait']))
                    <img src="{{ $settings['about_portrait'] }}" alt="Portrait" width="96" height="96"
                        fetchpriority="high" decoding="async" class="w-full h-full object-cover" />
                @else
                    <div class="w-full h-full flex items-center justify-center text-2xl font-bold"
                        style="color: var(--color-ink);">
                        {{ strtoupper(substr($settings['site_name'] ?? 'M', 0, 1)) }}
                    </div>
                @endif
            </div>
        </div>

        <p class="eyebrow mb-3 animate-reveal delay-200">About Me</p>
        <h1 class="display display--xl max-w-3xl mx-auto mb-3 sm:mb-4 animate-reveal delay-250"
            style="font-size: clamp(1.75rem, 5vw, 3rem);">
            {{ $settings['site_name'] ?? 'Hello, I make digital things.' }}
        </h1>
        <p class="lede max-w-2xl mx-auto px-4 animate-reveal delay-300">
            {{ $settings['about_page_intro'] ?? 'Designer & developer based in Indonesia. I help brands and products connect with their audience through thoughtful, functional design.' }}
        </p>
    </section>

    {{-- BIO --}}
    <section class="section !mt-8 sm:!mt-12" data-reveal>
        <div class="surface p-5 sm:p-8 lg:p-12 max-w-3xl mx-auto">
            <p class="lede mb-5">
                {{ $settings['about_page_bio'] ?? "Over the last several years, I've partnered with forward-thinking brands to translate complex problems into elegant, editorial-grade digital solutions. I believe every pixel should serve a purpose and every interaction should tell a story." }}
            </p>
            <p class="lede">
                {{ $settings['about_text_2'] ?? 'Outside of client work I keep notebooks, take photos, and drink too much coffee. The work below is a tiny window into all of that.' }}
            </p>
        </div>
    </section>

    {{-- STATS --}}
    <section class="section" data-reveal>
        <div class="grid grid-cols-3 gap-2 sm:gap-3 lg:gap-5 max-w-4xl mx-auto">
            <div class="surface p-3 sm:p-5 lg:p-8 text-center">
                <p class="display display--lg" style="font-size: clamp(1.25rem, 4vw, 2.5rem);">
                    {{ $settings['about_page_years'] ?? '5+' }}</p>
                <p class="micro mt-1.5 sm:mt-2" style="font-size: 0.625rem;">Years</p>
            </div>
            <div class="surface p-3 sm:p-5 lg:p-8 text-center">
                <p class="display display--lg" style="font-size: clamp(1.25rem, 4vw, 2.5rem);">
                    {{ $settings['about_page_projects'] ?? '50+' }}</p>
                <p class="micro mt-1.5 sm:mt-2" style="font-size: 0.625rem;">Projects</p>
            </div>
            <div class="surface p-3 sm:p-5 lg:p-8 text-center">
                <p class="display display--lg" style="font-size: clamp(1.25rem, 4vw, 2.5rem);">
                    {{ $settings['stat_3_value'] ?? '09' }}</p>
                <p class="micro mt-1.5 sm:mt-2" style="font-size: 0.625rem;">
                    {{ $settings['stat_3_label'] ?? 'Cities' }}</p>
            </div>
        </div>
    </section>

    {{-- SKILLS --}}
    <section class="section" data-reveal>
        <div class="section-title">
            <p class="eyebrow mb-3">Toolkit</p>
            <h2>What I Do Best</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 max-w-4xl mx-auto">
            @foreach ($skills ?? collect() as $skill)
                <div class="surface p-5 sm:p-6">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4"
                        style="background: var(--color-line-soft); color: var(--color-ink);">
                        <x-icon :name="$skill->icon" :size="20" />
                    </div>
                    <h3 class="text-base font-semibold text-ink mb-2" style="color: var(--color-ink);">
                        {{ $skill->title }}</h3>
                    <p class="text-sm leading-relaxed" style="color: var(--color-ink-4);">{{ $skill->description }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- TRAJECTORY --}}
    <section class="section" data-reveal>
        <div class="section-title">
            <p class="eyebrow mb-3">Trajectory</p>
            <h2>Where I've Been</h2>
        </div>

        <div class="surface p-5 sm:p-8 lg:p-10 max-w-3xl mx-auto">
            @foreach ($experiences ?? collect() as $i => $exp)
                <div class="row">
                    <span class="row__num">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                    <div class="row__title">
                        <strong>{{ $exp->title }}</strong>
                        <span style="color: var(--color-ink-4);">at {{ $exp->company }}</span>
                    </div>
                    <span class="row__meta">{{ $exp->start_year }} — {{ $exp->end_year }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="section" data-reveal>
        <div class="surface surface-massive p-6 sm:p-10 lg:p-14 text-center">
            <h2 class="display display--lg mb-4">Let's build something together.</h2>
            <a href="{{ route('contact') }}" class="btn btn-embossed btn-lg mt-3">
                Discuss a Project
                <x-icon name="arrow_outward" :size="16" />
            </a>
        </div>
    </section>

</x-layouts.frontend>
