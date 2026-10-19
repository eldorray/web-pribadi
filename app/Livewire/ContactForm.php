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

    protected $messages = [
        'name.required' => 'Nama wajib diisi.',
        'name.min' => 'Nama minimal 2 karakter.',
        'name.max' => 'Nama maksimal 255 karakter.',
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Masukkan alamat email yang valid.',
        'email.max' => 'Email maksimal 255 karakter.',
        'subject.required' => 'Subjek wajib diisi.',
        'subject.min' => 'Subjek minimal 2 karakter.',
        'subject.max' => 'Subjek maksimal 255 karakter.',
        'message.required' => 'Pesan wajib diisi.',
        'message.min' => 'Pesan minimal 10 karakter.',
        'message.max' => 'Pesan maksimal 5.000 karakter.',
    ];

    public function submit()
    {
        $this->validate();

        // Public endpoint — cap submissions per IP so it can't be used as a spam sink.
        $key = 'contact-form:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'message' => 'Batas pengiriman pesan tercapai. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
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
