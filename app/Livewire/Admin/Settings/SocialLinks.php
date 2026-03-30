<?php

namespace App\Livewire\Admin\Settings;

use App\Models\SocialLink;
use Livewire\Component;

class SocialLinks extends Component
{
    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    public string $platform = '';
    public string $url = '';
    public int $sort_order = 0;

    protected $rules = [
        'platform' => 'required|max:100',
        'url' => 'required|max:500',
        'sort_order' => 'integer',
    ];

    public function create()
    {
        $this->reset(['platform', 'url', 'sort_order', 'editingId']);
        $this->showModal = true;
        $this->editing = false;
    }

    public function edit(int $id)
    {
        $link = SocialLink::findOrFail($id);
        $this->editingId = $id;
        $this->platform = $link->platform;
        $this->url = $link->url;
        $this->sort_order = $link->sort_order;
        $this->showModal = true;
        $this->editing = true;
    }

    public function save()
    {
        $this->validate();
        $data = ['platform' => $this->platform, 'url' => $this->url, 'sort_order' => $this->sort_order];

        if ($this->editing && $this->editingId) {
            SocialLink::findOrFail($this->editingId)->update($data);
        } else {
            SocialLink::create($data);
        }

        $this->showModal = false;
    }

    public function delete(int $id)
    {
        SocialLink::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.settings.social-links', [
            'links' => SocialLink::ordered()->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Social Links']);
    }
}
