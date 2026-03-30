<?php

namespace App\Livewire\Admin\Skills;

use App\Models\Skill;
use Livewire\Component;

class Index extends Component
{
    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    public string $title = '';
    public string $description = '';
    public string $icon = 'code';
    public string $color = 'primary';
    public bool $is_wide = false;
    public int $sort_order = 0;

    protected $rules = [
        'title' => 'required|max:255',
        'description' => 'nullable',
        'icon' => 'required|max:50',
        'color' => 'required|max:50',
        'is_wide' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function create()
    {
        $this->reset(['title', 'description', 'icon', 'color', 'is_wide', 'sort_order', 'editingId']);
        $this->icon = 'code';
        $this->color = 'primary';
        $this->showModal = true;
        $this->editing = false;
    }

    public function edit(int $id)
    {
        $skill = Skill::findOrFail($id);
        $this->editingId = $id;
        $this->title = $skill->title;
        $this->description = $skill->description ?? '';
        $this->icon = $skill->icon;
        $this->color = $skill->color;
        $this->is_wide = $skill->is_wide;
        $this->sort_order = $skill->sort_order;
        $this->showModal = true;
        $this->editing = true;
    }

    public function save()
    {
        $this->validate();
        $data = [
            'title' => $this->title, 'description' => $this->description,
            'icon' => $this->icon, 'color' => $this->color,
            'is_wide' => $this->is_wide, 'sort_order' => $this->sort_order,
        ];

        if ($this->editing && $this->editingId) {
            Skill::findOrFail($this->editingId)->update($data);
        } else {
            Skill::create($data);
        }
        $this->showModal = false;
    }

    public function delete(int $id)
    {
        Skill::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.skills.index', [
            'skills' => Skill::ordered()->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Skills']);
    }
}
