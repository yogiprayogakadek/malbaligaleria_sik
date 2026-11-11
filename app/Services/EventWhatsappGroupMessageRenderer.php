<?php

namespace App\Services;

use App\Models\EventWhatsappGroupMember;
use App\Models\EventWhatsappGroupRule;
use App\Models\WorkPermit;
use Illuminate\Support\Collection;

class EventWhatsappGroupMessageRenderer
{
    public const VARIABLES = [
        'organizer_name' => 'Nama EO atau tenant',
        'permit_number' => 'Nomor surat resmi',
        'category' => 'Kategori Event',
        'location' => 'Lokasi Event',
        'period' => 'Tanggal mulai sampai selesai',
        'facilities' => 'Fasilitas yang dipilih',
        'loading_access' => 'Akses loading barang',
        'vehicle_access' => 'Akses kendaraan',
        'conditional_messages' => 'Pesan tambahan dari rule yang cocok',
        'mentions' => 'Mention anggota grup',
    ];

    /** @return array{message: string, mentions: array<int, string>, rules: Collection<int, EventWhatsappGroupRule>} */
    public function render(WorkPermit $permit, string $template): array
    {
        $requirements = collect($permit->event_requirements ?: []);
        $rules = EventWhatsappGroupRule::query()
            ->where('is_active', true)
            ->with(['requirement', 'members' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('priority')
            ->orderBy('id')
            ->get()
            ->filter(fn (EventWhatsappGroupRule $rule): bool => $this->matches($rule, $requirements, $permit))
            ->values();

        $ruleMembers = $rules->flatMap->members->unique('id')->values();
        $defaultMembers = EventWhatsappGroupMember::query()
            ->where('is_active', true)
            ->where('mention_by_default', true)
            ->get()
            ->whereNotIn('id', $ruleMembers->pluck('id'))
            ->values();
        $members = $defaultMembers
            ->merge($ruleMembers)
            ->unique('id')
            ->values();
        $mentions = $members->map->mentionJid()->filter()->values()->all();
        $mentionText = $this->mentionText($defaultMembers);

        $variables = [
            'organizer_name' => $permit->contractor_name,
            'permit_number' => $permit->permit_number,
            'category' => $permit->work_category_label,
            'location' => $permit->work_location,
            'period' => $permit->start_date->format('d/m/Y').' s.d. '.$permit->end_date->format('d/m/Y'),
            'facilities' => $this->requirements($requirements, 'Fasilitas', $rules),
            'loading_access' => $this->requirements($requirements, 'Akses loading barang', $rules),
            'vehicle_access' => $this->requirements($requirements, 'Akses kendaraan', $rules),
            'conditional_messages' => $rules->map(function (EventWhatsappGroupRule $rule): string {
                $message = trim($rule->message);
                if ($rule->requirement_group === EventWhatsappGroupRule::LOADING_PERMIT_CONDITION) {
                    $message = trim($message.' '.$this->mentionText($rule->members));
                }

                return $message;
            })->filter()->implode("\n"),
            'mentions' => $mentionText,
        ];

        $message = $template;
        foreach ($variables as $key => $value) {
            $message = preg_replace('/\{\{\s*'.preg_quote($key, '/').'\s*\}\}/', (string) $value, $message) ?? $message;
        }
        $message = trim((string) preg_replace("/\n{3,}/", "\n\n", $message));

        if ($mentionText !== '' && ! str_contains($template, '{{mentions}}')) {
            $message .= "\n\n".$mentionText;
        }

        return compact('message', 'mentions', 'rules');
    }

    /** @param Collection<int, array<string, mixed>> $requirements */
    private function matches(EventWhatsappGroupRule $rule, Collection $requirements, WorkPermit $permit): bool
    {
        if ($rule->requirement_group === EventWhatsappGroupRule::LOADING_PERMIT_CONDITION) {
            return (bool) $permit->includes_loading_material;
        }

        return $requirements->contains(function (array $item) use ($rule): bool {
            if (($item['group'] ?? null) !== $rule->requirement_group) {
                return false;
            }

            if (! $rule->event_requirement_id) {
                return true;
            }

            return (isset($item['id']) && (int) $item['id'] === $rule->event_requirement_id)
                || ($rule->requirement && ($item['name'] ?? null) === $rule->requirement->name);
        });
    }

    /** @param Collection<int, array<string, mixed>> $requirements */
    private function requirements(Collection $requirements, string $group, Collection $rules): string
    {
        $value = $requirements
            ->where('group', $group)
            ->map(function (array $item) use ($group, $rules): string {
                $members = $rules
                    ->where('requirement_group', $group)
                    ->filter(fn (EventWhatsappGroupRule $rule): bool => ! $rule->event_requirement_id
                        || (isset($item['id']) && (int) $item['id'] === $rule->event_requirement_id)
                        || ($rule->requirement && ($item['name'] ?? null) === $rule->requirement->name))
                    ->flatMap->members
                    ->unique('id')
                    ->values();

                return trim(($item['name'] ?? '').' '.$this->mentionText($members));
            })
            ->filter()
            ->implode(', ');

        return $value !== '' ? $value : '-';
    }

    /** @param Collection<int, EventWhatsappGroupMember> $members */
    private function mentionText(Collection $members): string
    {
        return $members
            ->map(fn (EventWhatsappGroupMember $member): ?string => $member->mentionJid() ? '@'.str($member->mentionJid())->before('@') : null)
            ->filter()
            ->implode(' ');
    }
}
