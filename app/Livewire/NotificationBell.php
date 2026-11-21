<?php

namespace App\Livewire;

use Livewire\Component;

class NotificationBell extends Component
{
    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function open(string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        if ($url = $notification->data['url'] ?? null) {
            // Only follow links within this application.
            if (str_starts_with($url, url('/'))) {
                return $this->redirect($url, navigate: true);
            }
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.notification-bell', [
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(8)->get(),
        ]);
    }
}
