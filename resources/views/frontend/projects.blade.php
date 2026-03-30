<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" title="Projects | {{ $settings['site_name'] ?? 'Architect.folio' }}">

    <div class="pt-40 pb-20 px-6 md:px-12 max-w-7xl mx-auto">
        <!-- Hero Section / Header -->
        <section class="mb-20">
            <h1 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tight text-on-surface mb-6 max-w-4xl">
                Crafting digital <span class="text-primary italic">structures</span> with editorial precision.
            </h1>
            <p class="font-body text-lg text-outline leading-relaxed max-w-2xl">
                A curated selection of architectural interfaces and high-performance development projects where form follows function.
            </p>
        </section>

        <!-- Filter System -->
        <nav class="flex flex-wrap items-center gap-x-8 gap-y-4 mb-16 overflow-x-auto pb-4" id="project-filters">
            <a href="{{ route('projects') }}"
               class="font-label text-sm font-bold uppercase tracking-[0.05em] pb-1 border-b-2 transition-colors
                  {{ !request('category') ? 'text-primary border-primary' : 'text-outline border-transparent hover:text-on-surface hover:border-outline-variant' }}">
                All Projects
            </a>
            @foreach($categories as $category)
                <a href="{{ route('projects', ['category' => $category]) }}"
                   class="font-label text-sm font-medium uppercase tracking-[0.05em] pb-1 border-b-2 transition-colors
                      {{ request('category') === $category ? 'text-primary border-primary font-bold' : 'text-outline border-transparent hover:text-on-surface hover:border-outline-variant' }}">
                    {{ $category }}
                </a>
            @endforeach
        </nav>

        <!-- Project Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-8">
            @foreach($projects as $index => $project)
                @if($index === 0)
                    <!-- Large Span -->
                    <div class="lg:col-span-8 group cursor-pointer">
                        <div class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-ambient hover:shadow-ambient-hover transition-all duration-500">
                            <div class="aspect-[16/9] overflow-hidden">
                                <img alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                     src="{{ $project->image_url }}"/>
                            </div>
                            <div class="p-8 md:p-10">
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="font-label text-xs font-bold uppercase tracking-wider text-primary py-1 px-3 bg-primary-fixed-dim/20 rounded-full">{{ $project->category }}</span>
                                    <span class="font-label text-xs font-bold uppercase tracking-wider text-outline py-1 px-3 bg-surface-container-high rounded-full">{{ $project->year }}</span>
                                </div>
                                <h3 class="font-headline text-3xl font-bold mb-4 group-hover:text-primary transition-colors">{{ $project->title }}</h3>
                                <p class="font-body text-outline leading-relaxed max-w-xl">{{ $project->description }}</p>
                            </div>
                        </div>
                    </div>
                @elseif($index === 1)
                    <!-- Vertical Sidebar -->
                    <div class="lg:col-span-4 group cursor-pointer">
                        <div class="h-full bg-surface-container-lowest rounded-xl overflow-hidden shadow-ambient hover:shadow-ambient-hover transition-all duration-500 flex flex-col">
                            <div class="aspect-[4/5] md:aspect-auto md:grow overflow-hidden">
                                <img alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                     src="{{ $project->image_url }}"/>
                            </div>
                            <div class="p-8">
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="font-label text-xs font-bold uppercase tracking-wider text-primary py-1 px-3 bg-primary-fixed-dim/20 rounded-full">{{ $project->category }}</span>
                                </div>
                                <h3 class="font-headline text-2xl font-bold mb-3 group-hover:text-primary transition-colors">{{ $project->title }}</h3>
                                <p class="font-body text-sm text-outline leading-relaxed">{{ $project->description }}</p>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- Regular Grid Cards -->
                    <div class="lg:col-span-4 group cursor-pointer">
                        <div class="bg-surface-container-lowest rounded-xl overflow-hidden shadow-ambient hover:shadow-ambient-hover transition-all duration-500">
                            <div class="aspect-square overflow-hidden">
                                <img alt="{{ $project->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                     src="{{ $project->image_url }}"/>
                            </div>
                            <div class="p-8">
                                <div class="flex items-center gap-3 mb-4">
                                    <span class="font-label text-xs font-bold uppercase tracking-wider text-primary py-1 px-3 bg-primary-fixed-dim/20 rounded-full">{{ $project->category }}</span>
                                </div>
                                <h3 class="font-headline text-2xl font-bold mb-3 group-hover:text-primary transition-colors">{{ $project->title }}</h3>
                                <p class="font-body text-sm text-outline leading-relaxed">{{ $project->description }}</p>
                            </div>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>

        <!-- CTA Section -->
        <section class="mt-32 p-12 md:p-20 bg-primary-container rounded-3xl text-on-primary-container flex flex-col items-center text-center">
            <h2 class="font-headline text-4xl md:text-5xl font-bold mb-6 max-w-2xl">Ready to build your next structural masterpiece?</h2>
            <p class="font-body text-lg mb-10 opacity-80 max-w-xl">Let's collaborate to bring your vision into the digital physical realm with precision and style.</p>
            <div class="flex flex-col md:flex-row gap-4">
                <a href="{{ route('contact') }}" class="px-10 py-4 bg-white text-primary font-headline font-bold rounded-full hover:scale-105 active:scale-95 transition-all">Start a Project</a>
                <a href="{{ route('about') }}" class="px-10 py-4 bg-primary text-white font-headline font-bold rounded-full border border-white/20 hover:scale-105 active:scale-95 transition-all">View Process</a>
            </div>
        </section>
    </div>

</x-layouts.frontend>
