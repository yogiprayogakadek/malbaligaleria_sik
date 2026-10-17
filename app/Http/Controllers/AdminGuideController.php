<?php

namespace App\Http\Controllers;

use App\Support\AdminMenuCatalog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AdminGuideController extends Controller
{
    public function index(): View
    {
        return view('admin.guide.index', [
            'groups' => AdminMenuCatalog::groups(),
            'workflow' => AdminMenuCatalog::workflow(),
        ]);
    }

    public function download(): Response
    {
        $logoPath = public_path('logo.png');

        return Pdf::loadView('admin.guide.pdf', [
            'groups' => AdminMenuCatalog::groups(),
            'workflow' => AdminMenuCatalog::workflow(),
            'logoData' => is_file($logoPath) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath)) : null,
            'generatedAt' => now(config('operating-hours.timezone')),
        ])->setPaper('a4')->download('panduan-sistem-perizinan-mbg.pdf');
    }
}
