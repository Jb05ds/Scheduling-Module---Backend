<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function publicKey()
    {
        return response()->json([
            'data' => ['public_key' => config('webpush.vapid.public_key')],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['message' => 'Notifications enabled.'], 201);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['message' => 'Notifications disabled.']);
    }
}
