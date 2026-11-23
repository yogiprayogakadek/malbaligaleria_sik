@props(['permit'])

@php
  $revisionFields = app(\App\Services\PermitRevisionService::class)->fieldsFor($permit);
  $revisionType = $permit instanceof \App\Models\LoadingPermit ? 'loading' : 'work';
  $hasRevision = old('has_revision', $errors->has('fields') || collect(old('fields', []))->isNotEmpty() ? '1' : '0');
  $isMepPermit = $permit instanceof \App\Models\WorkPermit && $permit->assigned_division === 'MEP';
  $coordinationRequired = $isMepPermit ? old('coordination_required', '0') : '0';
@endphp

<section class="permit-revision-request" aria-labelledby="revisionRequestTitle">
  <div class="permit-revision-request-head">
    <h3 id="revisionRequestTitle">{{ $isMepPermit ? 'Tindak Lanjut Pemeriksaan' : 'Minta Perbaikan Data' }}</h3>
    <p>{{ $isMepPermit ? 'Tentukan perbaikan data dan kebutuhan koordinasi sebelum menetapkan deposit.' : 'Pilih field yang perlu diperbaiki dan tuliskan alasan yang spesifik.' }}</p>
  </div>
  <form action="{{ route('staff.permit-revisions.store', [$revisionType, $permit->id]) }}" method="POST" data-revision-request-form data-staff-action-progress="Mengirim tindak lanjut pemeriksaan">
    @csrf
    <div class="pf">
      <label>Apakah ada data yang perlu diperbaiki?</label>
      <div class="pf-radio-group" data-revision-choice>
        <label class="pf-radio"><input type="radio" name="has_revision" value="0" @checked($hasRevision === '0')><svg><use href="#i-check"/></svg><span>Tidak ada perbaikan</span></label>
        <label class="pf-radio"><input type="radio" name="has_revision" value="1" @checked($hasRevision === '1')><svg><use href="#i-edit"/></svg><span>Ada field yang perlu diperbaiki</span></label>
      </div>
    </div>
    @if($isMepPermit)
      <div class="pf permit-coordination-choice">
        <label>Koordinasi dengan Office Management</label>
        <div class="pf-radio-group" data-coordination-choice>
          <label class="pf-radio"><input type="radio" name="coordination_required" value="0" @checked($coordinationRequired === '0')><svg><use href="#i-check"/></svg><span>Tidak perlu koordinasi lebih lanjut</span></label>
          <label class="pf-radio"><input type="radio" name="coordination_required" value="1" @checked($coordinationRequired === '1')><svg><use href="#i-info"/></svg><span>Perlu koordinasi dengan Office Management</span></label>
        </div>
        <span class="pf-hint">Pemohon akan diarahkan ke Office Management untuk berkoordinasi dengan MEP.</span>
      </div>
      <div class="pf" data-coordination-notes @if($coordinationRequired !== '1') hidden @endif>
        <label for="coordination_notes">Instruksi koordinasi <span class="req">*</span></label>
        <textarea id="coordination_notes" name="coordination_notes" rows="3" minlength="10" maxlength="500" @required($coordinationRequired === '1') @disabled($coordinationRequired !== '1')>{{ old('coordination_notes') }}</textarea>
        <span class="pf-hint">Jelaskan kebutuhan yang harus dikoordinasikan dengan Office Management.</span>
        @error('coordination_notes')<span class="pf-error">{{ $message }}</span>@enderror
      </div>
    @endif
    <div class="revision-request-options" data-revision-options @if($hasRevision !== '1') hidden @endif>
    <div class="revision-request-list">
      @foreach($revisionFields as $field => $label)
        <div class="revision-request-row" data-revision-row>
          <label class="revision-request-check" for="revision_field_{{ $field }}">
            <input id="revision_field_{{ $field }}" type="checkbox" name="fields[]" value="{{ $field }}" data-revision-checkbox aria-controls="revision_reason_wrap_{{ $field }}" aria-expanded="{{ in_array($field, old('fields', []), true) ? 'true' : 'false' }}" @checked(in_array($field, old('fields', []), true))>
            <span>{{ $label }}</span>
          </label>
          <div id="revision_reason_wrap_{{ $field }}" class="revision-request-reason" data-revision-reason @if(!in_array($field, old('fields', []), true)) hidden @endif>
            <label for="revision_reason_{{ $field }}">Alasan perbaikan</label>
            <textarea id="revision_reason_{{ $field }}" name="reasons[{{ $field }}]" rows="2" minlength="10" maxlength="500" @disabled(!in_array($field, old('fields', []), true))>{{ old("reasons.{$field}") }}</textarea>
            @error("reasons.{$field}")<span class="pf-error">{{ $message }}</span>@enderror
          </div>
        </div>
      @endforeach
    </div>
    @error('fields')<span class="pf-error revision-request-error">{{ $message }}</span>@enderror
    </div>
    <div data-revision-submit @if($hasRevision !== '1' && $coordinationRequired !== '1') hidden @endif>
      <button type="submit" class="btn-tr-reject"><svg><use href="#i-edit"/></svg>Kirim Tindak Lanjut</button>
    </div>
  </form>
