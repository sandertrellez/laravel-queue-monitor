<?php

namespace alextrellez\QueueMonitor\Tests;

use alextrellez\QueueMonitor\Services\ClassUses;
use alextrellez\QueueMonitor\Tests\Support\MonitoredExtendingJob;
use alextrellez\QueueMonitor\Tests\Support\MonitoredJob;
use alextrellez\QueueMonitor\Traits\IsMonitored;

class ClassUsesTraitTest extends TestCase
{
    public function testUsingMonitorTrait()
    {
        $this->assertArrayHasKey(
            IsMonitored::class,
            ClassUses::classUsesRecursive(MonitoredJob::class)
        );
    }

    public function testUsingMonitorTraitExtended()
    {
        $this->assertArrayHasKey(
            IsMonitored::class,
            ClassUses::classUsesRecursive(MonitoredExtendingJob::class)
        );
    }
}
