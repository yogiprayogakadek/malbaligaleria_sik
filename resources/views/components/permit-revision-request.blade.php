@props(['permit'])

@php
  $revisionFields = app(\App\Services\PermitRevisionService::class)->fieldsFor($permit);
  $revisionType = $permit instanceof \App\Models\LoadingPermit ? 'loading' : 'work';
  $hasRevision = old('has_revision', $errors->has('fields') || collect(old('fields', []))->isNotEmpty() ? '1' : '0');
@endphp

<section class="permit-revision-request" aria-labelledby="revisionRequestTitle">
  <div class="permit-revision-request-head">
    <h3 id="revisionRequestTitle">Minta Perbaikan Data</h3>
    <p>Pilih field yang perlu diperbaiki dan tuliskan alasan yang spesifik.</p>
  </div>
  <form action="{{ route('staff.permit-revisions.store', [$revisionType, $permit->id]) }}" method="POST" data-revision-request-form>
    @csrf
    <div class="pf">
      <label>Apakah ada data yang perlu diperbaiki?</label>
      <div class="pf-radio-group" data-revision-choice>
        <label class="pf-radio"><input type="radio" name="has_revision" value="0" @checked($hasRevision === '0')><svg><use href="#i-check"/></svg><span>Tidak ada perbaikan</span></label>
        <label class="pf-radio"><input type="radio" name="has_revision" value="1" @checked($hasRevision === '1')><svg><use href="#i-edit"/></svg><span>Ada field yang perlu diperbaiki</span></label>
      </div>
    </div>
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
    <button type="submit" class="btn-tr-reject"><svg><use href="#i-edit"/></svg>Kirim Permintaan Perbaikan</button>
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
      const syncChoice = () => {
        const enabled = form.querySelector('input[name="has_revision"]:checked')?.value === '1';
        options.hidden = !enabled;
        options.querySelectorAll('input[type="checkbox"], textarea').forEach(field => {
          if (!enabled) field.disabled = true;
          else if (field.matches('input[type="checkbox"]')) field.disabled = false;
          else field.disabled = !field.closest('[data-revision-row]').querySelector('[data-revision-checkbox]').checked;
        });
      };
      choices.forEach(choice => choice.addEventListener('change', syncChoice));
      syncChoice();
    });
    </script>
  @endpush
@endonce
