@props(['permit'])

@php
  $revisionFields = app(\App\Services\PermitRevisionService::class)->fieldsFor($permit);
  $revisionType = $permit instanceof \App\Models\LoadingPermit ? 'loading' : 'work';
@endphp

<section class="permit-revision-request" aria-labelledby="revisionRequestTitle">
  <div class="permit-revision-request-head">
    <h3 id="revisionRequestTitle">Minta Perbaikan Data</h3>
    <p>Pilih field yang perlu diperbaiki dan tuliskan alasan yang spesifik.</p>
  </div>
  <form action="{{ route('staff.permit-revisions.store', [$revisionType, $permit->id]) }}" method="POST" data-revision-request-form>
    @csrf
    <div class="revision-request-list">
      @foreach($revisionFields as $field => $label)
        <div class="revision-request-row" data-revision-row>
          <label class="revision-request-check">
            <input type="checkbox" name="fields[]" value="{{ $field }}" data-revision-checkbox @checked(in_array($field, old('fields', []), true))>
            <span>{{ $label }}</span>
          </label>
          <div class="revision-request-reason" data-revision-reason @if(!in_array($field, old('fields', []), true)) hidden @endif>
            <label for="revision_reason_{{ $field }}">Alasan perbaikan</label>
            <textarea id="revision_reason_{{ $field }}" name="reasons[{{ $field }}]" rows="2" minlength="10" maxlength="500" @disabled(!in_array($field, old('fields', []), true))>{{ old("reasons.{$field}") }}</textarea>
            @error("reasons.{$field}")<span class="pf-error">{{ $message }}</span>@enderror
          </div>
        </div>
      @endforeach
    </div>
    @error('fields')<span class="pf-error revision-request-error">{{ $message }}</span>@enderror
    <button type="submit" class="btn-tr-reject"><svg><use href="#i-edit"/></svg>Kirim Permintaan Perbaikan</button>
  </form>
</section>

@once
  @push('scripts')
    <script>
    document.querySelectorAll('[data-revision-checkbox]').forEach(checkbox => {
      const sync = (shouldFocus = false) => {
        const reason = checkbox.closest('[data-revision-row]').querySelector('[data-revision-reason]');
        const textarea = reason.querySelector('textarea');
        reason.hidden = !checkbox.checked;
        textarea.disabled = !checkbox.checked;
        textarea.required = checkbox.checked;
        if (checkbox.checked && shouldFocus) textarea.focus();
      };
      checkbox.addEventListener('change', () => sync(true));
      sync();
    });
    </script>
  @endpush
@endonce
