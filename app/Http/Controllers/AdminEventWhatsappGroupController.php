<?php

namespace App\Http\Controllers;

use App\Jobs\SendApprovedEventWhatsappGroup;
use App\Models\EventRequirement;
use App\Models\EventWhatsappGroupDelivery;
use App\Models\EventWhatsappGroupMember;
use App\Models\EventWhatsappGroupRule;
use App\Models\EventWhatsappGroupSetting;
use App\Models\WhatsappSetting;
use App\Services\EventWhatsappGroupMessageRenderer;
use App\Support\IndonesianPhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminEventWhatsappGroupController extends Controller
{
    private const REQUIREMENT_GROUPS = ['Fasilitas', 'Akses loading barang', 'Akses kendaraan'];

    public function index(): View
    {
        return view('admin.event-whatsapp-group.index', [
            'setting' => EventWhatsappGroupSetting::current(),
            'whatsappReady' => WhatsappSetting::current()?->is_active === true,
            'members' => EventWhatsappGroupMember::query()->orderByDesc('is_active')->orderBy('name')->get(),
            'rules' => EventWhatsappGroupRule::query()->with(['requirement', 'members'])->orderBy('priority')->orderBy('name')->get(),
            'requirements' => EventRequirement::query()->where('is_active', true)->whereIn('group_name', self::REQUIREMENT_GROUPS)->orderBy('group_name')->orderBy('sort_order')->get()->groupBy('group_name'),
            'deliveries' => EventWhatsappGroupDelivery::query()->with('permit')->latest()->take(30)->get(),
            'variables' => EventWhatsappGroupMessageRenderer::VARIABLES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
            'group_name' => ['required', 'string', 'min:2', 'max:120'],
            'group_id' => ['required', 'string', 'max:120', 'regex:/^[0-9-]+@g\.us$/'],
            'base_message' => ['required', 'string', 'min:10', 'max:3000'],
        ], ['group_id.regex' => 'ID grup harus menggunakan format WAHA, contohnya 120363000000000000@g.us.']);

        if ((bool) $data['is_active'] && ! WhatsappSetting::current()?->is_active) {
            throw ValidationException::withMessages(['is_active' => 'Aktifkan dan uji konfigurasi WAHA terlebih dahulu.']);
        }

        $this->validateTemplate($data['base_message']);
        $groupId = trim($data['group_id']);
        $setting = EventWhatsappGroupSetting::current() ?? new EventWhatsappGroupSetting;
        $setting->fill([
            'is_active' => (bool) $data['is_active'],
            'group_name' => trim($data['group_name']),
            'group_id' => $groupId,
            'group_id_hash' => hash('sha256', $groupId),
            'base_message' => trim($data['base_message']),
        ])->save();

        return back()->with('success', 'Konfigurasi notifikasi grup Event berhasil disimpan.');
    }

    public function storeMember(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'string', 'max:25'],
            'mention_by_default' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);
        $phone = IndonesianPhoneNumber::normalize($data['phone']);
        if (! $phone) {
            throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp tidak valid.']);
        }
        if (EventWhatsappGroupMember::query()->where('phone_hash', hash('sha256', $phone))->exists()) {
            throw ValidationException::withMessages(['phone' => 'Nomor ini sudah terdaftar sebagai anggota grup.']);
        }

        EventWhatsappGroupMember::query()->create([
            'name' => trim($data['name']),
            'phone' => $phone,
            'phone_hash' => hash('sha256', $phone),
            'mention_by_default' => (bool) $data['mention_by_default'],
            'is_active' => (bool) $data['is_active'],
        ]);

        return back()->with('success', 'Anggota grup berhasil ditambahkan.');
    }

    public function destroyMember(EventWhatsappGroupMember $member): RedirectResponse
    {
        $member->delete();

        return back()->with('success', 'Anggota grup berhasil dihapus.');
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'requirement_group' => ['required', Rule::in(self::REQUIREMENT_GROUPS)],
            'event_requirement_id' => ['nullable', 'integer', 'exists:event_requirements,id'],
            'message' => ['required', 'string', 'min:3', 'max:1000'],
            'priority' => ['required', 'integer', 'between:1,999'],
            'is_active' => ['required', 'boolean'],
            'member_ids' => ['nullable', 'array', 'max:30'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('event_whatsapp_group_members', 'id')->where('is_active', true)],
        ]);

        $requirement = filled($data['event_requirement_id'] ?? null)
            ? EventRequirement::query()->findOrFail($data['event_requirement_id'])
            : null;
        if ($requirement && $requirement->group_name !== $data['requirement_group']) {
            throw ValidationException::withMessages(['event_requirement_id' => 'Kebutuhan tidak sesuai dengan kelompok kondisi.']);
        }

        DB::transaction(function () use ($data): void {
            $rule = EventWhatsappGroupRule::query()->create([
                'name' => trim($data['name']),
                'requirement_group' => $data['requirement_group'],
                'event_requirement_id' => $data['event_requirement_id'] ?? null,
                'message' => trim($data['message']),
                'priority' => $data['priority'],
                'is_active' => (bool) $data['is_active'],
            ]);
            $rule->members()->sync($data['member_ids'] ?? []);
        });

        return back()->with('success', 'Rule notifikasi berhasil ditambahkan.');
    }

    public function destroyRule(EventWhatsappGroupRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Rule notifikasi berhasil dihapus.');
    }

    public function retry(EventWhatsappGroupDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->text_status !== 'sent' || $delivery->file_status !== 'sent', 409, 'Pesan dan surat sudah terkirim.');
        $delivery->update([
            'text_status' => $delivery->text_status === 'sent' ? 'sent' : 'queued',
            'file_status' => $delivery->file_status === 'sent' ? 'sent' : 'queued',
            'error' => null,
        ]);
        SendApprovedEventWhatsappGroup::dispatch($delivery->id)->afterCommit();

        return back()->with('success', 'Pengiriman ulang dimasukkan ke antrean.');
    }

    private function validateTemplate(string $template): void
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $template, $matches);
        $unsupported = array_diff(array_unique($matches[1] ?? []), array_keys(EventWhatsappGroupMessageRenderer::VARIABLES));
        if ($unsupported !== []) {
            throw ValidationException::withMessages(['base_message' => 'Variabel tidak didukung: '.implode(', ', $unsupported).'.']);
        }
    }
}
