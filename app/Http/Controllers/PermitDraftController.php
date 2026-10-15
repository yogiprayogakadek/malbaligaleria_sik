<?php

namespace App\Http\Controllers;

use App\Http\Requests\SavePermitDraftRequest;
use App\Services\PermitDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermitDraftController extends Controller
{
    public function store(
        SavePermitDraftRequest $request,
        string $type,
        PermitDraftService $drafts,
    ): JsonResponse {
        $savedAt = $drafts->save($request, $type, $request->validated());

        return response()->json([
            'message' => 'Draft tersimpan.',
            'saved_at' => $savedAt,
        ]);
    }

    public function destroy(Request $request, string $type, PermitDraftService $drafts): JsonResponse
    {
        abort_unless(in_array($type, ['loading', 'work'], true), 404);

        $drafts->clear($request, $type);

        return response()->json(['message' => 'Draft dihapus.']);
    }
}
