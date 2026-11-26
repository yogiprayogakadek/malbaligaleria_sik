@props(['permit'])

@php
  $revisionFields = app(\App\Services\PermitRevisionService::class)->fieldsFor($permit);
  $revisionType = $permit instanceof \App\Models\LoadingPermit ? 'loading' : 'work';
  $hasRevision = old('has_revision', $errors->has('fields') || collect(old('fields', []))->isNotEmpty() ? '1' : '0');
  $isMepPermit = $permit instanceof \App\Models\WorkPermit && $permit->assigned_division === 'MEP';
  $coordinationRequired = $isMepPermit ? old('coordination_required', '0') : '0';
  $depositChoice = $isMepPermit
    ? old('deposit_required', $permit->deposit_required === null ? null : ($permit->deposit_required ? '1' : '0'))
    : null;
  $defaultRecipientName = $isMepPermit ? $permit->defaultDepositInvoiceRecipientName() : '';
  $draftRecipientName = $isMepPermit
    ? old(
      'deposit_invoice_recipient_name',
      $permit->deposit_invoice_recipient_source === \App\Models\WorkPermit::DEPOSIT_INVOICE_RECIPIENT_SOURCE_CUSTOM
        ? ($permit->deposit_invoice_recipient_name ?? '')
        : '',
    )
    : '';
  $draftRecipientAddress = $isMepPermit
    ? old('deposit_invoice_recipient_address', $permit->deposit_invoice_recipient_address ?? '')
    : '';
@endphp

