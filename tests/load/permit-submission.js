import http from 'k6/http';
import execution from 'k6/execution';
import { check, fail, sleep } from 'k6';
import { assertSafeTarget, dateAfterToday, hiddenInput, requireEnv } from './helpers.js';

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const sinkEmail = requireEnv('LOAD_TEST_EMAIL');
const sinkPhone = requireEnv('LOAD_TEST_PHONE');
const cameraImage = open('../../public/assets/images/portrait.png', 'b');
const targetVus = Number(__ENV.K6_VUS || 3);

assertSafeTarget(baseUrl);

export const options = {
  scenarios: {
    loading_submission: {
      executor: 'ramping-vus',
      exec: 'submitLoadingPermit',
      stages: [
        { duration: __ENV.K6_RAMP_UP || '20s', target: targetVus },
        { duration: __ENV.K6_HOLD || '40s', target: targetVus },
        { duration: __ENV.K6_RAMP_DOWN || '10s', target: 0 },
      ],
      gracefulStop: '30s',
    },
  },
  thresholds: {
    checks: ['rate>0.99'],
    'http_req_failed{request:loading-submit}': ['rate<0.01'],
    'http_req_duration{request:loading-submit}': ['p(95)<8000'],
  },
};

export function submitLoadingPermit() {
  const form = http.get(`${baseUrl}/loading/create`, { tags: { request: 'loading-form' } });
  const csrfToken = hiddenInput(form.body, '_token');
  const submissionToken = hiddenInput(form.body, 'submission_token');

  if (!check(form, {
    'form loading tersedia': (response) => response.status === 200,
    'token CSRF tersedia': () => Boolean(csrfToken),
    'token pengajuan tersedia': () => Boolean(submissionToken),
  })) {
    fail('Form pengajuan tidak siap. Periksa jam operasional dan konfigurasi staging.');
  }

  const suffix = `${execution.vu.idInTest}-${execution.scenario.iterationInTest}`;
  const response = http.post(`${baseUrl}/loading/store`, {
    _token: csrfToken,
    submission_token: submissionToken,
    tenant_name: `Load Test ${suffix}`,
    applicant_name: `Pemohon Load Test ${suffix}`,
    applicant_phone: sinkPhone,
    applicant_email: sinkEmail,
    direction: 'in',
    start_date: dateAfterToday(1),
    end_date: dateAfterToday(2),
    movement_time: '22:30',
    item_count: '3',
    item_unit: 'koli',
    item_description: `Barang uji staging ${suffix}`,
    id_doc_type: 'ktp',
    id_doc: http.file(cameraImage, `camera-${suffix}.jpg`, 'image/jpeg'),
  }, {
    redirects: 0,
    tags: { request: 'loading-submit' },
  });

  check(response, {
    'pengajuan diterima': (result) => result.status === 302 && (result.headers.Location || '').includes('/loading/success/'),
    'request ID tersedia': (result) => Boolean(result.headers['X-Request-Id']),
  });

  sleep(1);
}
