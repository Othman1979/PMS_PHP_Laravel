<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PushController extends Controller
{
    public function publicKey(WebPushService $push): JsonResponse
    {
        return response()->json(['publicKey' => $push->publicKey()]);
    }

    public function subscribe(Request $request): Response
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:800'],
            'p256dh' => ['required', 'string', 'max:200'],
            'auth' => ['required', 'string', 'max:100'],
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $data['endpoint'])],
            [...$data, 'user_id' => $request->user()->id, 'user_agent' => Str::limit((string) $request->userAgent(), 297)],
        );

        return response()->noContent();
    }

    public function unsubscribe(Request $request): Response
    {
        PushSubscription::query()->where('endpoint_hash', hash('sha256', (string) $request->input('endpoint')))->delete();

        return response()->noContent();
    }

    public function test(Request $request, WebPushService $push): Response
    {
        $push->sendToUsers($request->user()->id, __('AppName'), __('Push_TestBody'), route('home', absolute: false), 'test');

        return response()->noContent();
    }
}
