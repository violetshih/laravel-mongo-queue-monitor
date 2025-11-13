<?php

namespace violetshih\MongoQueueMonitor\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

use violetshih\MongoQueueMonitor\Routes\MongoQueueMonitorRoutes;
use violetshih\MongoQueueMonitor\Services\QueueMonitor;
use violetshih\MongoQueueMonitor\Providers\MongoQueueMonitorComponentsProvider;

class MongoQueueMonitorProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            if (QueueMonitor::$loadMigrations) {
                $this->loadMigrationsFrom(
                    __DIR__ . '/../../migrations'
                );
            }

            $this->publishes([
                __DIR__ . '/../../config/queue-monitor.php' => config_path('queue-monitor.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../../migrations' => database_path('migrations'),
            ], 'migrations');
        }

        $this->loadViewsFrom(
            __DIR__ . '/../../views', 'queue-monitor'
        );

        Route::mixin(new MongoQueueMonitorRoutes());

        //handle Job Creation event
        Event::listen(JobQueued::class, [QueueMonitor::class, 'handleJobQueued']);

        /** @var \Illuminate\Queue\QueueManager $manager */
        $manager = app(\Illuminate\Queue\QueueManager::class);

        $manager->before(static function (JobProcessing $event) {
            QueueMonitor::handleJobProcessing($event);
        });

        $manager->after(static function (JobProcessed $event) {
            QueueMonitor::handleJobProcessed($event);
        });

        $manager->failing(static function (JobFailed $event) {
            QueueMonitor::handleJobFailed($event);
        });

        $manager->exceptionOccurred(static function (JobExceptionOccurred $event) {
            QueueMonitor::handleJobExceptionOccurred($event);
        });

    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
				//Register Blade X Components
				$this->app->register('violetshih\MongoQueueMonitor\Providers\MongoQueueMonitorComponentsProvider');

				if (!$this->app->configurationIsCached()) {
            $this->mergeConfigFrom(
                __DIR__ . '/../../config/queue-monitor.php',
                'queue-monitor'
            );
        }

        QueueMonitor::$model = config('queue-monitor.model') ?: MongoMonitorQueueModel::class;
    }
}
