<?php

namespace App\Livewire\Admin\Messages;

use App\Models\ContactMessage;
use Livewire\Component;

class Index extends Component
{
    public ?int $viewingId = null;

    public function view(int $id)
    {
        $message = ContactMessage::findOrFail($id);
        $message->update(['is_read' => true]);
        $this->viewingId = $this->viewingId === $id ? null : $id;
    }

    public function markAsRead(int $id)
    {
        ContactMessage::findOrFail($id)->update(['is_read' => true]);
    }

    public function markAsUnread(int $id)
    {
        ContactMessage::findOrFail($id)->update(['is_read' => false]);
    }

    public function delete(int $id)
    {
        ContactMessage::findOrFail($id)->delete();
        $this->viewingId = null;
    }

    public function render()
    {
        return view('livewire.admin.messages.index', [
            'messages' => ContactMessage::latest()->get(),
        ])->layout('layouts.admin', ['pageTitle' => 'Messages']);
    }
}
