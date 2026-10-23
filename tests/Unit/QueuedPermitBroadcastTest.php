<?php

namespace Tests\Unit;

use App\Events\LoadingPermitSubmitted;
use App\Events\ValidatorAccountDeactivated;
use App\Events\WorkPermitSubmitted;
use App\Events\WorkPermitWorkflowUpdated;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QueuedPermitBroadcastTest extends TestCase
{
    #[DataProvider('operationalBroadcasts')]
    public function test_operational_permit_broadcasts_are_queued(string $event): void
    {
        $interfaces = class_implements($event);

        $this->assertContains(ShouldBroadcast::class, $interfaces);
        $this->assertNotContains(ShouldBroadcastNow::class, $interfaces);
    }

    public function test_account_deactivation_broadcast_remains_immediate(): void
    {
        $this->assertContains(
            ShouldBroadcastNow::class,
            class_implements(ValidatorAccountDeactivated::class),
        );
    }

    /** @return array<string, array{class-string}> */
    public static function operationalBroadcasts(): array
    {
        return [
            'loading submitted' => [LoadingPermitSubmitted::class],
            'work submitted' => [WorkPermitSubmitted::class],
            'work workflow updated' => [WorkPermitWorkflowUpdated::class],
        ];
    }
}
