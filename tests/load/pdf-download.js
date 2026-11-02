import http from 'k6/http';
import execution from 'k6/execution';
import { check, sleep } from 'k6';
import { assertSafeTarget, requireEnv } from './helpers.js';

const urlFile = requireEnv('PDF_URLS_PATH');
const pdfUrls = open(urlFile)
  .split(/\r?\n/)
  .map((line) => line.trim())
  .filter((line) => line && !line.startsWith('#'));

if (pdfUrls.length === 0) {
  throw new Error('PDF_URLS_PATH tidak berisi URL surat.');
}

pdfUrls.forEach(assertSafeTarget);

export const options = {
  scenarios: {
    pdf_download: {
      executor: 'shared-iterations',
      exec: 'downloadPdf',
      vus: Number(__ENV.K6_VUS || Math.min(3, pdfUrls.length)),
      iterations: Number(__ENV.K6_ITERATIONS || pdfUrls.length),
      maxDuration: __ENV.K6_MAX_DURATION || '5m',
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    'http_req_failed{request:permit-pdf}': ['rate<0.01'],
    'http_req_duration{request:permit-pdf}': ['p(95)<5000'],
  },
};

export function downloadPdf() {
  const url = pdfUrls[execution.scenario.iterationInTest % pdfUrls.length];
  const response = http.get(url, {
    redirects: 0,
    tags: { request: 'permit-pdf' },
  });

  check(response, {
    'surat berhasil diunduh': (result) => result.status === 200,
    'respons berupa PDF': (result) => (result.headers['Content-Type'] || '').startsWith('application/pdf'),
    'request ID tersedia': (result) => Boolean(result.headers['X-Request-Id']),
  });

  sleep(1);
}
