<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\SystemNotification;
use Illuminate\Support\Facades\Notification;

class Notifier
{
    /**
     * Send an in-app notification to every active user holding any of the given permissions
     * (through a role or directly), plus super administrators.
     *
     * @param  string|list<string>  $permissions
     * @param  string|null  $key  When given, users who already have an unread notification with this key are skipped.
     */
    public static function send(string|array $permissions, string $title, string $message, ?string $url = null, string $icon = 'bell', ?string $key = null, ?User $also = null): void
    {
        $permissions = (array) $permissions;

        // Relationship queries (rather than Spatie's scopes) so unknown permission names never throw.
        $users = $permissions === [] ? collect() : User::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereHas('roles', fn ($r) => $r->where('name', User::SUPER_ADMIN))
                ->orWhereHas('roles.permissions', fn ($p) => $p->whereIn('name', $permissions))
                ->orWhereHas('permissions', fn ($p) => $p->whereIn('name', $permissions)))
            ->get();

        if ($also) {
            $users->push($also);
        }

        $users = $users->unique('id');

        if ($key) {
            $users = $users->reject(fn (User $u) => $u->unreadNotifications()->where('data', 'like', '%"key":"'.$key.'"%')->exists());
        }

        if ($users->isNotEmpty()) {
            Notification::send($users, new SystemNotification($title, $message, $url, $icon, $key));
        }
    }
}
