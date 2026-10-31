<?php

namespace App\Http\Controllers;

use App\Services\FormPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormPresenceController extends Controller
{
    public function heartbeat(Request $request, string $type, FormPresenceService $presence): JsonResponse
    {
        $validated = $request->validate([
            'tab_id' => ['required', 'uuid'],
            'visitor_id' => ['required', 'uuid'],
        ]);

        return response()->json($presence->heartbeat($type, $validated['visitor_id'], $validated['tab_id']));
    }

    public function leave(Request $request, string $type, FormPresenceService $presence): JsonResponse
    {
        $validated = $request->validate([
            'tab_id' => ['required', 'uuid'],
            'visitor_id' => ['required', 'uuid'],
        ]);

        return response()->json($presence->leave($type, $validated['visitor_id'], $validated['tab_id']));
    }
}
