<?php

namespace violetshih\MongoQueueMonitor\Tests;

use MongoDB\Laravel\MongodbServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use violetshih\MongoQueueMonitor\Models\MongoMonitorQueueModel;
use violetshih\MongoQueueMonitor\Providers\MongoQueueMonitorProvider;
use violetshih\MongoQueueMonitor\Services\QueueMonitor;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $database = config('database.connections.mongodb.database');
        if ($database !== 'queue_monitor_package_test') {
            $this->fail('拒絕清空非套件測試庫：'.$database);
        }

        MongoMonitorQueueModel::query()->delete();
    }

    protected function getPackageProviders($app)
    {
        return [
            MongodbServiceProvider::class,
            MongoQueueMonitorProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('database.default', 'mongodb');
        $app['config']->set('database.connections.mongodb', [
            'driver' => 'mongodb',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 27017),
            'database' => env('DB_DATABASE', 'queue_monitor_package_test'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', 'admin'),
            'options' => [
                'database' => env('DB_AUTHENTICATION_DATABASE', 'admin'),
            ],
        ]);
        $app['config']->set('queue-monitor.table', 'queue_monitor');
        $app['config']->set('queue-monitor.model', MongoMonitorQueueModel::class);

        QueueMonitor::$model = MongoMonitorQueueModel::class;
    }
}