</section>

@once
  @push('scripts')
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
    document.querySelectorAll('[data-revision-checkbox]').forEach(checkbox => {
      const sync = (shouldFocus = false) => {
        const reason = checkbox.closest('[data-revision-row]').querySelector('[data-revision-reason]');
        const textarea = reason.querySelector('textarea');
        reason.hidden = !checkbox.checked;
        textarea.disabled = !checkbox.checked;
        textarea.required = checkbox.checked;
        checkbox.setAttribute('aria-expanded', checkbox.checked ? 'true' : 'false');
        checkbox.closest('[data-revision-row]').classList.toggle('is-selected', checkbox.checked);
        if (checkbox.checked && shouldFocus) textarea.focus();
      };
      checkbox.addEventListener('change', () => sync(true));
      sync();
    });
    document.querySelectorAll('[data-revision-request-form]').forEach(form => {
      const options = form.querySelector('[data-revision-options]');
      const choices = form.querySelectorAll('input[name="has_revision"]');
      const coordinationChoices = form.querySelectorAll('input[name="coordination_required"]');
      const coordinationNotes = form.querySelector('[data-coordination-notes]');
      const coordinationTextarea = coordinationNotes?.querySelector('textarea');
      const submit = form.querySelector('[data-revision-submit]');
      const syncChoice = () => {
        const enabled = form.querySelector('input[name="has_revision"]:checked')?.value === '1';
        const coordinationEnabled = form.querySelector('input[name="coordination_required"]:checked')?.value === '1';
        options.hidden = !enabled;
        options.querySelectorAll('input[type="checkbox"], textarea').forEach(field => {
          if (!enabled) field.disabled = true;
          else if (field.matches('input[type="checkbox"]')) field.disabled = false;
          else field.disabled = !field.closest('[data-revision-row]').querySelector('[data-revision-checkbox]').checked;
        });
        if (coordinationNotes && coordinationTextarea) {
          coordinationNotes.hidden = !coordinationEnabled;
          coordinationTextarea.disabled = !coordinationEnabled;
          coordinationTextarea.required = coordinationEnabled;
        }
        submit.hidden = !enabled && !coordinationEnabled;

        const depositForm = document.querySelector('[data-deposit-decision]');
        if (depositForm) {
          depositForm.hidden = coordinationEnabled;
          depositForm.querySelectorAll('input[name="deposit_required"]').forEach(input => input.disabled = coordinationEnabled);
        }
      };
      choices.forEach(choice => choice.addEventListener('change', syncChoice));
      coordinationChoices.forEach(choice => choice.addEventListener('change', syncChoice));
      syncChoice();
    });
    </script>
  @endpush
@endonce
