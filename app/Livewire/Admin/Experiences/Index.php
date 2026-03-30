<?php

namespace App\Livewire\Admin\Experiences;

use App\Models\Experience;
use Livewire\Component;

class Index extends Component
{
    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    public string $title = '';
    public string $company = '';
    public string $description = '';
    public string $icon = 'token';
    public string $color = 'primary';
    public string $start_year = '';
    public string $end_year = 'PRESENT';
    public int $sort_order = 0;

    protected $rules = [
        'title' => 'required|max:255',
        'company' => 'required|max:255',
        'description' => 'nullable',
        'icon' => 'required|max:50',
        'color' => 'required|max:50',
        'start_year' => 'required|digits:4',
        'end_year' => 'required|max:10',
        'sort_order' => 'integer',
    ];

    public function create()
    {
        $this->reset(['title', 'company', 'description', 'icon', 'color', 'start_year', 'end_year', 'sort_order', 'editingId']);
        $this->icon = 'token';
        $this->color = 'primary';
        $this->end_year = 'PRESENT';
        $this->showModal = true;
        $this->editing = false;
    }

    public function edit(int $id)
    {
        $exp = Experience::findOrFail($id);
        $this->editingId = $id;
        $this->title = $exp->title;
        $this->company = $exp->company;
        $this->description = $exp->description ?? '';
        $this->icon = $exp->icon;
        $this->color = $exp->color;
        $this->start_year = $exp->start_year;
        $this->end_year = $exp->end_year;
        $this->sort_order = $exp->sort_order;
        $this->showModal = true;
        $this->editing = true;
    }

    public function save()
    {
        $this->validate();
        $data = [
            'title' => $this->title, 'company' => $this->company, 'description' => $this->description,
            'icon' => $this->icon, 'color' => $this->color, 'start_year' => $this->start_year,
            'end_year' => $this->end_year, 'sort_order' => $this->sort_order,
        ];

        if ($this->editing && $this->editingId) {
            Experience::findOrFail($this->editingId)->update($data);
        } else {
            Experience::create($data);
        }
        $this->showModal = false;
    }

    public function delete(int $id)
    {
        Experience::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.experiences.index', [
            'experiences' => Experience::ordered()->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Experience']);
    }
}
