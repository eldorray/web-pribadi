<?php

namespace App\Livewire\Admin\Tools;

use App\Models\Tool;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    public string $name = '';
    public string $icon_svg = '';
    public string $icon_url = '';
    public $iconUpload = null;
    public string $gradient = '#111827';
    public int $sort_order = 0;
    public bool $is_active = true;

    protected $rules = [
        'name'       => 'required|max:120',
        'icon_svg'   => 'nullable',
        'icon_url'   => 'nullable|url|max:500',
        'gradient'   => 'required|max:500',
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public function create(): void
    {
        $this->reset(['name', 'icon_svg', 'icon_url', 'iconUpload', 'sort_order', 'editingId']);
        $this->gradient   = '#111827';
        $this->is_active  = true;
        $this->sort_order = (int) (Tool::max('sort_order') ?? 0) + 1;
        $this->showModal  = true;
        $this->editing    = false;
    }

    public function edit(int $id): void
    {
        $tool = Tool::findOrFail($id);
        $this->editingId  = $id;
        $this->name       = $tool->name;
        $this->icon_svg   = $tool->icon_svg ?? '';
        $this->icon_url   = $tool->icon_url ?? '';
        $this->iconUpload = null;
        $this->gradient   = $tool->gradient ?? '#111827';
        $this->sort_order = $tool->sort_order;
        $this->is_active  = $tool->is_active;
        $this->showModal  = true;
        $this->editing    = true;
    }

    public function save(): void
    {
        $this->validate();

        // Handle uploaded image (overrides icon_url)
        if ($this->iconUpload && ! is_string($this->iconUpload)) {
            $path = $this->iconUpload->store('tools', 'public');
            $this->icon_url = asset('storage/' . $path);
        }

        $data = [
            'name'       => $this->name,
            'icon_svg'   => $this->icon_svg ?: null,
            'icon_url'   => $this->icon_url ?: null,
            'gradient'   => $this->gradient,
            'sort_order' => $this->sort_order,
            'is_active'  => $this->is_active,
        ];

        if ($this->editing && $this->editingId) {
            Tool::findOrFail($this->editingId)->update($data);
        } else {
            Tool::create($data);
        }

        $this->showModal = false;
    }

    public function delete(int $id): void
    {
        Tool::findOrFail($id)->delete();
    }

    public function toggleActive(int $id): void
    {
        $tool = Tool::findOrFail($id);
        $tool->update(['is_active' => ! $tool->is_active]);
    }

    public function render()
    {
        return view('livewire.admin.tools.index', [
            'tools' => Tool::ordered()->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Tools']);
    }
}
