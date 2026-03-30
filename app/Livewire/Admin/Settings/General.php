<?php

namespace App\Livewire\Admin\Settings;

use App\Models\SiteSetting;
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
        if ($value) {
            $path = $value->store('settings', 'public');
            $this->settings[$key] = asset('storage/' . $path);
        }
    }

    public function removeImage(string $key)
    {
        $this->settings[$key] = '';
        unset($this->imageUploads[$key]);
    }

    public function save()
    {
        foreach ($this->settings as $key => $value) {
            $type = $this->isImageSetting($key) ? 'image' : 'text';
            SiteSetting::set($key, $value, $type);
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
