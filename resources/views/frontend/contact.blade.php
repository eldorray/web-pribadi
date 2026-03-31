<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" title="Contact | {{ $settings['site_name'] ?? 'My Portofolio' }}">

    <div class="pt-24 sm:pt-32 pb-16 sm:pb-24 px-4 sm:px-6 md:px-12 lg:px-24 max-w-7xl mx-auto">
        <!-- Hero Section -->
        <section class="mb-12 sm:mb-20">
            <h1 class="font-headline text-4xl sm:text-6xl md:text-8xl font-black tracking-tighter mb-4 sm:mb-6 text-on-surface">
                Let's Build <br/>
                <span class="text-primary italic">Together.</span>
            </h1>
            <p class="max-w-2xl text-base sm:text-lg md:text-xl text-on-surface-variant leading-relaxed">
                Whether you're starting a new architectural project or looking for a design partnership, I'm here to turn your vision into structural reality.
            </p>
        </section>

        <!-- Bento Grid Contact Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8">
            <!-- Contact Form Section (Bento Large Item) -->
            <div class="lg:col-span-8 bg-surface-container-lowest rounded-xl p-6 sm:p-8 md:p-12 shadow-ambient">
                @livewire('contact-form')
            </div>

            <!-- Sidebar Info (Bento Side Items) -->
            <div class="lg:col-span-4 space-y-6 sm:space-y-8">
                <!-- Location Card -->
                <div class="bg-surface-container-low rounded-xl p-6 sm:p-8 relative overflow-hidden group">
                    <div class="relative z-10">
                        <h3 class="font-headline text-2xl font-bold mb-4">Studio</h3>
                        <p class="text-on-surface-variant font-medium leading-relaxed">
                            {{ $settings['contact_address_1'] ?? '' }}<br/>
                            {{ $settings['contact_address_2'] ?? '' }}<br/>
                            {{ $settings['contact_address_3'] ?? '' }}
                        </p>
                    </div>
                    <div class="absolute -right-12 -bottom-12 opacity-10 group-hover:scale-110 transition-transform duration-700">
                        <span class="material-symbols-outlined text-[120px]">location_on</span>
                    </div>
                </div>

                <!-- Connect Card -->
                <div class="bg-primary text-on-primary rounded-xl p-6 sm:p-8 shadow-[0_20px_40px_rgba(0,62,199,0.15)]">
                    <h3 class="font-headline text-2xl font-bold mb-6">Digital Presence</h3>
                    <div class="space-y-4">
                        @foreach($socialLinks as $link)
                            <a class="flex items-center justify-between p-4 bg-white/10 rounded-lg hover:bg-white/20 transition-all group" href="{{ $link->url }}" target="_blank">
                                <span class="font-medium">{{ $link->platform }}</span>
                                <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Fast Contact -->
                <div class="bg-surface-container-high rounded-xl p-6 sm:p-8">
                    <div class="flex items-center gap-4 mb-4">
                        <span class="material-symbols-outlined text-primary">mail</span>
                        <span class="font-label text-xs uppercase tracking-widest font-bold">Email directly</span>
                    </div>
                    <a class="text-xl font-headline font-bold text-on-surface hover:text-primary transition-colors" href="mailto:{{ $settings['contact_email'] ?? '' }}">
                        {{ $settings['contact_email'] ?? 'hello@My Portofolio' }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Asymmetrical Image Section -->
        <section class="mt-16 sm:mt-24 grid grid-cols-1 md:grid-cols-2 gap-8 sm:gap-12 items-center">
            <div class="rounded-2xl overflow-hidden aspect-[4/5] shadow-2xl relative bg-surface-container-high">
                @if(!empty($settings['contact_image']))
                    <img alt="Modern building facade" class="w-full h-full object-cover"
                         src="{{ $settings['contact_image'] }}"/>
                @else
                    <div class="w-full h-full flex items-center justify-center">
                        <span class="material-symbols-outlined text-[8rem] text-outline/20">image</span>
                    </div>
                @endif
                <div class="absolute inset-0 bg-gradient-to-t from-on-surface/40 to-transparent"></div>
            </div>
            <div class="space-y-4 sm:space-y-6 md:pl-12">
                <span class="text-primary font-bold tracking-widest uppercase text-sm font-label">Availability</span>
                <h2 class="font-headline text-3xl sm:text-4xl font-bold leading-tight">{{ $settings['contact_availability'] ?? '' }}</h2>
                <p class="text-on-surface-variant leading-relaxed text-base sm:text-lg">
                    I limit my intake to three major projects per quarter to ensure each structural design receives the editorial attention and architectural precision it deserves.
                </p>
                <div class="flex gap-4">
                    <div class="h-12 w-12 rounded-full border border-outline-variant flex items-center justify-center">
                        <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1;">schedule</span>
                    </div>
                    <div>
                        <p class="font-bold">Avg. Response Time</p>
                        <p class="text-on-surface-variant">24-48 business hours</p>
                    </div>
                </div>
            </div>
        </section>
    </div>

</x-layouts.frontend>
