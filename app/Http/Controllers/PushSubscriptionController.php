<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeletePushSubscriptionRequest;
use App\Http\Requests\StorePushSubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'],
        );

        return response()->json(['subscribed' => true], 201);
    }

    public function destroy(DeletePushSubscriptionRequest $request): Response
    {
        $request->user()->deletePushSubscription($request->validated('endpoint'));

        return response()->noContent();
    }
}
