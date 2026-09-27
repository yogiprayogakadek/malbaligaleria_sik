<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;

class AdminLoadingController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true)
            ? $request->query('status')
            : 'all';
        $query = LoadingPermit::query()->with('reviewer')->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('admin.loading.index', [
            'permits' => $query->paginate(15)->withQueryString(),
            'status' => $status,
            'counts' => [
                'all' => LoadingPermit::count(),
                'pending' => LoadingPermit::where('status', 'pending')->count(),
                'approved' => LoadingPermit::where('status', 'approved')->count(),
                'rejected' => LoadingPermit::where('status', 'rejected')->count(),
            ],
        ]);
    }

    public function show(string $permitNumber)
    {
        $permit = LoadingPermit::query()
            ->where('permit_number', $permitNumber)
            ->with('user', 'reviewer')
            ->firstOrFail();

        return view('admin.loading.show', compact('permit'));
    }
}
