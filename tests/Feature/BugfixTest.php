<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Settings\General;
use App\Livewire\Admin\Tools\Index;
use App\Livewire\ContactForm;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Tool;
use App\Models\User;
use App\Services\GithubContributions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Regression coverage for the bugs fixed in this pass.
 */
class BugfixTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_setting_does_not_clobber_its_group(): void
    {
        SiteSetting::set('contact_email', 'a@b.com', 'text', 'contact');

        SiteSetting::set('contact_email', 'x@y.com');

        $setting = SiteSetting::where('key', 'contact_email')->first();
        $this->assertSame('contact', $setting->group);
        $this->assertSame('x@y.com', $setting->value);
    }

    public function test_settings_form_save_preserves_groups(): void
    {
        SiteSetting::set('hero_label', 'HI', 'text', 'hero');
        SiteSetting::set('site_name', 'Studio', 'text', 'general');

        Livewire::actingAs(User::factory()->create())
            ->test(General::class)
            ->call('save');

        $this->assertSame('hero', SiteSetting::where('key', 'hero_label')->first()->group);
        $this->assertSame('general', SiteSetting::where('key', 'site_name')->first()->group);
    }

    public function test_duplicate_titles_get_unique_slugs(): void
    {
        $attrs = ['description' => 'x', 'category' => 'web', 'year' => '2026'];

        $this->assertSame('my-site', Project::create(['title' => 'My Site'] + $attrs)->slug);
        $this->assertSame('my-site-2', Project::create(['title' => 'My Site'] + $attrs)->slug);
        $this->assertSame('my-site-3', Project::create(['title' => 'My Site'] + $attrs)->slug);
    }

    public function test_title_that_slugs_to_nothing_still_gets_a_slug(): void
    {
        $attrs = ['description' => 'x', 'category' => 'web', 'year' => '2026'];

        $this->assertSame('project', Project::create(['title' => '日本語'] + $attrs)->slug);
        $this->assertSame('project-2', Project::create(['title' => '中文'] + $attrs)->slug);
    }

    public function test_failed_github_fetch_is_cached_instead_of_refetched(): void
    {
        Cache::flush();
        Http::fake(['github-contributions-api.jogruber.de/*' => Http::response('', 404)]);

        $this->assertNull(GithubContributions::fetch('nobody'));
        $afterFirst = count(Http::recorded());

        GithubContributions::fetch('nobody');
        GithubContributions::fetch('nobody');

        $this->assertSame($afterFirst, count(Http::recorded()));
    }

    public function test_login_locks_out_after_repeated_failures(): void
    {
        RateLimiter::clear('victim@example.com|127.0.0.1');
        User::factory()->create(['email' => 'victim@example.com', 'password' => Hash::make('correct-password')]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'victim@example.com', 'password' => 'wrong']);
        }

        // Even the right password is refused once the account is locked.
        $this->post('/login', ['email' => 'victim@example.com', 'password' => 'correct-password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_contact_form_rate_limits_submissions(): void
    {
        RateLimiter::clear('contact-form:127.0.0.1');

        $send = fn () => Livewire::test(ContactForm::class)
            ->set('name', 'Ann')
            ->set('email', 'ann@example.com')
            ->set('subject', 'Hello')
            ->set('message', 'This is a long enough message.')
            ->call('submit');

        for ($i = 0; $i < 5; $i++) {
            $send()->assertHasNoErrors();
        }

        $send()->assertHasErrors('message');
        $this->assertSame(5, ContactMessage::count());
    }

    public function test_tool_svg_is_stripped_of_script_vectors(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Evil')
            ->set('icon_svg', '<svg onload="alert(1)"><script>alert(2)</script><a href="javascript:alert(3)"><path d="M0 0"/></a></svg>')
            ->call('save')
            ->assertHasNoErrors();

        $svg = Tool::where('name', 'Evil')->first()->icon_svg;
        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringNotContainsString('onload', $svg);
        $this->assertStringNotContainsString('javascript:', $svg);
        $this->assertStringContainsString('<path d="M0 0"/>', $svg);
    }

    public function test_uploaded_files_must_be_images(): void
    {
        Storage::fake('public');

        Livewire::actingAs(User::factory()->create())
            ->test(\App\Livewire\Admin\Projects\Index::class)
            ->call('create')
            ->set('title', 'X')
            ->set('description', 'long enough description')
            ->set('category', 'web')
            ->set('year', '2026')
            ->set('image', UploadedFile::fake()->create('shell.php', 10, 'application/x-php'))
            ->call('save')
            ->assertHasErrors('image');

        $this->assertSame(0, Project::count());
    }

    public function test_every_page_renders(): void
    {
        $public = ['/', '/projects', '/projects?category=web', '/about', '/contact', '/login'];
        foreach ($public as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs(User::factory()->create());
        $admin = [
            '/admin', '/admin/projects', '/admin/skills', '/admin/tools',
            '/admin/experiences', '/admin/messages', '/admin/settings', '/admin/social-links',
        ];
        foreach ($admin as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_projects_page_shows_the_total_count_not_the_filtered_count(): void
    {
        $attrs = ['description' => 'x', 'year' => '2026'];
        Project::create(['title' => 'A', 'category' => 'web'] + $attrs);
        Project::create(['title' => 'B', 'category' => 'web'] + $attrs);
        Project::create(['title' => 'C', 'category' => 'print'] + $attrs);

        $this->get('/projects?category=print')->assertSee('All · 3');
    }
}
