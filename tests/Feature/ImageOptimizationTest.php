<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admin\Projects\Index;
use App\Models\Project;
use App\Models\User;
use App\Support\Image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A 1.2 MB portrait was being served into a 48px avatar tile. Uploads now get
 * downscaled and re-encoded on the way in.
 */
class ImageOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downscales_and_converts_to_webp(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/big.png', $this->pngBytes(2000, 1000));

        $before = Storage::disk('public')->size('settings/big.png');

        $path = Image::optimize('settings/big.png', 512);

        $this->assertSame('settings/big.webp', $path);
        $this->assertFalse(Storage::disk('public')->exists('settings/big.png'));
        $this->assertLessThan($before, Storage::disk('public')->size($path));

        [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(512, $width);
        $this->assertSame(256, $height);
    }

    public function test_it_leaves_svg_and_unreadable_files_alone(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('settings/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
        Storage::disk('public')->put('settings/junk.png', 'not an image');

        $this->assertSame('settings/logo.svg', Image::optimize('settings/logo.svg', 128));
        $this->assertSame('settings/junk.png', Image::optimize('settings/junk.png', 128));
        $this->assertTrue(Storage::disk('public')->exists('settings/logo.svg'));
        $this->assertTrue(Storage::disk('public')->exists('settings/junk.png'));
    }

    public function test_project_uploads_are_stored_as_webp(): void
    {
        Storage::fake('public');

        Livewire::actingAs(User::factory()->create())
            ->test(Index::class)
            ->call('create')
            ->set('title', 'Optimized')
            ->set('description', 'long enough description')
            ->set('category', 'web')
            ->set('year', '2026')
            ->set('image', UploadedFile::fake()->image('shot.png', 2400, 1800))
            ->call('save')
            ->assertHasNoErrors();

        $image = (string) Project::firstOrFail()->image;

        $this->assertStringEndsWith('.webp', $image);
        $this->assertTrue(Storage::disk('public')->exists($image));

        [$width] = getimagesizefromstring(Storage::disk('public')->get($image));
        $this->assertSame(1024, $width);
    }

    /** A gradient, so the encoder has real data to compress rather than one flat colour. */
    private function pngBytes(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x += 4) {
            $colour = imagecolorallocate($image, $x % 256, ($x * 3) % 256, ($x * 7) % 256);
            imagefilledrectangle($image, $x, 0, $x + 3, $height, $colour);
        }

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
