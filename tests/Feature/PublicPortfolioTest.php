<?php

namespace Tests\Feature;

use App\Livewire\ContactForm;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class PublicPortfolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_cards_preserve_content_and_only_link_when_a_destination_exists(): void
    {
        $attributes = ['description' => 'A useful education product.', 'category' => 'Web App', 'year' => '2026', 'is_featured' => true];
        Project::create(['title' => 'School records', 'link' => 'https://example.com/records'] + $attributes);
        Project::create(['title' => 'Work in progress'] + $attributes);

        foreach (['/', '/projects'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('School records')
                ->assertSee('Work in progress')
                ->assertSee('https://example.com/records')
                ->assertSee('Tautan belum tersedia')
                ->assertDontSee('href="#"', false);
        }
    }

    public function test_category_filters_preserve_the_catalog_total_and_show_only_matching_projects(): void
    {
        $attributes = ['description' => 'A useful education product.', 'year' => '2026'];
        Project::create(['title' => 'Lesson planner', 'category' => 'AI'] + $attributes);
        Project::create(['title' => 'School dashboard', 'category' => 'Web App'] + $attributes);

        $this->get('/projects?category=AI')->assertOk()
            ->assertSee('Lesson planner')->assertDontSee('School dashboard')
            ->assertSee('Semua')->assertSee('Menampilkan 1 dari 2 karya');

        $this->get('/projects?category=missing')->assertOk()
            ->assertSee('Belum ada karya di kategori ini.');
    }

    public function test_all_public_pages_keep_navigation_and_configurable_contact_details(): void
    {
        SiteSetting::set('contact_email', 'studio@example.com');
        SiteSetting::set('hero_title_2', 'Test Designer, M.TI.');
        SiteSetting::set('about_text_1', 'Configured home biography.');
        SiteSetting::set('about_page_intro', 'Configured about introduction.');
        SiteSetting::set('about_text_2', 'Configured additional biography.');

        foreach (['/', '/projects', '/about', '/contact'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('Test Designer')
                ->assertSee('id="main-content"', false)
                ->assertSee('aria-label="Navigasi seluler"', false)
                ->assertSee(route('projects'))->assertSee(route('about'))->assertSee(route('contact'));
        }

        $this->get('/contact')->assertSee('mailto:studio@example.com')
            ->assertSee('wire:submit="submit"', false)
            ->assertSee('wire:model="name"', false)
            ->assertSee('wire:model="email"', false)
            ->assertSee('wire:model="subject"', false)
            ->assertSee('wire:model="message"', false);

        $this->get('/')->assertSee('Configured home biography.');
        $this->get('/about')->assertSee('Configured about introduction.')
            ->assertSee('Configured additional biography.');
    }

    public function test_github_uses_cached_real_counts_and_the_configured_username(): void
    {
        SiteSetting::set('github_username', 'test-designer');
        $week = array_fill(0, 7, ['date' => '2026-10-19', 'count' => 3, 'level' => 2]);
        Cache::put('github_contrib::test-designer', [
            'total' => 1234, 'weeks' => array_fill(0, 30, $week), 'months' => [],
        ]);

        $response = $this->get('/')->assertOk()
            ->assertSee('1.234 kontribusi dalam setahun terakhir')
            ->assertSee('&#64;test-designer', false)
            ->assertSee('https://github.com/test-designer')
            ->assertDontSee('{{ $username }}', false);

        $this->assertSame(4, substr_count($response->getContent(), 'pf-gh-week--older'));
    }

    public function test_contact_validation_and_success_stay_in_the_public_interface_language(): void
    {
        RateLimiter::clear('contact-form:127.0.0.1');
        $form = Livewire::test(ContactForm::class)
            ->call('submit')
            ->assertHasErrors(['name', 'email', 'subject', 'message'])
            ->assertSee('Nama wajib diisi.');
        $this->assertSame(0, ContactMessage::count());

        $form->set('name', 'Test Designer')->set('email', 'designer@example.com')
            ->set('subject', 'Kolaborasi')->set('message', 'Mari membangun produk pendidikan.')
            ->call('submit')->assertHasNoErrors()->assertSet('submitted', true)
            ->assertSee('Pesan sudah terkirim.')->assertSet('message', '');
        $this->assertSame(1, ContactMessage::count());

        $form->set('submitted', false)->assertSee('Kirim pesan');
    }
}
