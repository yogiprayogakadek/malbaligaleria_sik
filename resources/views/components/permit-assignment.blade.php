@props(['permit', 'type' => 'work', 'eligibleValidators' => collect()])

@php
  $isAdminAssignment = Auth::user()->isAdmin();
  $isLoadingAssignment = $type === 'loading';
  $actionDivision = $isLoadingAssignment ? ($permit->status === 'pending' ? 'TR' : null) : $permit->actionDivision();
  $isEligibleValidator = Auth::user()->isValidator() && $actionDivision === Auth::user()->division;
  $isOwnAssignment = $permit->assigned_validator_id === Auth::id();
  $isAssignedToOther = $permit->assigned_validator_id && !$isOwnAssignment;
@endphp

@if($actionDivision && ($isAdminAssignment || $isEligibleValidator))
  <section class="permit-assignment" aria-labelledby="permitAssignmentTitle">
    <div class="permit-assignment-copy">
      <span class="permit-assignment-icon"><svg><use href="#i-user"/></svg></span>
      <div>
        <h2 id="permitAssignmentTitle">Penanggung jawab pemeriksaan</h2>
        @if($permit->assignee)
          <p><strong>{{ $permit->assignee->name }}</strong> menangani tahap {{ $actionDivision }} sejak {{ $permit->assigned_at?->format('d M Y, H:i') }} WITA.</p>
          @if($permit->assignment_note)<p>Alasan pengambilalihan: {{ $permit->assignment_note }}</p>@endif
        @else
          <p>Belum ada validator yang mengambil permohonan ini.</p>
        @endif
      </div>
    </div>

    @if($isAdminAssignment)
      <form action="{{ $isLoadingAssignment ? route('admin.loading.assignment', $permit->permit_number) : route('admin.work-permits.assignment', $permit->public_token) }}" method="POST" class="permit-assignment-admin">
        @csrf
        @method('PUT')
        <label for="assignmentValidator">Validator {{ $actionDivision }}</label>
        <select id="assignmentValidator" name="validator_id">
          <option value="">Belum ditugaskan</option>
          @foreach($eligibleValidators as $validator)
            <option value="{{ $validator->id }}" @selected($permit->assigned_validator_id === $validator->id)>{{ $validator->name }}</option>
          @endforeach
        </select>
        <button type="submit" class="admin-primary-button">Simpan penugasan</button>
      </form>
    @elseif($isOwnAssignment)
      <span class="permit-assignment-state"><svg><use href="#i-check"/></svg>Ditugaskan kepada Anda</span>
    @elseif(!$permit->assigned_validator_id)
      <form action="{{ $isLoadingAssignment ? route('tr.assignment', $permit->permit_number) : route('staff.work-permits.assignment', $permit->public_token) }}" method="POST">
        @csrf
        <button type="submit" class="btn-primary"><svg><use href="#i-user"/></svg>Ambil permohonan</button>
      </form>
    @else
      <form action="{{ $isLoadingAssignment ? route('tr.assignment', $permit->permit_number) : route('staff.work-permits.assignment', $permit->public_token) }}" method="POST" class="permit-takeover-form" onsubmit="return confirm('Ambil alih permohonan dari validator saat ini?')">
        @csrf
        <input type="hidden" name="takeover" value="1">
        <label for="takeoverReason">Alasan pengambilalihan</label>
        <textarea id="takeoverReason" name="takeover_reason" rows="2" minlength="5" maxlength="500" required placeholder="Contoh: validator sebelumnya sedang tidak bertugas"></textarea>
        <button type="submit" class="btn-secondary"><svg><use href="#i-user"/></svg>Ambil alih</button>
      </form>
    @endif
  </section>
@endif
