<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'About — ' . ($settings['site_name'] ?? 'Studio')">

    {{-- HERO --}}
    <section class="text-center pt-8 sm:pt-16 pb-8">
        <div class="animate-reveal delay-150 inline-block mb-6">
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full overflow-hidden mx-auto"
                style="background: var(--color-card-soft); border: 4px solid #fff; box-shadow: var(--shadow-card), 0 0 0 1px var(--color-line);">
                @if (!empty($settings['about_page_portrait']))
                    <img src="{{ $settings['about_page_portrait'] }}" alt="Portrait" class="w-full h-full object-cover" />
                @elseif(!empty($settings['about_portrait']))
                    <img src="{{ $settings['about_portrait'] }}" alt="Portrait" class="w-full h-full object-cover" />
                @else
                    <div class="w-full h-full flex items-center justify-center text-2xl font-bold"
                        style="color: var(--color-ink);">
                        {{ strtoupper(substr($settings['site_name'] ?? 'M', 0, 1)) }}
                    </div>
                @endif
            </div>
        </div>

        <p class="eyebrow mb-3 animate-reveal delay-200">About Me</p>
        <h1 class="display display--xl max-w-3xl mx-auto mb-4 animate-reveal delay-250"
            style="font-size: clamp(2rem, 5vw, 3rem);">
            {{ $settings['site_name'] ?? 'Hello, I make digital things.' }}
        </h1>
        <p class="lede max-w-2xl mx-auto animate-reveal delay-300">
            {{ $settings['about_page_intro'] ?? 'Designer & developer based in Indonesia. I help brands and products connect with their audience through thoughtful, functional design.' }}
        </p>
    </section>

    {{-- BIO --}}
    <section class="section !mt-12" data-reveal>
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
        <div class="grid grid-cols-3 gap-3 sm:gap-5 max-w-4xl mx-auto">
            <div class="surface p-5 sm:p-8 text-center">
                <p class="display display--lg" style="font-size: clamp(1.75rem, 4vw, 2.5rem);">
                    {{ $settings['about_page_years'] ?? '5+' }}</p>
                <p class="micro mt-2">Years Experience</p>
            </div>
            <div class="surface p-5 sm:p-8 text-center">
                <p class="display display--lg" style="font-size: clamp(1.75rem, 4vw, 2.5rem);">
                    {{ $settings['about_page_projects'] ?? '50+' }}</p>
                <p class="micro mt-2">Projects Shipped</p>
            </div>
            <div class="surface p-5 sm:p-8 text-center">
                <p class="display display--lg" style="font-size: clamp(1.75rem, 4vw, 2.5rem);">
                    {{ $settings['stat_3_value'] ?? '09' }}</p>
                <p class="micro mt-2">{{ $settings['stat_3_label'] ?? 'Cities' }}</p>
            </div>
        </div>
    </section>

    {{-- SKILLS --}}
    <section class="section" data-reveal>
        <div class="section-title">
            <p class="eyebrow mb-3">Toolkit</p>
            <h2>What I Do Best</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 max-w-4xl mx-auto">
            @foreach ($skills ?? collect() as $skill)
                <div class="surface p-6">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center mb-4"
                        style="background: rgba(17,24,39,0.06); color: var(--color-ink);">
                        <span class="material-symbols-outlined text-[20px]">{{ $skill->icon }}</span>
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
        <div class="surface surface-massive p-8 sm:p-14 text-center">
            <h2 class="display display--lg mb-4">Let's build something together.</h2>
            <a href="{{ route('contact') }}" class="btn btn-embossed btn-lg mt-3">
                Discuss a Project
                <span class="material-symbols-outlined text-[16px]">arrow_outward</span>
            </a>
        </div>
    </section>

</x-layouts.frontend>
