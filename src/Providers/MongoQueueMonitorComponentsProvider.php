<?php

namespace violetshih\MongoQueueMonitor\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use violetshih\MongoQueueMonitor\Components\MetricCard;
use violetshih\MongoQueueMonitor\Components\FiltersForm;
use violetshih\MongoQueueMonitor\Components\JobsList;
use violetshih\MongoQueueMonitor\Components\JobLine;
use violetshih\MongoQueueMonitor\Components\JobDeleteForm;
use violetshih\MongoQueueMonitor\Components\JobPurgeForm;
use violetshih\MongoQueueMonitor\Components\JobRetryForm;

class MongoQueueMonitorComponentsProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */

		public function boot()
		{
		    Blade::component('metric-card', MetricCard::class);
				Blade::component('filters-form', FiltersForm::class);
				Blade::component('jobs-list', JobsList::class);
				Blade::component('job-line', JobLine::class);
				Blade::component('job-delete-form', JobDeleteForm::class);
				Blade::component('job-purge-form', JobPurgeForm::class);
				Blade::component('job-retry-form', JobRetryForm::class);
		}

		/**
		 * Register the application services.
		 *
		 * @return void
		 */
		public function register()
		{
		}
}
