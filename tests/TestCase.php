<?php

namespace Tests;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        if ($user instanceof User && $user->isStaff()) {
            if (! $user->hasConfirmedTwoFactor()) {
                $user->forceFill([
                    'two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP',
                    'two_factor_recovery_codes' => [],
                    'two_factor_confirmed_at' => now(),
                ])->saveQuietly();
            }

            $this->withSession(['two_factor.verified_at' => now()->timestamp]);
        }

        return parent::actingAs($user, $guard);
    }

    protected function actingAsWithoutTwoFactor(Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        return $this;
    }
}
