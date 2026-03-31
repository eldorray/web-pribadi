<?php

namespace App\Livewire\Admin\Projects;

use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;

class Index extends Component
{
    use WithFileUploads;

    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;
    public bool $confirmingDelete = false;
    public ?int $deletingId = null;

    public string $title = '';
    public string $description = '';
    public string $category = '';
    public string $year = '';
    public $image = null;
    public string $existingImage = '';
    public string $link = '';
    public bool $is_featured = false;
    public int $sort_order = 0;

    protected $rules = [
        'title' => 'required|min:2|max:255',
        'description' => 'required|min:10',
        'category' => 'required|max:100',
        'year' => 'required|digits:4',
        'link' => 'nullable|url|max:500',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function create()
    {
        $this->resetForm();
        $this->showModal = true;
        $this->editing = false;
    }

    public function edit(int $id)
    {
        $project = Project::findOrFail($id);
        $this->editingId = $id;
        $this->title = $project->title;
        $this->description = $project->description;
        $this->category = $project->category;
        $this->year = $project->year;
        $this->existingImage = $project->image ?? '';
        $this->link = $project->link ?? '';
        $this->is_featured = $project->is_featured;
        $this->sort_order = $project->sort_order;
        $this->showModal = true;
        $this->editing = true;
    }

    public function save()
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'description' => $this->description,
            'category' => $this->category,
            'year' => $this->year,
            'link' => $this->link ?: null,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,
        ];

        if ($this->image && !is_string($this->image)) {
            $data['image'] = $this->image->store('projects', 'public');
        } elseif ($this->existingImage) {
            $data['image'] = $this->existingImage;
        }

        if ($this->editing && $this->editingId) {
            Project::findOrFail($this->editingId)->update($data);
        } else {
            Project::create($data);
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function confirmDelete(int $id)
    {
        $this->deletingId = $id;
        $this->confirmingDelete = true;
    }

    public function delete()
    {
        if ($this->deletingId) {
            Project::findOrFail($this->deletingId)->delete();
        }
        $this->confirmingDelete = false;
        $this->deletingId = null;
    }

    public function cancelDelete()
    {
        $this->confirmingDelete = false;
        $this->deletingId = null;
    }

    private function resetForm()
    {
        $this->title = '';
        $this->description = '';
        $this->category = '';
        $this->year = '';
        $this->image = null;
        $this->existingImage = '';
        $this->link = '';
        $this->is_featured = false;
        $this->sort_order = 0;
        $this->editingId = null;
    }

    public function render()
    {
        return view('livewire.admin.projects.index', [
            'projects' => Project::ordered()->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Projects']);
    }
}
