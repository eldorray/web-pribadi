<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'Projects — ' . ($settings['site_name'] ?? 'Studio')">

    {{-- HERO --}}
    <section class="text-center pt-4 sm:pt-12 pb-8 sm:pb-12">
        <p class="eyebrow mb-3 animate-reveal delay-100">A Working Catalogue</p>
        <h1 class="display display--xl max-w-3xl mx-auto mb-4 sm:mb-5 animate-reveal delay-200"
            style="font-size: clamp(1.75rem, 5vw, 3rem); font-weight: 700;">
            Things I've made &amp; shipped.
        </h1>
        <p class="lede max-w-2xl mx-auto px-4 animate-reveal delay-300">
            A small library of digital and visual experiments — interfaces, brands, and side-quests
            where curiosity met craft.
        </p>

        {{-- Filter chips --}}
        <div class="mt-6 sm:mt-8 chip-row animate-reveal delay-400">
            <a href="{{ route('projects') }}" class="chip"
                style="{{ !request('category') ? 'background: var(--color-ink); color: #fff; border-color: var(--color-ink);' : '' }}">
                All · {{ $projects->count() }}
            </a>
            @foreach ($categories as $category)
                <a href="{{ route('projects', ['category' => $category]) }}" class="chip"
                    style="{{ request('category') === $category ? 'background: var(--color-ink); color: #fff; border-color: var(--color-ink);' : '' }}">
                    {{ $category }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- GRID --}}
    <section class="section !mt-2 sm:!mt-4" data-reveal>
        @if ($projects->count() === 0)
            <div class="surface text-center py-12 sm:py-16">
                <span class="material-symbols-outlined text-5xl mb-3 block"
                    style="color: var(--color-ink-5);">folder_open</span>
                <p style="color: var(--color-ink-4);">Nothing in this category yet.</p>
                <a href="{{ route('projects') }}" class="btn btn-light mt-5">View all</a>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-5">
                @foreach ($projects as $project)
                    <a href="{{ $project->link ?? '#' }}" {{ $project->link ? 'target=_blank rel=noopener' : '' }}
                        class="project-card">
                        <div class="project-card__image">
                            <img src="{{ $project->image_url }}" alt="{{ $project->title }}" loading="lazy" />
                        </div>
                        <div class="project-card__body">
                            <p class="project-card__cat">{{ $project->category }} · {{ $project->year }}</p>
                            <h3 class="project-card__title">{{ $project->title }}</h3>
                            <p class="text-sm mt-3 leading-relaxed" style="color: var(--color-ink-4);">
                                {{ Str::limit($project->description, 120) }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    {{-- CTA --}}
    <section class="section" data-reveal>
        <div class="surface surface-massive p-6 sm:p-10 lg:p-14 text-center">
            <p class="eyebrow mb-3">Want to be next?</p>
            <h2 class="display display--lg mb-4">Got an idea worth making?</h2>
            <p class="lede max-w-md mx-auto mb-6 sm:mb-7 px-4 sm:px-0">
                I take on a small number of projects each quarter. Let's talk about yours.
            </p>
            <a href="{{ route('contact') }}" class="btn btn-embossed btn-lg">
                Discuss a Project
                <span class="material-symbols-outlined text-[16px]">arrow_outward</span>
            </a>
        </div>
    </section>

</x-layouts.frontend>
