<?php

namespace App\Http\Controllers;

use App\Services\FormPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class AdminFormPresenceController extends Controller
{
    public function index(FormPresenceService $presence): View
    {
        return view('admin.form-presence.index', [
            'snapshot' => $presence->snapshot(),
            'formTypes' => FormPresenceService::TYPES,
        ]);
    }

    public function snapshot(FormPresenceService $presence): JsonResponse
    {
        return response()->json($presence->snapshot());
    }
}
