<?php

namespace App\Http\Controllers;

use App\Support\AdminMenuCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminSidebarSettingController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings.sidebar', [
            'groups' => AdminMenuCatalog::groups(),
            'hiddenRoutes' => AdminMenuCatalog::hiddenRoutesFor($request->user()),
            'hideableCount' => count(AdminMenuCatalog::hideableRoutes()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:save,show_all'],
            'visible_routes' => ['nullable', 'array'],
            'visible_routes.*' => ['string', 'max:255'],
        ]);

        $hideableRoutes = AdminMenuCatalog::hideableRoutes();
        $visibleRoutes = array_values(array_intersect(
            $hideableRoutes,
            array_values(array_filter($data['visible_routes'] ?? [], 'is_string')),
        ));

        $request->user()->forceFill([
            'admin_hidden_menu_routes' => $data['action'] === 'show_all'
                ? []
                : array_values(array_diff($hideableRoutes, $visibleRoutes)),
        ])->save();

        $message = $data['action'] === 'show_all'
            ? 'Seluruh menu kembali ditampilkan di sidebar.'
            : 'Tampilan sidebar berhasil disimpan.';

        return redirect()->route('admin.settings.sidebar.edit')->with('success', $message);
    }
}
