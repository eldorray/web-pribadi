<?php

namespace App\Livewire;

use App\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class ContactForm extends Component
{
    public string $name = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    public bool $submitted = false;

    protected $rules = [
        'name' => 'required|min:2|max:255',
        'email' => 'required|email|max:255',
        'subject' => 'required|min:2|max:255',
        'message' => 'required|min:10|max:5000',
    ];

    public function submit()
    {
        $this->validate();

        // Public endpoint — cap submissions per IP so it can't be used as a spam sink.
        $key = 'contact-form:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'message' => 'Too many messages sent. Please try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes.',
            ]);
        }
        RateLimiter::hit($key, 3600);

        ContactMessage::create([
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
        ]);

        $this->reset(['name', 'email', 'subject', 'message']);
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.contact-form');
    }
}
