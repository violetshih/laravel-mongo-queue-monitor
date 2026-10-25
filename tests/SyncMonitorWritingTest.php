<?php

namespace violetshih\MongoQueueMonitor\Tests;

use violetshih\MongoQueueMonitor\Models\MongoMonitorQueueModel;
use violetshih\MongoQueueMonitor\Tests\Support\IntentionallyFailedException;
use violetshih\MongoQueueMonitor\Tests\Support\MonitoredFailingJob;
use violetshih\MongoQueueMonitor\Tests\Support\MonitoredJob;

class SyncMonitorWritingTest extends TestCase
{
    public function test_sync_success_writes_queue_monitor(): void
    {
        dispatch(new MonitoredJob());

        $monitor = MongoMonitorQueueModel::query()
            ->where('name', MonitoredJob::class)
            ->first();

        $this->assertNotNull($monitor);
        $this->assertFalse((bool) $monitor->failed);
        $this->assertNotNull($monitor->started_at);
        $this->assertNotNull($monitor->finished_at);
        $this->assertTrue($monitor->hasSucceeded());
    }

    public function test_sync_exception_writes_failed_queue_monitor(): void
    {
        try {
            dispatch(new MonitoredFailingJob());
            $this->fail('Expected IntentionallyFailedException');
        } catch (IntentionallyFailedException $e) {
            $this->assertSame('Whoops', $e->getMessage());
        }

        $monitor = MongoMonitorQueueModel::query()
            ->where('name', MonitoredFailingJob::class)
            ->first();

        $this->assertNotNull($monitor);
        $this->assertTrue((bool) $monitor->failed);
        $this->assertSame(IntentionallyFailedException::class, $monitor->exception_class);
        $this->assertSame('Whoops', $monitor->exception_message);
        $this->assertTrue($monitor->hasFailed());
    }
}
