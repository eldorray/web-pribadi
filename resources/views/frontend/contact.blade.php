<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'Contact — ' . ($settings['site_name'] ?? 'Studio')">

    {{-- HERO --}}
    <section class="text-center pt-8 sm:pt-16 pb-8">
        <p class="eyebrow mb-3 animate-reveal delay-100">Get in Touch</p>
        <h1 class="display display--xl max-w-3xl mx-auto mb-4 animate-reveal delay-200"
            style="font-size: clamp(2rem, 5vw, 3rem);">
            Let's build something good together.
        </h1>
        <p class="lede max-w-2xl mx-auto mb-7 animate-reveal delay-300">
            {{ $settings['contact_availability'] ?? 'Currently accepting a small number of new commissions. Drop a note about your project — timeline, scope, anything you have in mind.' }}
        </p>
        <span class="status animate-reveal delay-400">
            <span class="status__dot"></span>
            Avg. response 24-48 hrs
        </span>
    </section>

    {{-- LAYOUT: form + sidebar --}}
    <section class="section !mt-12" data-reveal>
        <div class="grid lg:grid-cols-12 gap-5 max-w-5xl mx-auto">
            {{-- Form (8 cols) --}}
            <div class="lg:col-span-8">
                <div class="surface p-5 sm:p-8 lg:p-10">
                    @livewire('contact-form')
                </div>
            </div>

            {{-- Side info (4 cols) --}}
            <aside class="lg:col-span-4 space-y-3">
                <div class="surface-tint p-5">
                    <p class="eyebrow mb-2">Direct Email</p>
                    <a href="mailto:{{ $settings['contact_email'] ?? '' }}"
                        class="text-base font-semibold break-all hover:underline" style="color: var(--color-ink);">
                        {{ $settings['contact_email'] ?? 'hello@studio.com' }}
                    </a>
                </div>

                @if (!empty($settings['contact_address_1']))
                    <div class="surface-tint p-5">
                        <p class="eyebrow mb-2">Studio</p>
                        <p class="text-sm leading-relaxed" style="color: var(--color-ink-3);">
                            {{ $settings['contact_address_1'] }}<br />
                            {{ $settings['contact_address_2'] ?? '' }}<br />
                            {{ $settings['contact_address_3'] ?? '' }}
                        </p>
                    </div>
                @endif

                @if ($socialLinks && $socialLinks->count() > 0)
                    <div class="surface-tint p-5">
                        <p class="eyebrow mb-3">Elsewhere</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($socialLinks as $link)
                                <a href="{{ $link->url }}" target="_blank" rel="noopener" class="chip">
                                    {{ $link->platform }}
                                    <span class="material-symbols-outlined text-[12px]"
                                        style="color: var(--color-ink-5);">arrow_outward</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="surface-tint p-5">
                    <p class="eyebrow mb-3">How We'll Work</p>
                    <ul class="space-y-2 text-sm" style="color: var(--color-ink-3);">
                        <li class="flex gap-3"><span class="row__num">01.</span> Quick reply within 48 hrs</li>
                        <li class="flex gap-3"><span class="row__num">02.</span> Discovery call (30 min, free)</li>
                        <li class="flex gap-3"><span class="row__num">03.</span> Proposal &amp; timeline</li>
                        <li class="flex gap-3"><span class="row__num">04.</span> Make something good</li>
                    </ul>
                </div>
            </aside>
        </div>
    </section>

</x-layouts.frontend>
