<?php

namespace violetshih\MongoQueueMonitor\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use violetshih\MongoQueueMonitor\Models\Contracts\MonitorContract;
use violetshih\MongoQueueMonitor\Traits\IsMonitored;
use Throwable;

use Illuminate\Support\Facades\Log;

class QueueMonitor
{
		private const TIMESTAMP_EXACT_FORMAT = 'Y-m-d H:i:s.u';

		public const MAX_BYTES_TEXT = 65535;

		public const MAX_BYTES_LONGTEXT = 4294967295;

		public static $loadMigrations = false;

		public static $model;

		/**
		 * Get the model used to store the monitoring data.
		 *
		 * @return \violetshih\MongoQueueMonitor\Models\Contracts\MonitorContract
		 */
		public static function getModel(): MonitorContract
		{
				return new self::$model();
		}

		/**
		 * Handle Job Processing.
		 *
		 * @param JobProcessing $event
		 *
		 * @return void
		 */
		public static function handleJobProcessing(JobProcessing $event): void
		{
			self::jobStarted($event->job);
		}

		/**
		 * Handle Job Processed.
		 *
		 * @param JobProcessed $event
		 *
		 * @return void
		 */
		public static function handleJobProcessed(JobProcessed $event): void
		{
				self::jobFinished($event->job);
		}

		/**
		 * Handle Job Failing.
		 *
		 * @param JobFailed $event
		 *
		 * @return void
		 */
		public static function handleJobFailed(JobFailed $event): void
		{
			self::jobFinished($event->job, true, $event->exception);
		}

		/**
		 * Handle Job Exception Occurred.
		 *
		 * @param JobExceptionOccurred $event
		 *
		 * @return void
		 */
		public static function handleJobExceptionOccurred(JobExceptionOccurred $event): void
		{
			self::jobFinished($event->job, true, $event->exception);
		}


		/**
		 * Handle Job Queued.
		 *
		 * @param JobQueued $event
		 *
		 * @return void
		 */
		public static function handleJobQueued(JobQueued $event): void
		{
				self::jobQueued($event);
		}

		/**
		 * Get Job ID.
		 *
		 * @param JobContract $job
		 *
		 * @return string|int
		 */
		public static function getJobId(JobContract $job)
		{
				if ($jobId = $job->getJobId()) {
						return $jobId;
				}

				return sha1($job->getRawBody());
		}

		/**
		 * Start Queue Monitoring for Job.
		 *
		 * @param JobContract $job
		 *
		 * @return void
		 */
		protected static function jobQueued(JobQueued $event): void
		{
				// $event->job is an App\Jobs\XXXX instance and not an Illuminate\Contracts\Queue\Job
				if (!self::shouldBeMonitoredClassName(get_class($event->job))) {
						return;
				}

				$now = Carbon::now();

				$model = self::getModel();

				try {

					$model::create([
						'job_id' => $event->id,
						'name' => get_class($event->job),
						'queue' => $event->job->queue ?? 'default',
						'queued_at' => $now,
						'queued_at_exact' => $now->format(self::TIMESTAMP_EXACT_FORMAT),
						'started_at' => Null,
						'started_at_exact' => Null,
						'attempt' => 0,
						'finished_at' => Null,
						'finished_at_exact' => Null,

					]);
				}
				catch(Throwable $e) {
							Log::error('violetshih/MongoQueueMonitor - jobQueued - unable to create Monitor');
							Log::debug($e);
					}

		}


		/**
		 * Start Queue Monitoring for Job.
		 *
		 * @param JobContract $job
		 *
		 * @return void
		 */
		protected static function jobStarted(JobContract $job): void
		{
				if (!self::shouldBeMonitored($job)) {
						return;
				}

				$now = Carbon::now();

					$model = self::getModel();

					$monitor = $model::query()
							->where('job_id', self::getJobId($job))
							->first();

					$attributes = [
						'name' => $job->resolveName(),
						'queue' => $job->getQueue(),
						'started_at' => $now,
						'started_at_exact' => $now->format(self::TIMESTAMP_EXACT_FORMAT),
						'attempt' => $job->attempts(),
					];

				try {
						$monitor->update($attributes);
				}
				catch(Throwable $e) {
						Log::error('violetshih/MongoQueueMonitor - jobStarted - unable to update Monitor : Monitor job id = ' . self::getJobId($job) . ' exists ? ');
						Log::debug($e);
				}
		}

		/**
		 * Finish Queue Monitoring for Job.
		 *
		 * @param JobContract $job
		 * @param bool $failed
		 * @param Throwable|null $exception
		 *
		 * @return void
		 */
		protected static function jobFinished(JobContract $job, bool $failed = false, ?Throwable $exception = null): void
		{
				if (!self::shouldBeMonitored($job)) {
						return;
				}

				$model = self::getModel();

				$monitor = $model::query()
						->where('job_id', self::getJobId($job))
						->where('attempt', $job->attempts())
						->orderByDesc('started_at')
						->first();

				/** @var MonitorContract $monitor */
				$now = Carbon::now();

				try {
					if ($startedAt = $monitor->getStartedAtExact()) {
							$timeElapsed = (float) $startedAt->diffInSeconds($now) + $startedAt->diff($now)->f;
					}

					/** @var IsMonitored $resolvedJob */
					$resolvedJob = $job->resolveName();

					if (null === $exception && false === $resolvedJob::keepMonitorOnSuccess()) {
							$monitor->delete();
							return;
					}

					$attributes = [
							'finished_at' => $now,
							'finished_at_exact' => $now->format(self::TIMESTAMP_EXACT_FORMAT),
							'time_elapsed' => $timeElapsed ?? 0.0,
							'failed' => $failed,
					];

					if (null !== $exception) {
							$attributes += [
									'exception' => mb_strcut((string) $exception, 0, min(PHP_INT_MAX, self::MAX_BYTES_LONGTEXT)),
									'exception_class' => get_class($exception),
									'exception_message' => mb_strcut($exception->getMessage(), 0, self::MAX_BYTES_TEXT),
							];
					}

					$monitor->update($attributes);
				}
				catch(Throwable $e) {
					Log::error('violetshih/MongoQueueMonitor - jobFinished - unable to update Monitor : Monitor job id = ' . self::getJobId($job) . ' exists ? ');
					Log::debug($e);
				}
		}

		/**
		 * Determine weather the Job should be monitored, default true.
		 *
		 * @param JobContract $job
		 *
		 * @return bool
		 */
		public static function shouldBeMonitored(JobContract $job): bool
		{
				return array_key_exists(IsMonitored::class, ClassUses::classUsesRecursive(
						$job->resolveName()
				));
		}

		/**
		 * Determine weather the JobClass should be monitored, default true.
		 *
		 * @param String $classname
		 *
		 * @return bool
		 */
		public static function shouldBeMonitoredClassName(String $className): bool
		{
				return array_key_exists(IsMonitored::class, ClassUses::classUsesRecursive($className));
		}

}
