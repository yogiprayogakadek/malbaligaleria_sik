<?php

namespace App\Http\Controllers;

use App\Models\LoadingPermit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermitController extends Controller
{
    /**
     * Konfigurasi metadata jenis izin.
     */
    protected $types = [
        'loading' => [
            'code'     => 'LOG',
            'title'    => 'Loading & Unloading Barang',
            'subtitle' => 'Ajukan izin perpindahan barang masuk atau keluar area gedung.',
            'tag'      => 'LOGISTIK',
            'icon'     => 'box',
            'color'    => 'blue',
            'details'  => 'Detail Barang & Kendaraan',
        ],
        'work' => [
            'code'     => 'KRJ',
            'title'    => 'Surat Izin Kerja (SIK)',
            'subtitle' => 'Ajukan izin pekerjaan teknis atau renovasi operasional di area gedung.',
            'tag'      => 'OPERASIONAL',
            'icon'     => 'wrench',
            'color'    => 'violet',
            'details'  => 'Detail Pekerjaan & Kontraktor',
        ],
        'exhibition' => [
            'code'     => 'PMR',
            'title'    => 'Surat Izin Pameran',
            'subtitle' => 'Ajukan izin aktivasi promosi dan display pameran produk.',
            'tag'      => 'PROMOSI',
            'icon'     => 'gallery',
            'color'    => 'rose',
            'details'  => 'Detail Booth & Konsep Pameran',
        ],
        'event' => [
            'code'     => 'EVT',
            'title'    => 'Surat Izin Event',
            'subtitle' => 'Ajukan izin penyelenggaraan acara, gathering, dan kegiatan khusus tenant.',
            'tag'      => 'ACARA',
            'icon'     => 'calendar',
            'color'    => 'amber',
            'details'  => 'Detail Agenda & Kegiatan',
        ],
    ];

    /**
     * Halaman Arsip / Riwayat Permohonan Saya.
     */
    public function index()
    {
        $permits = collect();
        if (Auth::check()) {
            $permits = LoadingPermit::where('user_id', Auth::id())
                ->latest()
                ->paginate(10);
        }

        return view('portal.permits.index', compact('permits'));
    }

    /**
     * Formulir Pengajuan Izin Baru.
     */
    public function create(Request $request)
    {
        $type = $request->query('type', 'work');
        if ($type === 'loading') {
            return redirect()->route('loading.create');
        }
        if (!array_key_exists($type, $this->types)) {
            $type = 'work';
        }

        $typeInfo = $this->types[$type];

        return view('portal.permits.create', compact('type', 'typeInfo'));
    }

    /**
     * Proses Pengajuan Izin (Prototipe tanpa menyimpan ke database).
     */
    public function store(Request $request)
    {
        $type = $request->input('permitType', 'loading');
        $code = $this->types[$type]['code'] ?? 'LOG';
        $year = date('Y');
        $random = str_pad(mt_rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $reference = "MBG-{$year}-{$code}-{$random}";

        return redirect()->route('permits.success', [
            'ref'   => $reference,
            'type'  => $type,
            'date'  => $request->input('startDate', date('Y-m-d')),
        ]);
    }

    /**
     * Halaman Bukti / Konfirmasi Permohonan Berhasil Dicatat.
     */
    public function success(Request $request)
    {
        $reference = $request->query('ref', 'MBG-' . date('Y') . '-LOG-' . mt_rand(100000, 999999));
        $type = $request->query('type', 'loading');
        $typeInfo = $this->types[$type] ?? $this->types['loading'];
        $date = $request->query('date', date('Y-m-d'));

        return view('portal.permits.success', compact('reference', 'type', 'typeInfo', 'date'));
    }
}
