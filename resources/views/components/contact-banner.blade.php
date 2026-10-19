@props(['title' => 'Punya ide? Mari kita wujudkan.'])
<section class="pf-cta" aria-label="Diskusikan proyek">
    <div>
        <p class="pf-eyebrow">Punya ide yang ingin diwujudkan?</p>
        <h2>{{ $title }}</h2>
    </div>
    <a href="{{ route('contact') }}" class="pf-button">Diskusikan proyek <x-icon name="arrow_outward" :size="20" /></a>
</section>
