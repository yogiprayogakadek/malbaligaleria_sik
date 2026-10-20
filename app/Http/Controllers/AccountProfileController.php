<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AccountProfileController extends Controller
{
    public function edit(): View
    {
        return view('account.profile');
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email']);
        $data['phone'] = IndonesianPhoneNumber::normalize($request->string('phone')->toString());

        if ($user->isTenant()) {
            $data['tenant_name'] = $user->tenant?->displayName() ?: $request->string('tenant_name')->toString();
        }

        $user->update($data);

        return back()->with('success', 'Profil berhasil diperbarui. Data login dan pemulihan akun mengikuti perubahan ini.');
    }
}
