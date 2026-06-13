<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="($settings['site_name'] ?? 'Studio') . ' — Design Engineer'">

    {{-- ============================================
         01 — HERO (2-column)
    ============================================ --}}
    <section class="grid lg:grid-cols-12 gap-8 lg:gap-12 items-start pt-2 lg:pt-4">

        {{-- LEFT --}}
        <div class="lg:col-span-7">
            <div class="flex items-center gap-3 sm:gap-4 mb-5 sm:mb-6 animate-reveal delay-150">
                <div class="avatar-tile">
                    @if (!empty($settings['about_portrait']))
                        <img src="{{ $settings['about_portrait'] }}" alt="Avatar" />
                    @else
                        <div class="w-full h-full flex items-center justify-center text-xl font-bold"
                            style="background: linear-gradient(135deg, #f59e0b, #ea580c); color: #fff;">
                            {{ strtoupper(substr($settings['site_name'] ?? 'M', 0, 1)) }}
                        </div>
                    @endif
                </div>
                <h1 class="display tracking-tight" style="font-size: clamp(1.5rem, 4vw, 2.5rem); font-weight: 700;">
                    {{ $settings['hero_title_2'] ?? ($settings['site_name'] ?? 'Fahmie Al Khudhorie') }}
                </h1>
            </div>

            <p class="text-xl sm:text-[1.75rem] leading-snug mb-7 animate-reveal delay-200 max-w-xl"
                style="color: var(--color-ink); font-weight: 500;">
                {{ $settings['hero_subtitle'] ?? 'Design engineer building products at the intersection of UI, code, and craft.' }}
            </p>

            <div class="flex items-center gap-3 mb-9 animate-reveal delay-300 flex-wrap">
                <a href="{{ route('contact') }}" class="btn btn-embossed">
                    <span class="material-symbols-outlined text-[16px]">chat</span>
                    Discuss a Project
                </a>
                <span class="status">
                    <span class="status__dot"></span>
                    Available
                </span>
            </div>

            <div class="flex flex-wrap gap-1.5 sm:gap-2 mb-10 sm:mb-12 animate-reveal delay-400">
                <span class="chip"><span class="material-symbols-outlined text-[14px]"
                        style="color: var(--color-ink-4);">language</span> Web Design</span>
                <span class="chip"><span class="material-symbols-outlined text-[14px]"
                        style="color: var(--color-ink-4);">design_services</span> Vibe Code</span>
                <span class="chip"><span class="material-symbols-outlined text-[14px]"
                        style="color: var(--color-ink-4);">edit_note</span> Copywriting</span>
                <span class="chip"><span class="material-symbols-outlined text-[14px]"
                        style="color: var(--color-ink-4);">brush</span> Graphic Design</span>
                <span class="chip"><span class="material-symbols-outlined text-[14px]"
                        style="color: var(--color-ink-4);">code</span> Front-end</span>
            </div>

            <div class="surface p-5 sm:p-7 animate-reveal delay-500" data-reveal>
                <p class="text-sm sm:text-[15px] leading-relaxed italic" style="color: var(--color-ink-3);">
                    ""Manusia yang paling dicintai oleh Allah adalah yang paling memberikan manfaat bagi manusia
                    lainnya."
                </p>
                <div class="flex items-center gap-3 mt-5">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shrink-0"
                        style="background: linear-gradient(135deg, #2563eb, #7c3aed); font-size: 14px;">
                        HR
                    </div>
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--color-ink);">(HR. Thabrani)</p>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- RIGHT --}}
        <aside class="lg:col-span-5 space-y-4 lg:space-y-5">
            @foreach ($featuredProjects->take(3) as $i => $project)
                <a href="{{ $project->link ?? '#' }}" {{ $project->link ? 'target=_blank rel=noopener' : '' }}
                    class="block group animate-reveal" style="animation-delay: {{ 300 + $i * 100 }}ms;">
                    <div class="surface overflow-hidden">
                        <div class="aspect-[4/3] overflow-hidden" style="background: var(--color-card-soft);">
                            <img src="{{ $project->image_url }}" alt="{{ $project->title }}"
                                class="w-full h-full object-cover group-hover:scale-[1.025] transition-transform duration-500 ease-out"
                                loading="lazy" />
                        </div>
                        <div class="px-4 sm:px-5 py-3 sm:py-4 flex items-center justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-[10px] sm:text-[11px] font-mono uppercase tracking-widest mb-0.5"
                                    style="color: var(--color-ink-5);">
                                    {{ $project->category }} · {{ $project->year }}
                                </p>
                                <h3 class="text-sm sm:text-base font-semibold truncate"
                                    style="color: var(--color-ink);">
                                    {{ $project->title }}
                                </h3>
                            </div>
                            <span
                                class="material-symbols-outlined text-[16px] shrink-0 group-hover:translate-x-0.5 transition-transform"
                                style="color: var(--color-ink-4);">arrow_outward</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </aside>
    </section>

    {{-- ============================================
         02 — GITHUB CONTRIBUTIONS
    ============================================ --}}
    <div class="section">
        <x-github-heatmap :username="$settings['github_username'] ?? null" />
    </div>

    {{-- ============================================
         03 — TOOLS I USE (fan stack on desktop, grid on mobile)
    ============================================ --}}
    @if ($tools->count() > 0)
        <section class="section" data-reveal>
            <div class="section-title">
                <h2>Tools I Use</h2>
                <p>My favorite stack for designing and building digital experiences.</p>
            </div>

            {{-- Mobile: simple grid (visible on < md) --}}
            <div class="tool-grid-mobile">
                @foreach ($tools as $tool)
                    <div class="tool-tile" aria-label="{{ $tool->name }}">
                        <div class="tool-tile__icon" style="background: {{ $tool->gradient }};">
                            @if ($tool->icon_url)
                                <img src="{{ $tool->icon_url }}" alt="{{ $tool->name }}"
                                    class="w-[22px] h-[22px] object-contain" />
                            @elseif($tool->icon_svg)
                                {!! $tool->icon_svg !!}
                            @else
                                <span class="material-symbols-outlined"
                                    style="color: white; font-size: 18px;">apps</span>
                            @endif
                        </div>
                        <span class="tool-tile__name">{{ $tool->name }}</span>
                    </div>
                @endforeach
            </div>

            {{-- Desktop: fan stack (visible on >= md) --}}
            <div class="tool-fan">
                @foreach ($tools as $tool)
                    <div class="tool-fan-slot" tabindex="0" aria-label="{{ $tool->name }}">
                        <div class="tool-fan-card">
                            <div class="tool-fan-card__icon" style="background: {{ $tool->gradient }};">
                                @if ($tool->icon_url)
                                    <img src="{{ $tool->icon_url }}" alt="{{ $tool->name }}"
                                        class="w-8 h-8 md:w-[38px] md:h-[38px] object-contain" />
                                @elseif($tool->icon_svg)
                                    {!! $tool->icon_svg !!}
                                @else
                                    <span class="material-symbols-outlined"
                                        style="color: white; font-size: 28px;">apps</span>
                                @endif
                            </div>
                            <span class="tool-fan-card__label">{{ $tool->name }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- ============================================
         04 — ABOUT ME
    ============================================ --}}
    <section class="section" data-reveal>
        <div class="surface-cream surface-massive p-6 sm:p-10 lg:p-16">
            <div class="grid lg:grid-cols-12 gap-6 sm:gap-8 lg:gap-16 items-start">
                <div class="lg:col-span-7">
                    <p class="eyebrow mb-4">About Me</p>
                    <h2 class="display display--lg mb-6 leading-tight"
                        style="font-size: clamp(1.5rem, 3.5vw, 2.25rem);">
                        Design is how I solve problems and create impact.
                    </h2>
                    <p class="lede mb-6">
                        {{ $settings['about_text_1'] ?? "I'm a multidisciplinary designer who loves crafting meaningful and functional digital experiences." }}
                    </p>
                    <a href="{{ route('contact') }}" class="btn btn-embossed">
                        Work With Me
                        <span class="material-symbols-outlined text-[16px]">arrow_outward</span>
                    </a>
                </div>

                <aside class="lg:col-span-5 space-y-3">
                    <div class="p-5 flex items-center gap-4 rounded-2xl"
                        style="background: var(--color-card); border: 1px solid var(--color-line);">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                            style="background: rgba(245,158,11,0.12); color: #b45309;">
                            <span class="material-symbols-outlined text-[22px]">workspace_premium</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--color-ink);">
                                {{ $settings['about_page_years'] ?? '5+' }} Years of Experience</p>
                            <p class="text-xs mt-0.5" style="color: var(--color-ink-4);">Working with brands &
                                products
                            </p>
                        </div>
                    </div>
                    <div class="p-5 flex items-center gap-4 rounded-2xl"
                        style="background: var(--color-card); border: 1px solid var(--color-line);">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                            style="background: rgba(37,99,235,0.10); color: #1d4ed8;">
                            <span class="material-symbols-outlined text-[22px]">location_on</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--color-ink);">Based in Indonesia
                            </p>
                            <p class="text-xs mt-0.5" style="color: var(--color-ink-4);">Working remotely
                                worldwide
                            </p>
                        </div>
                    </div>
                    <div class="p-5 flex items-center gap-4 rounded-2xl"
                        style="background: var(--color-card); border: 1px solid var(--color-line);">
                        <div class="w-11 h-11 rounded-xl flex items-center justify-center shrink-0"
                            style="background: rgba(16,185,129,0.12); color: #047857;">
                            <span class="material-symbols-outlined text-[22px]">handshake</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold" style="color: var(--color-ink);">Available for
                                Freelance
                            </p>
                            <p class="text-xs mt-0.5" style="color: var(--color-ink-4);">Selectively taking new
                                projects</p>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

</x-layouts.frontend>
