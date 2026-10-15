@props(['revision', 'label'])

<fieldset class="revision-field">
  <legend>{{ $label }}</legend>
  <p class="revision-field-reason"><strong>Catatan validator:</strong> {{ $revision->reason }}</p>
  {{ $slot }}
</fieldset>
