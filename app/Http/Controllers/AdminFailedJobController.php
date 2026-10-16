<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\View\View;
use stdClass;

class AdminFailedJobController extends Controller
{
    public function index(): View
    {
        $jobs = $this->failedJobsQuery()
            ->latest('failed_at')
            ->paginate(25)
            ->through(fn (stdClass $job): object => $this->safeJob($job));

        return view('admin.failed-jobs.index', [
            'jobs' => $jobs,
            'failedCount' => $this->failedJobsQuery()->count(),
            'failedTodayCount' => $this->failedJobsQuery()->where('failed_at', '>=', now()->startOfDay())->count(),
        ]);
    }

    public function confirm(string $uuid): View
    {
        $job = $this->failedJobsQuery()->where('uuid', $uuid)->first();
        abort_unless($job, 404);

        return view('admin.failed-jobs.confirm', ['job' => $this->safeJob($job)]);
    }

    public function retry(Request $request, string $uuid): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ], [
            'password.required' => 'Kata sandi administrator wajib diisi.',
            'password.current_password' => 'Kata sandi administrator tidak sesuai.',
        ]);

        $database = (string) config('queue.failed.database');
        $table = (string) config('queue.failed.table', 'failed_jobs');

        $job = DB::connection($database)->transaction(function () use ($database, $table, $uuid): object {
            $failedJob = DB::connection($database)->table($table)
                ->where('uuid', $uuid)
                ->lockForUpdate()
                ->first();

            abort_unless($failedJob, 404);

            Queue::connection($failedJob->connection)->pushRaw($failedJob->payload, $failedJob->queue);
            DB::connection($database)->table($table)->where('uuid', $uuid)->delete();

            return $this->safeJob($failedJob);
        }, 3);

        AuditLog::query()->create([
            'actor_id' => $request->user()->id,
            'event' => 'retried',
            'auditable_type' => 'queue_job',
            'route_name' => $request->route()?->getName(),
            'request_method' => $request->method(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => [
                'job_uuid' => $job->uuid,
                'job_name' => $job->name,
                'connection' => $job->connection,
                'queue' => $job->queue,
            ],
        ]);

        return redirect()->route('admin.failed-jobs.index')
            ->with('success', 'Pekerjaan dimasukkan kembali ke antrean. Pantau status worker pada Kesehatan Sistem.');
    }

    private function failedJobsQuery(): Builder
    {
        return DB::connection((string) config('queue.failed.database'))
            ->table((string) config('queue.failed.table', 'failed_jobs'));
    }

    private function safeJob(stdClass $job): object
    {
        $payload = json_decode((string) $job->payload, true);
        $displayName = is_array($payload) ? ($payload['displayName'] ?? null) : null;
        $displayName = is_string($displayName) && preg_match('/^[A-Za-z0-9_\\\\]+$/', $displayName)
            ? class_basename($displayName)
            : 'Pekerjaan antrean';

        $exceptionClass = Str::before((string) $job->exception, ':');
        $exceptionClass = preg_match('/^[A-Za-z0-9_\\\\]+$/', $exceptionClass)
            ? class_basename($exceptionClass)
            : 'Kesalahan proses';

        return (object) [
            'uuid' => (string) $job->uuid,
            'name' => $displayName,
            'connection' => (string) $job->connection,
            'queue' => (string) $job->queue,
            'failure_type' => $exceptionClass,
            'failed_at' => Carbon::parse($job->failed_at)->timezone(config('app.timezone')),
        ];
    }
}
