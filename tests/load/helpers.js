export function requireEnv(name) {
  const value = (__ENV[name] || '').trim();

  if (!value) {
    throw new Error(`${name} wajib diisi.`);
  }

  return value;
}

export function assertSafeTarget(rawUrl) {
  const target = new URL(rawUrl);
  const hostname = target.hostname.toLowerCase();
  const productionHosts = ['si.malbaligaleria.com', 'sik.malbaligaleria.com'];
  const localHosts = ['127.0.0.1', 'localhost', '::1'];

  if (!['http:', 'https:'].includes(target.protocol)) {
    throw new Error('Target harus menggunakan HTTP atau HTTPS.');
  }

  if (productionHosts.includes(hostname)) {
    throw new Error(`Load test ke domain production ${hostname} diblokir.`);
  }

  if (!localHosts.includes(hostname) && __ENV.K6_REMOTE_CONFIRM !== 'staging-only') {
    throw new Error('Target remote hanya boleh dipakai dengan K6_REMOTE_CONFIRM=staging-only.');
  }
}

export function hiddenInput(html, name) {
  const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const input = html.match(new RegExp(`<input\\b(?=[^>]*\\bname=["']${escapedName}["'])[^>]*>`, 'i'));
  const match = input ? input[0].match(/\bvalue=["']([^"']+)["']/i) : null;

  return match ? match[1] : null;
}

export function dateAfterToday(days) {
  return new Date(Date.now() + days * 86400000).toISOString().slice(0, 10);
}
