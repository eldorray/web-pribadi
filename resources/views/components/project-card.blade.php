@props(['project', 'index' => 1])
<article class="pf-project">
    @if ($project->link)
        <a class="pf-project__image" href="{{ $project->link }}" target="_blank" rel="noopener noreferrer" aria-label="Lihat proyek {{ $project->title }} (tab baru)">
    @else
        <div class="pf-project__image">
    @endif
        <span class="pf-project__placeholder" aria-hidden="true"><x-icon name="code" :size="40" /></span>
        @if ($project->image_url)
            <img src="{{ $project->image_url }}" alt="Tampilan {{ $project->title }}" width="1024" height="768" loading="lazy" decoding="async" data-project-image />
        @endif
    @if ($project->link)
        </a>
    @else
        </div>
    @endif
    <div class="pf-project__body">
        <p class="pf-project__category">{{ str_pad($index, 2, '0', STR_PAD_LEFT) }} / {{ $project->category }} · {{ $project->year }}</p>
        <h3 class="pf-project__title">
            @if ($project->link)
                <a href="{{ $project->link }}" target="_blank" rel="noopener noreferrer">{{ $project->title }}<x-icon name="arrow_outward" :size="23" /></a>
            @else
                {{ $project->title }}
            @endif
        </h3>
        <p class="pf-project__description">{{ Str::limit($project->description, 150) }}</p>
        @if ($project->link)
            <a href="{{ $project->link }}" class="pf-project__link" target="_blank" rel="noopener noreferrer">Lihat proyek <x-icon name="arrow_outward" :size="13" /><span class="sr-only"> {{ $project->title }} (tab baru)</span></a>
        @else
            <span class="pf-project__unavailable">Tautan belum tersedia</span>
        @endif
    </div>
</article>
