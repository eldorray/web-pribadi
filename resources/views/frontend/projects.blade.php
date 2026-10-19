<x-layouts.frontend :settings="$settings" :socialLinks="$socialLinks" :title="'Karya — ' . ($settings['site_name'] ?? 'Portfolio')">
    <section class="pf-panel pf-page-intro">
        <a href="{{ route('home') }}" class="pf-back"><x-icon name="arrow_back" :size="16" /> Kembali ke beranda</a>
        <div class="pf-page-intro__row"><h1>Karya & eksperimen.</h1><p>Produk digital di persimpangan pendidikan, desain, dan teknologi.</p></div>
    </section>

    <section class="pf-catalog" aria-label="Katalog karya">
        <div class="pf-filter-bar">
            <nav class="pf-filters" aria-label="Filter kategori karya">
                <a href="{{ route('projects') }}" @class(['pf-filter', 'is-active' => !request('category') || request('category') === 'all']) @if(!request('category') || request('category') === 'all') aria-current="true" @endif>Semua <span>{{ $totalProjects }}</span></a>
                @foreach ($categories as $category)
                    <a href="{{ route('projects', ['category' => $category]) }}" @class(['pf-filter', 'is-active' => request('category') === $category]) @if(request('category') === $category) aria-current="true" @endif>{{ $category }}</a>
                @endforeach
            </nav>
            <p class="pf-catalog__meta">{{ $projects->count() }} karya · Dikurasi dengan niat</p>
        </div>

        @if ($projects->isNotEmpty())
            <div class="pf-project-grid">
                @foreach ($projects as $project)
                    <x-project-card :project="$project" :index="$loop->iteration" />
                @endforeach
            </div>
        @else
            <div class="pf-panel pf-empty"><x-icon name="folder_open" :size="36" /><h2>Belum ada karya di kategori ini.</h2><p>Jelajahi kategori lain untuk menemukan karya dan eksperimen saya.</p><a href="{{ route('projects') }}" class="pf-button pf-button--small">Lihat semua karya <x-icon name="arrow_outward" :size="17" /></a></div>
        @endif
        <p class="pf-catalog__count">Menampilkan {{ $projects->count() }} dari {{ $totalProjects }} karya</p>
    </section>

    <x-contact-banner title="Ide berikutnya bisa jadi milik Anda." />
</x-layouts.frontend>