<section class="permit-revision-request" aria-labelledby="revisionRequestTitle">
  <div class="permit-revision-request-head">
    <h3 id="revisionRequestTitle">{{ $isMepPermit ? 'Pemeriksaan Engineering' : 'Minta Perbaikan Data' }}</h3>
    <p>{{ $isMepPermit ? 'Catat tindak lanjut dan keputusan deposit dalam satu pemeriksaan.' : 'Pilih field yang perlu diperbaiki dan tuliskan alasan yang spesifik.' }}</p>
  </div>
  <form action="{{ $isMepPermit ? route('mep.work-permits.deposit', $permit->public_token) : route('staff.permit-revisions.store', [$revisionType, $permit->id]) }}" method="POST" class="tr-decision-form" data-revision-request-form @if($isMepPermit) data-deposit-decision @endif data-staff-action-progress="Menyimpan pemeriksaan">
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
        <label for="coordination_notes">Instruksi koordinasi <span class="pf-optional">Opsional</span></label>
        <textarea id="coordination_notes" name="coordination_notes" rows="3" maxlength="500" @disabled($coordinationRequired !== '1')>{{ old('coordination_notes') }}</textarea>
        <span class="pf-hint">Isi jika ada hal khusus yang perlu dibahas dengan Office Management.</span>
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
    @if($isMepPermit)
      <div class="permit-deposit-decision">
        <div class="pf"><label>Keputusan Security Deposit <span class="req">*</span></label><div class="pf-radio-group">
          <label class="pf-radio"><input type="radio" name="deposit_required" value="0" required @checked($depositChoice === '0')><svg><use href="#i-check"/></svg><span>Tidak diperlukan</span></label>
          <label class="pf-radio"><input type="radio" name="deposit_required" value="1" required @checked($depositChoice === '1')><svg><use href="#i-lock"/></svg><span>Diperlukan</span></label>
        </div></div>
        <div class="pf" data-deposit-amount @if($depositChoice !== '1') hidden @endif><label for="deposit_amount">Nominal Deposit <span class="req">*</span></label><div class="pf-wrap"><span class="pf-prefix">Rp</span><input id="deposit_amount" name="deposit_amount" type="number" min="1" max="999999999999.99" step="1" inputmode="numeric" value="{{ old('deposit_amount', $permit->deposit_amount) }}" placeholder="Contoh: 5000000" @required($depositChoice === '1')></div>@error('deposit_amount')<span class="pf-error">{{ $message }}</span>@enderror</div>
        <div class="deposit-recipient-section" data-deposit-recipient @if($depositChoice !== '1') hidden @endif>
          <div class="deposit-recipient-head">
            <h4 class="deposit-recipient-title">Tujuan penagihan invoice</h4>
            <p class="deposit-recipient-note">Jika dikosongkan, invoice memakai data tenant dan kontraktor dari permohonan ({{ $defaultRecipientName }}) tanpa baris alamat.</p>
          </div>
          <div class="pf">
            <label for="deposit_invoice_recipient_name">Nama penerima / perusahaan <span class="pf-optional">Opsional</span></label>
            <input id="deposit_invoice_recipient_name" name="deposit_invoice_recipient_name" type="text" maxlength="200" value="{{ $draftRecipientName }}" placeholder="Contoh: PT Nama Perusahaan" @disabled($depositChoice !== '1') aria-describedby="deposit_invoice_recipient_name_hint{{ $errors->has('deposit_invoice_recipient_name') ? ' deposit_invoice_recipient_name_error' : '' }}" @if($errors->has('deposit_invoice_recipient_name')) aria-invalid="true" @endif>
            <span id="deposit_invoice_recipient_name_hint" class="pf-hint">Kosongkan untuk memakai {{ $defaultRecipientName }}.</span>
            @error('deposit_invoice_recipient_name')<span id="deposit_invoice_recipient_name_error" class="pf-error">{{ $message }}</span>@enderror
          </div>
          <div class="pf">
            <label for="deposit_invoice_recipient_address">Alamat penagihan <span class="pf-optional">Opsional</span></label>
            <textarea id="deposit_invoice_recipient_address" name="deposit_invoice_recipient_address" rows="3" maxlength="500" placeholder="Isi alamat penagihan jika perlu dicetak pada invoice" @disabled($depositChoice !== '1') aria-describedby="deposit_invoice_recipient_address_hint{{ $errors->has('deposit_invoice_recipient_address') ? ' deposit_invoice_recipient_address_error' : '' }}" @if($errors->has('deposit_invoice_recipient_address')) aria-invalid="true" @endif>{{ $draftRecipientAddress }}</textarea>
            <span id="deposit_invoice_recipient_address_hint" class="pf-hint">Dapat menggunakan beberapa baris. Jika kosong, baris alamat tidak dicetak pada invoice.</span>
            @error('deposit_invoice_recipient_address')<span id="deposit_invoice_recipient_address_error" class="pf-error">{{ $message }}</span>@enderror
          </div>
        </div>
        <div class="pf"><label for="deposit_notes">Catatan Management</label><textarea id="deposit_notes" name="deposit_notes" rows="3" maxlength="1000" placeholder="Dasar penetapan deposit atau catatan pemeriksaan">{{ old('deposit_notes', $permit->deposit_notes) }}</textarea></div>
        <span class="pf-hint">Jika deposit diperlukan, invoice diterbitkan setelah tindak lanjut selesai. Surat terbit otomatis setelah Finance memverifikasi pembayaran.</span>
      </div>
    @endif
    <div data-revision-submit @if(!$isMepPermit && $hasRevision !== '1' && $coordinationRequired !== '1') hidden @endif>
      <button type="submit" class="{{ $isMepPermit ? 'btn-primary' : 'btn-tr-reject' }}"><svg><use href="#{{ $isMepPermit ? 'i-check' : 'i-edit' }}"/></svg>{{ $isMepPermit ? 'Simpan Pemeriksaan Engineering' : 'Kirim Tindak Lanjut' }}</button>
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
          coordinationTextarea.required = false;
        }
        submit.hidden = form.hasAttribute('data-deposit-decision') ? false : !enabled && !coordinationEnabled;

      };
      choices.forEach(choice => choice.addEventListener('change', syncChoice));
      coordinationChoices.forEach(choice => choice.addEventListener('change', syncChoice));
      syncChoice();
    });
    </script>
  @endpush
@endonce
