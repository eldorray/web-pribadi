<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Project;
use App\Models\ContactMessage;
use App\Models\Experience;
use App\Models\Skill;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'totalProjects' => Project::count(),
            'featuredProjects' => Project::featured()->count(),
            'unreadMessages' => ContactMessage::unread()->count(),
            'totalMessages' => ContactMessage::count(),
            'totalSkills' => Skill::count(),
            'totalExperiences' => Experience::count(),
            'recentMessages' => ContactMessage::latest()->take(5)->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Dashboard']);
    }
}
