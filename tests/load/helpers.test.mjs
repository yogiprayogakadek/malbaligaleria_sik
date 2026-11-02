import assert from 'node:assert/strict';

globalThis.__ENV = {};

const { assertSafeTarget, hiddenInput } = await import('./helpers.js');

assert.doesNotThrow(() => assertSafeTarget('http://127.0.0.1:8000'));
assert.throws(() => assertSafeTarget('https://si.malbaligaleria.com'));
assert.throws(() => assertSafeTarget('https://staging.example.test'));

globalThis.__ENV.K6_REMOTE_CONFIRM = 'staging-only';
assert.doesNotThrow(() => assertSafeTarget('https://staging.example.test'));
assert.throws(() => assertSafeTarget('https://sik.malbaligaleria.com'));
assert.equal(hiddenInput('<input name="_token" value="abc123">', '_token'), 'abc123');
assert.equal(hiddenInput('<input value="abc123" name="_token">', '_token'), 'abc123');
