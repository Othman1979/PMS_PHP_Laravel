<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = UserNotification::query()->where('user_id', $request->user()->id);

        return response()->json([
            'unread' => (clone $query)->unread()->count(),
            'items' => (clone $query)->latest('id')->take(15)->get()->map(fn (UserNotification $n) => $n->toLive()),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        UserNotification::query()->where('user_id', $request->user()->id)->unread()->update(['read_at' => now()]);

        return response()->json(['unread' => 0]);
    }

    /** Marks one notification read and jumps to the screen it points at. */
    public function open(Request $request, UserNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        return redirect($notification->url ?: route('home'));
    }
}
