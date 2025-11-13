<?php

namespace violetshih\MongoQueueMonitor\Tests;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use violetshih\MongoQueueMonitor\Controllers\RetryMonitorController;
use violetshih\MongoQueueMonitor\Models\Monitor;
use violetshih\MongoQueueMonitor\Models\MongoMonitorQueueModel;
use violetshih\MongoQueueMonitor\Tests\Support\MonitoredFailingJob;

class RetryMonitorTest extends TestCase
{
    public function testRetryController()
    {
        // Create a failed job monitor record
        $monitor = Monitor::create([
            'job_id' => 'test-job-id',
            'name' => MonitoredFailingJob::class,
            'queue' => 'default',
            'queued_at' => now(),
            'queued_at_exact' => now()->format('Y-m-d H:i:s.u'),
            'started_at' => now()->subMinute(),
            'started_at_exact' => now()->subMinute()->format('Y-m-d H:i:s.u'),
            'finished_at' => now(),
            'finished_at_exact' => now()->format('Y-m-d H:i:s.u'),
            'time_elapsed' => 60.0,
            'failed' => true,
            'attempt' => 1,
            'exception' => 'Test exception',
            'exception_class' => \Exception::class,
            'exception_message' => 'Test exception message',
            'data' => json_encode(['test' => 'data'])
        ]);

        // Mock the queue facade
        Queue::shouldReceive('push')->once()->with(\Mockery::type(MonitoredFailingJob::class));

        // Create the controller and request
        $controller = new RetryMonitorController();
        $request = Request::create('/retry', 'POST', ['viewname' => 'queue-monitor::jobs']);
        $request->setRouteResolver(function () use ($monitor) {
            return (new \Illuminate\Routing\Route(['POST'], '/monitors/{monitor}/retry', [RetryMonitorController::class, '__invoke']))
                ->bind($monitor);
        });

        // Execute the controller
        $response = $controller($request, $monitor);

        // Assert the response is a redirect
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertEquals(route('queue-monitor::index', ['viewname' => 'queue-monitor::jobs']), $response->getTargetUrl());

        // Assert the job was retried (queue push was called)
        Queue::shouldHaveReceived('push');
    }

    public function testRetryControllerWithInvalidJob()
    {
        // Create a monitor record with invalid job class
        $monitor = Monitor::create([
            'job_id' => 'test-job-id-invalid',
            'name' => 'Invalid\Job\Class',
            'queue' => 'default',
            'queued_at' => now(),
            'queued_at_exact' => now()->format('Y-m-d H:i:s.u'),
            'started_at' => now()->subMinute(),
            'started_at_exact' => now()->subMinute()->format('Y-m-d H:i:s.u'),
            'finished_at' => now(),
            'finished_at_exact' => now()->format('Y-m-d H:i:s.u'),
            'time_elapsed' => 60.0,
            'failed' => true,
            'attempt' => 1,
            'exception' => 'Test exception',
            'exception_class' => \Exception::class,
            'exception_message' => 'Test exception message',
            'data' => json_encode(['test' => 'data'])
        ]);

        // Mock the queue facade to not be called
        Queue::shouldReceive('push')->never();

        // Create the controller and request
        $controller = new RetryMonitorController();
        $request = Request::create('/retry', 'POST', ['viewname' => 'queue-monitor::jobs']);
        $request->setRouteResolver(function () use ($monitor) {
            return (new \Illuminate\Routing\Route(['POST'], '/monitors/{monitor}/retry', [RetryMonitorController::class, '__invoke']))
                ->bind($monitor);
        });

        // Execute the controller
        $response = $controller($request, $monitor);

        // Assert the response is a redirect
        $this->assertInstanceOf(\Illuminate\Http\RedirectResponse::class, $response);
        $this->assertEquals(route('queue-monitor::index', ['viewname' => 'queue-monitor::jobs']), $response->getTargetUrl());

        // Assert the job was not retried
        Queue::shouldHaveReceived('push', 0);
    }
}