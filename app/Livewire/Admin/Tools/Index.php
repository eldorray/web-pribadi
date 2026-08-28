<?php

namespace App\Livewire\Admin\Tools;

use App\Models\Tool;
use App\Support\Image;
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
        'name' => 'required|max:120',
        'icon_svg' => 'nullable',
        // icon_url and gradient are VARCHAR(255) columns — keep maxes in sync.
        // Accepts an absolute http(s) URL or a local /storage/ path from an upload.
        'icon_url' => ['nullable', 'string', 'max:255', 'regex:#^(https?://|/storage/)#'],
        'gradient' => 'required|max:255',
        'iconUpload' => 'nullable|image|mimes:jpg,jpeg,png,gif,webp,avif,svg|max:2048',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function create(): void
    {
        $this->reset(['name', 'icon_svg', 'icon_url', 'iconUpload', 'sort_order', 'editingId']);
        $this->gradient = '#111827';
        $this->is_active = true;
        $this->sort_order = (int) (Tool::max('sort_order') ?? 0) + 1;
        $this->showModal = true;
        $this->editing = false;
    }

    public function edit(int $id): void
    {
        $tool = Tool::findOrFail($id);
        $this->editingId = $id;
        $this->name = $tool->name;
        $this->icon_svg = $tool->icon_svg ?? '';
        $this->icon_url = $tool->icon_url ?? '';
        $this->iconUpload = null;
        $this->gradient = $tool->gradient ?? '#111827';
        $this->sort_order = $tool->sort_order;
        $this->is_active = $tool->is_active;
        $this->showModal = true;
        $this->editing = true;
    }

    public function save(): void
    {
        $this->validate();

        // Handle uploaded image (overrides icon_url). Store a root-relative path,
        // not asset() — an absolute URL bakes the current host into the DB and
        // breaks every icon the moment the site moves domain.
        if ($this->iconUpload && ! is_string($this->iconUpload)) {
            $path = Image::optimize($this->iconUpload->store('tools', 'public'), 128);
            $this->icon_url = '/storage/'.$path;
        }

        $data = [
            'name' => $this->name,
            'icon_svg' => $this->sanitizeSvg($this->icon_svg) ?: null,
            'icon_url' => $this->icon_url ?: null,
            'gradient' => $this->gradient,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
        ];

        if ($this->editing && $this->editingId) {
            Tool::findOrFail($this->editingId)->update($data);
        } else {
            Tool::create($data);
        }

        $this->showModal = false;
    }

    /**
     * icon_svg is rendered raw with {!! !!} on the public homepage. Strip the
     * script vectors an SVG copied off the internet may carry with it.
     *
     * ponytail: prefix/attribute stripping, not a real SVG parser — enough for
     * paste-from-the-web markup. Swap in a sanitizer library if untrusted
     * (non-admin) users ever get to submit SVG.
     */
    private function sanitizeSvg(string $svg): string
    {
        $svg = preg_replace('#<\s*(script|foreignObject|iframe|object|embed)\b[^>]*>.*?<\s*/\s*\1\s*>#is', '', $svg) ?? '';
        $svg = preg_replace('#<\s*(script|foreignObject|iframe|object|embed)\b[^>]*/?>#i', '', $svg) ?? '';
        $svg = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $svg) ?? '';
        $svg = preg_replace('#(href|xlink:href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]*("|\')?#i', '', $svg) ?? '';

        return trim($svg);
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
