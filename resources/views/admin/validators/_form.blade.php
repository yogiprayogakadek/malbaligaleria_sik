<form action="{{ $action }}" method="POST" class="admin-form admin-validator-form" data-validator-form data-availability-url="{{ $availabilityUrl }}" novalidate>
  @csrf
  @if($method !== 'POST') @method($method) @endif

  <div class="admin-form-grid">
    <label>Nama lengkap
      <input type="text" name="name" value="{{ old('name', $validator?->name) }}" required minlength="3" maxlength="100" autocomplete="name" aria-describedby="name-feedback">
      <span class="admin-field-feedback @error('name') is-error @enderror" id="name-feedback" aria-live="polite">@error('name'){{ $message }}@enderror</span>
    </label>
    <label>Divisi
      <select name="division" required aria-describedby="division-feedback">
        <option value="">Pilih divisi</option>
        @foreach(config('divisions') as $code => $label)<option value="{{ $code }}" {{ old('division', $validator?->division) === $code ? 'selected' : '' }}>{{ $code }} - {{ $label }}</option>@endforeach
      </select>
      <span class="admin-field-feedback @error('division') is-error @enderror" id="division-feedback" aria-live="polite">@error('division'){{ $message }}@enderror</span>
    </label>
    <label>Email
      <input type="email" name="email" value="{{ old('email', $validator?->email) }}" required maxlength="120" autocomplete="off" data-availability-field aria-describedby="email-feedback">
      <span class="admin-field-feedback @error('email') is-error @enderror" id="email-feedback" aria-live="polite">@error('email'){{ $message }}@enderror</span>
    </label>
    <label>Nomor telepon
      <input type="tel" name="phone" value="{{ old('phone', $validator?->phone) }}" required minlength="9" maxlength="20" pattern="[0-9+()\-\s]+" autocomplete="off" data-availability-field aria-describedby="phone-feedback">
      <span class="admin-field-feedback @error('phone') is-error @enderror" id="phone-feedback" aria-live="polite">@error('phone'){{ $message }}@enderror</span>
    </label>
    <label>Kata sandi {{ $validator ? 'baru' : 'sementara' }}
      <input type="password" name="password" {{ $validator ? '' : 'required' }} minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password-feedback">
      <span class="admin-field-feedback @error('password') is-error @enderror" id="password-feedback" aria-live="polite">@error('password'){{ $message }}@else{{ $validator ? 'Kosongkan jika tidak ingin mengganti kata sandi.' : 'Minimal 12 karakter dengan huruf besar, huruf kecil, angka, dan simbol.' }}@enderror</span>
    </label>
    <label>Konfirmasi kata sandi
      <input type="password" name="password_confirmation" {{ $validator ? '' : 'required' }} minlength="12" maxlength="255" autocomplete="new-password" aria-describedby="password_confirmation-feedback">
      <span class="admin-field-feedback" id="password_confirmation-feedback" aria-live="polite"></span>
    </label>
  </div>

  <div class="admin-form-actions admin-validator-form-actions">
    <a href="{{ route('admin.validators.index') }}" class="admin-secondary-button"><svg><use href="#i-arrow-left"/></svg>Kembali</a>
    <button type="submit" class="admin-primary-button"><svg><use href="#i-check"/></svg>{{ $submitLabel }}</button>
  </div>
</form>
