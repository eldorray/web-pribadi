<?php

namespace App\Livewire\Admin\Settings;

use App\Models\SiteSetting;
use App\Support\Image;
use Livewire\Component;
use Livewire\WithFileUploads;

class General extends Component
{
    use WithFileUploads;

    public array $settings = [];

    public array $imageUploads = [];

    public bool $saved = false;

    // Image-type setting keys
    protected array $imageKeys = [
        'about_portrait',
        'about_page_portrait',
        'contact_image',
        'site_favicon',
    ];

    public function mount()
    {
        $allSettings = SiteSetting::all();
        foreach ($allSettings as $setting) {
            $this->settings[$setting->key] = $setting->value;
        }
    }

    public function isImageSetting(string $key): bool
    {
        return in_array($key, $this->imageKeys);
    }

    public function updatedImageUploads($value, $key)
    {
        if (! $value) {
            return;
        }

        $this->validateOnly("imageUploads.$key", [
            "imageUploads.$key" => 'image|mimes:jpg,jpeg,png,gif,webp,avif,svg|max:4096',
        ]);

        // Downscale + WebP before it ever reaches a page. Portraits render at
        // 96px at most, favicons at 32px — the raw upload is wildly oversized.
        $path = Image::optimize(
            $value->store('settings', 'public'),
            $key === 'site_favicon' ? 128 : 512,
        );

        // Root-relative, not asset(): an absolute URL bakes the current host
        // into the DB and breaks every image when the site changes domain.
        $this->settings[$key] = '/storage/'.$path;
    }

    public function removeImage(string $key)
    {
        $this->settings[$key] = '';
        unset($this->imageUploads[$key]);
    }

    public function save()
    {
        foreach ($this->settings as $key => $value) {
            // Pass no group: SiteSetting::set() keeps whatever group the row
            // already has. Passing one flattened every setting into "general".
            SiteSetting::set($key, $value, $this->isImageSetting($key) ? 'image' : null);
        }
        $this->saved = true;
    }

    public function render()
    {
        $groups = SiteSetting::all()->groupBy('group');

        return view('livewire.admin.settings.general', [
            'groups' => $groups,
        ])->layout('layouts.admin', ['pageTitle' => 'Settings']);
    }
}
