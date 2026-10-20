(() => {
  'use strict';

  const form = document.querySelector('[data-permit-draft]');
  if (!form) return;

  const saveUrl = form.dataset.draftSaveUrl;
  const clearUrl = form.dataset.draftClearUrl;
  const status = document.querySelector('[data-draft-status]');
  const clearButton = document.querySelector('[data-draft-clear]');
  const csrf = form.querySelector('input[name="_token"]')?.value;
  let timer;
  let activeRequest;
  let submitting = false;

  const setStatus = (message, state = '') => {
    if (!status) return;
    status.textContent = message;
    status.dataset.state = state;
  };

  const save = async () => {
    if (submitting || !saveUrl || !csrf) return;
    activeRequest?.abort();
    activeRequest = new AbortController();
    setStatus('Menyimpan draft...', 'saving');

    const data = new FormData(form);
    data.delete('id_doc');
    data.delete('assignment_letter');
    data.delete('instruction_letter');
    data.delete('consent');

    try {
      const response = await fetch(saveUrl, {
        method: 'POST',
        body: data,
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
        signal: activeRequest.signal,
      });
      if (!response.ok) throw new Error('Draft tidak dapat disimpan.');
      const payload = await response.json();
      const savedAt = new Date(payload.saved_at);
      const time = Number.isNaN(savedAt.getTime())
        ? ''
        : ` ${savedAt.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Makassar' })} WITA`;
      setStatus(`Draft tersimpan${time}. Lampiran tidak disimpan.`, 'saved');
      if (clearButton) clearButton.hidden = false;
    } catch (error) {
      if (error.name !== 'AbortError') setStatus('Draft belum tersimpan. Periksa koneksi.', 'error');
    }
  };

  const scheduleSave = event => {
    if (submitting || event.target instanceof HTMLInputElement && event.target.type === 'file') return;
    clearTimeout(timer);
    timer = window.setTimeout(save, 1200);
  };

  form.addEventListener('input', scheduleSave);
  form.addEventListener('change', scheduleSave);
  form.addEventListener('permit-draft:submitting', () => {
    submitting = true;
    clearTimeout(timer);
    activeRequest?.abort();
  });

  clearButton?.addEventListener('click', async () => {
    if (!window.confirm('Buang draft dan kosongkan formulir ini?')) return;
    clearButton.disabled = true;
    try {
      const response = await fetch(clearUrl, {
        method: 'DELETE',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error();
      window.location.reload();
    } catch {
      clearButton.disabled = false;
      setStatus('Draft gagal dihapus. Coba lagi.', 'error');
    }
  });
})();
