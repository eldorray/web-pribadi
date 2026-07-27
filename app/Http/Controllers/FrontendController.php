<?php

namespace App\Http\Controllers;

use App\Models\Experience;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\Tool;

class FrontendController extends Controller
{
    public function home()
    {
        $featuredProjects = Project::featured()->ordered()->take(3)->get();
        $settings = SiteSetting::pluck('value', 'key');
        $socialLinks = SocialLink::ordered()->get();
        $tools = Tool::active()->ordered()->get();

        return view('frontend.home', compact('featuredProjects', 'settings', 'socialLinks', 'tools'));
    }

    public function projects()
    {
        $category = request('category');
        $projects = Project::byCategory($category)->ordered()->get();
        $totalProjects = Project::count();
        $categories = Project::distinct()->pluck('category')->filter();
        $socialLinks = SocialLink::ordered()->get();
        $settings = SiteSetting::pluck('value', 'key');

        return view('frontend.projects', compact('projects', 'totalProjects', 'categories', 'socialLinks', 'settings'));
    }

    public function about()
    {
        $settings = SiteSetting::pluck('value', 'key');
        $skills = Skill::ordered()->get();
        $experiences = Experience::ordered()->get();
        $socialLinks = SocialLink::ordered()->get();

        return view('frontend.about', compact('settings', 'skills', 'experiences', 'socialLinks'));
    }

    public function contact()
    {
        $settings = SiteSetting::pluck('value', 'key');
        $socialLinks = SocialLink::ordered()->get();

        return view('frontend.contact', compact('settings', 'socialLinks'));
    }
}
