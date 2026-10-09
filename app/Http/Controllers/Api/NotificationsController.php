<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationsController extends Controller
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
}
