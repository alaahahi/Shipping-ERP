<?php

namespace App\Http\Controllers;

use App\Services\NotificationDispatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationDispatchService $notificationDispatchService
    ) {}

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $item = $request->user()?->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        $url = is_array($item->data) ? ($item->data['url'] ?? null) : null;

        return $url
            ? redirect()->to($url)
            : back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications->markAsRead();

        return back()->with('success', 'All notifications marked as read.');
    }
}
