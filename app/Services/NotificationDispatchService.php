<?php

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationDispatchService
{
    /**
     * @param  list<string>|string  $permissions
     */
    public function notifyByPermissions(array|string $permissions, Notification $notification, ?int $exceptUserId = null): void
    {
        $permissions = is_array($permissions) ? $permissions : [$permissions];

        $users = User::permission($permissions)
            ->get()
            ->merge(User::role(SystemRole::Admin->value)->get())
            ->unique('id')
            ->when($exceptUserId, fn ($collection) => $collection->where('id', '!=', $exceptUserId))
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        NotificationFacade::send($users, $notification);
    }

    public function notifyUser(?int $userId, Notification $notification): void
    {
        if (! $userId) {
            return;
        }

        User::query()->find($userId)?->notify($notification);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentFor(User $user, int $limit = 8): array
    {
        return $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn ($notification) => $this->transform($notification))
            ->all();
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function transform(object $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'type' => $data['type'] ?? class_basename((string) $notification->type),
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? '',
            'url' => $data['url'] ?? null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->diffForHumans(),
            'is_unread' => $notification->read_at === null,
        ];
    }
}
