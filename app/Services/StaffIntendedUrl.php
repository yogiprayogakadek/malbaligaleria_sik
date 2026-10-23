<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class StaffIntendedUrl
{
    public function pull(Request $request, User $user): ?string
    {
        $intended = $request->session()->pull('url.intended');

        return $this->resolve($request, $user, $intended);
    }

    private function resolve(Request $request, User $user, mixed $intended): ?string
    {
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        $parts = parse_url($intended);
        if ($parts === false || ! $this->sameOrigin($request, $parts)) {
            return null;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');

        return collect($this->allowedPrefixes($user))
            ->contains(fn (string $prefix): bool => $path === $prefix || str_starts_with($path, $prefix.'/'))
                ? $intended
                : null;
    }

    /** @param array<string, int|string> $parts */
    private function sameOrigin(Request $request, array $parts): bool
    {
        if (isset($parts['host']) && strcasecmp((string) $parts['host'], $request->getHost()) !== 0) {
            return false;
        }

        if (isset($parts['scheme']) && strcasecmp((string) $parts['scheme'], $request->getScheme()) !== 0) {
            return false;
        }

        if (! isset($parts['host'])) {
            return true;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? $request->getScheme()));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return $port === $request->getPort();
    }

    /** @return list<string> */
    private function allowedPrefixes(User $user): array
    {
        $prefixes = match (true) {
            $user->isAdmin() => ['/admin'],
            $user->isSecretary() => ['/secretary'],
            $user->isValidator() && $user->division === 'TR' => ['/tr'],
            $user->isValidator() && $user->division === 'MEP' => ['/mep'],
            $user->isValidator() && $user->division === 'FIN' => ['/finance'],
            $user->isValidator() && $user->division === 'EP' => ['/ep'],
            $user->isValidator() && $user->division === 'SEC' => ['/security'],
            default => [],
        };

        if ($user->isAdmin() || $user->isSecretary() || ($user->isValidator() && in_array($user->division, ['TR', 'MEP', 'FIN', 'EP'], true))) {
            $prefixes[] = '/staff/work-permits';
        }

        return $prefixes;
    }
}
