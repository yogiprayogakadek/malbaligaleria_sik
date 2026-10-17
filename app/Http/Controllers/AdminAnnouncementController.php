<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementCampaignRequest;
use App\Models\AnnouncementCampaign;
use App\Models\LoadingPermit;
use App\Models\User;
use App\Models\WorkPermit;
use App\Services\AnnouncementCampaignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminAnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'campaigns' => AnnouncementCampaign::query()->with('creator')->latest()->paginate(10),
            'accountCount' => User::query()->where('is_active', true)->count(),
            'applicantCount' => LoadingPermit::query()->count() + WorkPermit::query()->count(),
            'submissionToken' => (string) str()->uuid(),
        ]);
    }

    public function store(StoreAnnouncementCampaignRequest $request, AnnouncementCampaignService $campaigns): RedirectResponse
    {
        $campaign = $campaigns->create($request->validated(), $request->user());

        return redirect()->route('admin.announcements.index')
            ->with('success', 'Pengumuman masuk antrean untuk '.number_format($campaign->recipient_count, 0, ',', '.').' tujuan.');
    }

    public function cancel(AnnouncementCampaign $campaign, AnnouncementCampaignService $campaigns): RedirectResponse
    {
        $campaigns->cancel($campaign);

        return back()->with('success', 'Sisa pengiriman yang masih mengantre telah dibatalkan.');
    }
}
