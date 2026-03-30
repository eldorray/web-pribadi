<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" title="About | {{ $settings['site_name'] ?? 'Architect.folio' }}">

    <div class="pt-32 pb-20 px-6 md:px-12 max-w-7xl mx-auto">
        <!-- Hero Section: My Story -->
        <section class="mb-24">
            <div class="flex flex-col md:flex-row gap-16 items-center">
                <div class="w-full md:w-1/2 relative group">
                    <div class="absolute -inset-4 bg-primary/5 rounded-3xl -rotate-2 group-hover:rotate-0 transition-transform duration-500"></div>
                    <div class="relative overflow-hidden rounded-2xl aspect-[4/5] bg-surface-container-low">
                        <img alt="Professional portrait" class="w-full h-full object-cover"
                             src="{{ $settings['about_page_portrait'] ?? '' }}"/>
                    </div>
                </div>
                <div class="w-full md:w-1/2">
                    <span class="font-label text-primary font-bold tracking-[0.2em] text-xs uppercase mb-4 block">Introduction</span>
                    <h1 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tighter text-on-surface mb-8 leading-tight">My Story.</h1>
                    <div class="space-y-6 text-on-surface-variant font-body text-lg leading-relaxed">
                        <p>{{ $settings['about_page_intro'] ?? '' }}</p>
                        <p>{{ $settings['about_page_bio'] ?? '' }}</p>
                        <div class="pt-4 flex gap-4">
                            <div class="flex flex-col">
                                <span class="text-3xl font-bold text-on-surface">{{ $settings['about_page_years'] ?? '12+' }}</span>
                                <span class="text-xs font-label uppercase text-outline">Years Experience</span>
                            </div>
                            <div class="w-px h-12 bg-surface-container-high mx-4"></div>
                            <div class="flex flex-col">
                                <span class="text-3xl font-bold text-on-surface">{{ $settings['about_page_projects'] ?? '150+' }}</span>
                                <span class="text-xs font-label uppercase text-outline">Projects Delivered</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Skills Section: Bento Grid Layout -->
        <section class="mb-32">
            <div class="flex justify-between items-end mb-12">
                <h2 class="font-headline text-4xl font-bold tracking-tight">Core Expertise</h2>
                <div class="h-px flex-grow mx-8 bg-surface-container-high hidden md:block"></div>
                <span class="font-label text-sm text-outline">Capabilities</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                @foreach($skills as $skill)
                    @if($skill->is_wide)
                        <div class="md:col-span-2 bg-surface-container-low p-8 rounded-2xl transition-all hover:bg-surface-container-lowest hover:shadow-xl hover:shadow-primary/5 group">
                    @else
                        <div class="bg-surface-container-low p-8 rounded-2xl transition-all hover:bg-surface-container-lowest hover:shadow-xl hover:shadow-primary/5 group">
                    @endif
                        <div class="bg-{{ $skill->color }}/10 text-{{ $skill->color }} w-12 h-12 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                            <span class="material-symbols-outlined">{{ $skill->icon }}</span>
                        </div>
                        <h3 class="font-headline text-xl font-bold mb-2">{{ $skill->title }}</h3>
                        <p class="text-on-surface-variant text-sm leading-relaxed">{{ $skill->description }}</p>
                    </div>
                @endforeach
                <!-- Problem Solving at Scale (static accent card) -->
                <div class="md:col-span-3 bg-primary-container p-8 rounded-2xl text-on-primary-container relative overflow-hidden group">
                    <div class="relative z-10">
                        <h3 class="font-headline text-2xl font-bold mb-4">Problem Solving at Scale</h3>
                        <p class="text-on-primary-container/80 max-w-xl text-lg">My approach combines data-driven insights with aesthetic intuition to build products that don't just work—they resonate.</p>
                    </div>
                    <span class="material-symbols-outlined absolute -right-8 -bottom-8 text-[12rem] opacity-10 rotate-12 group-hover:rotate-0 transition-transform duration-700">lightbulb</span>
                </div>
            </div>
        </section>

        <!-- Experience/Education Section: Tonal Timeline -->
        <section class="mb-32">
            <h2 class="font-headline text-4xl font-bold tracking-tight mb-16 text-center">Trajectory</h2>
            <div class="max-w-4xl mx-auto space-y-4">
                @foreach($experiences as $experience)
                    <div class="group relative bg-surface-container-low hover:bg-surface-container-lowest p-8 rounded-3xl transition-all duration-300">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div class="flex items-center gap-6">
                                <div class="w-14 h-14 bg-white rounded-2xl shadow-sm flex items-center justify-center text-{{ $experience->color }}">
                                    <span class="material-symbols-outlined">{{ $experience->icon }}</span>
                                </div>
                                <div>
                                    <h4 class="font-headline text-xl font-bold text-on-surface">{{ $experience->title }}</h4>
                                    <p class="text-on-surface-variant">{{ $experience->company }}</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="bg-{{ $experience->color }}/10 text-{{ $experience->color }} px-4 py-1.5 rounded-full text-xs font-bold font-label">{{ $experience->year_range }}</span>
                            </div>
                        </div>
                        <p class="mt-6 text-on-surface-variant leading-relaxed text-sm">{{ $experience->description }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- CTA Section -->
        <section class="rounded-[3rem] bg-on-surface text-surface py-20 px-8 text-center overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-full opacity-5 pointer-events-none">
                <div class="absolute inset-0" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 40px 40px;"></div>
            </div>
            <h2 class="font-headline text-4xl md:text-5xl font-extrabold mb-8 relative z-10">Let's build something<br/>unforgettable together.</h2>
            <a href="{{ route('contact') }}" class="bg-primary hover:bg-primary-container text-white px-10 py-5 rounded-full font-bold text-lg transition-all hover:scale-105 active:scale-95 relative z-10 inline-block">
                Start a Conversation
            </a>
        </section>
    </div>

</x-layouts.frontend>
