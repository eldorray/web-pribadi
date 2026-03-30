<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="($settings['site_name'] ?? 'Architect.folio') . ' | Editorial Portfolio'">

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center pt-24 px-6 md:px-12 overflow-hidden bg-surface">
        <!-- Asymmetrical Design Element -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-primary-container/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 left-0 w-full h-1/2 bg-gradient-to-t from-surface-container-low to-transparent"></div>

        <div class="container mx-auto relative z-10">
            <div class="max-w-5xl mx-auto text-center">
                <span class="inline-block font-label text-xs tracking-[0.2em] uppercase text-primary mb-6 font-semibold">{{ $settings['hero_label'] ?? 'ESTABLISHED MMXXIV' }}</span>
                <h1 class="font-headline text-6xl md:text-8xl lg:text-[7rem] font-black leading-[0.9] tracking-tighter text-on-surface mb-8">
                    {{ $settings['hero_title_1'] ?? 'Designing Space,' }}<br/>
                    {{ $settings['hero_title_2'] ?? 'Defining' }} <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary to-secondary">{{ $settings['hero_title_highlight'] ?? 'Stories.' }}</span>
                </h1>
                <p class="font-body text-lg md:text-xl text-on-surface-variant max-w-2xl mx-auto mb-10 leading-relaxed">
                    {{ $settings['hero_subtitle'] ?? '' }}
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a class="btn-primary-gradient px-10 py-5 rounded-xl font-bold text-lg shadow-xl" href="{{ route('projects') }}">
                        View My Work
                    </a>
                    <a class="bg-surface-container-high text-on-surface px-10 py-5 rounded-xl font-bold text-lg hover:bg-surface-container-highest transition-colors flex items-center justify-center gap-2 group" href="{{ route('about') }}">
                        Read the Story
                        <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Background subtle pattern -->
        <div class="absolute inset-0 z-0 opacity-[0.03] pointer-events-none bg-dot-pattern"></div>
    </section>

    <!-- Intro Section: The Narrative -->
    <section class="py-32 px-6 md:px-12 bg-surface-container-low" id="about">
        <div class="container mx-auto">
            <div class="flex flex-col lg:flex-row gap-20 items-center">
                <div class="lg:w-5/12 relative">
                    <div class="aspect-[4/5] rounded-xl overflow-hidden shadow-2xl relative z-10 bg-surface-container-highest">
                        <img alt="Portrait" class="w-full h-full object-cover"
                             src="{{ $settings['about_portrait'] ?? '' }}"/>
                    </div>
                    <div class="absolute -bottom-8 -left-8 w-48 h-48 bg-tertiary-fixed rounded-xl -z-10"></div>
                    <div class="absolute top-1/2 -right-12 -translate-y-1/2 font-headline text-9xl text-on-surface/5 font-black select-none vertical-text hidden xl:block">
                        CREATIVE
                    </div>
                </div>
                <div class="lg:w-7/12">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="h-[1px] w-12 bg-primary"></div>
                        <span class="font-label text-sm uppercase tracking-widest text-primary font-bold">{{ $settings['about_label'] ?? 'The Philosophy' }}</span>
                    </div>
                    <h2 class="font-headline text-4xl md:text-5xl font-extrabold mb-8 leading-tight text-on-surface">
                        {{ $settings['about_title'] ?? '' }}
                    </h2>
                    <div class="space-y-6 text-on-surface-variant font-body text-lg leading-relaxed">
                        <p>{{ $settings['about_text_1'] ?? '' }}</p>
                        <p>{{ $settings['about_text_2'] ?? '' }}</p>
                    </div>
                    <div class="mt-12 flex gap-12 border-t border-outline-variant/20 pt-10">
                        <div>
                            <div class="text-3xl font-black font-headline text-primary">{{ $settings['stat_1_value'] ?? '120+' }}</div>
                            <div class="text-xs font-label uppercase tracking-widest text-outline mt-1">{{ $settings['stat_1_label'] ?? 'Projects Built' }}</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black font-headline text-primary">{{ $settings['stat_2_value'] ?? '15' }}</div>
                            <div class="text-xs font-label uppercase tracking-widest text-outline mt-1">{{ $settings['stat_2_label'] ?? 'Design Awards' }}</div>
                        </div>
                        <div>
                            <div class="text-3xl font-black font-headline text-primary">{{ $settings['stat_3_value'] ?? '09' }}</div>
                            <div class="text-xs font-label uppercase tracking-widest text-outline mt-1">{{ $settings['stat_3_label'] ?? 'Global Cities' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Highlights: Projects -->
    <section class="py-32 px-6 md:px-12 bg-surface" id="projects">
        <div class="container mx-auto">
            <div class="flex justify-between items-end mb-20">
                <div class="max-w-2xl">
                    <span class="font-label text-xs tracking-widest uppercase text-tertiary font-bold mb-4 block">Selected Portfolio</span>
                    <h2 class="font-headline text-5xl font-black text-on-surface">Featured Highlights</h2>
                </div>
                <a class="hidden md:flex items-center gap-2 font-bold text-primary hover:gap-4 transition-all duration-300" href="{{ route('projects') }}">
                    View All Projects <span class="material-symbols-outlined">north_east</span>
                </a>
            </div>

            <!-- Project Grid: Bento/Editorial Style -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-8">
                @foreach($featuredProjects as $index => $project)
                    @if($index === 0)
                        <!-- Project 1: Large Feature -->
                        <div class="md:col-span-8 group cursor-pointer">
                            <div class="relative overflow-hidden rounded-xl bg-surface-container-high aspect-[16/9] mb-6 shadow-ambient hover:shadow-ambient-hover transition-all duration-500">
                                <img alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                     src="{{ $project->image_url }}"/>
                                <div class="absolute inset-0 bg-gradient-to-t from-on-surface/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-8">
                                    <span class="text-white font-bold flex items-center gap-2">Explore Case Study <span class="material-symbols-outlined">arrow_outward</span></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-label uppercase text-primary font-bold tracking-widest">{{ $project->category }} • {{ $project->year }}</span>
                                    <h3 class="font-headline text-2xl font-extrabold mt-1 text-on-surface">{{ $project->title }}</h3>
                                </div>
                            </div>
                        </div>
                    @elseif($index === 1)
                        <!-- Project 2: Vertical Side -->
                        <div class="md:col-span-4 group cursor-pointer">
                            <div class="relative overflow-hidden rounded-xl bg-surface-container-high aspect-[3/4] mb-6 shadow-ambient hover:shadow-ambient-hover transition-all duration-500">
                                <img alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                     src="{{ $project->image_url }}"/>
                                <div class="absolute inset-0 bg-gradient-to-t from-on-surface/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-8">
                                    <span class="text-white font-bold flex items-center gap-2">Details <span class="material-symbols-outlined">arrow_outward</span></span>
                                </div>
                            </div>
                            <span class="text-xs font-label uppercase text-primary font-bold tracking-widest">{{ $project->category }} • {{ $project->year }}</span>
                            <h3 class="font-headline text-2xl font-extrabold mt-1 text-on-surface">{{ $project->title }}</h3>
                        </div>
                    @elseif($index === 2)
                        <!-- Project 3: Wide Bottom -->
                        <div class="md:col-span-12 group cursor-pointer">
                            <div class="relative overflow-hidden rounded-xl bg-surface-container-high aspect-[21/9] mb-6 shadow-ambient hover:shadow-ambient-hover transition-all duration-500">
                                <img alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                     src="{{ $project->image_url }}"/>
                                <div class="absolute inset-0 bg-gradient-to-t from-on-surface/60 to-transparent opacity-0 group-hover:opacity-100 transition-opacity flex items-end p-8">
                                    <span class="text-white font-bold flex items-center gap-2">View Experience <span class="material-symbols-outlined">arrow_outward</span></span>
                                </div>
                            </div>
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="text-xs font-label uppercase text-primary font-bold tracking-widest">{{ $project->category }} • {{ $project->year }}</span>
                                    <h3 class="font-headline text-2xl font-extrabold mt-1 text-on-surface">{{ $project->title }}</h3>
                                </div>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-24 px-6 md:px-12">
        <div class="container mx-auto">
            <div class="bg-primary-container rounded-3xl p-12 md:p-24 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-1/2 h-full opacity-10 pointer-events-none">
                    <span class="font-headline text-[20rem] font-black leading-none select-none">NEXT</span>
                </div>
                <div class="relative z-10 max-w-2xl">
                    <h2 class="font-headline text-4xl md:text-6xl font-black text-white mb-8 leading-tight">Ready to build your masterpiece?</h2>
                    <p class="text-white/80 text-lg mb-12 font-body">Let's collaborate on a project that challenges the status quo and leaves a lasting legacy.</p>
                    <a href="{{ route('contact') }}" class="bg-white text-primary px-12 py-5 rounded-xl font-black text-xl hover:scale-105 active:scale-95 transition-all duration-300 inline-block">
                        Start a Project
                    </a>
                </div>
            </div>
        </div>
    </section>

</x-layouts.frontend>
