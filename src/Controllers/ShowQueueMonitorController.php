<?php

namespace violetshih\MongoQueueMonitor\Controllers;

use Carbon\Carbon;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Eloquent\Builder;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

use violetshih\MongoQueueMonitor\Controllers\Payloads\Metric;
use violetshih\MongoQueueMonitor\Controllers\Payloads\Metrics;
use violetshih\MongoQueueMonitor\Models\Contracts\MonitorContract;
use violetshih\MongoQueueMonitor\Services\QueueMonitor;

use Illuminate\Support\Facades\View;

class ShowQueueMonitorController
{

    public function __invoke(Request $request, String $viewname = null)
    {
				$data = $request->validate([
				        'type' => ['nullable', 'string', Rule::in(['all', 'pending', 'running', 'failed', 'succeeded'])],
				        'queue' => ['nullable', 'string'],
				        'custom_data_key' => ['nullable', 'string'],
				        'custom_data_value' => ['nullable', 'string'],
						'view' => ['nullable', 'string']
				    ]);

				    $filters = [
				        'type' => $data['type'] ?? 'all',
				        'queue' => $data['queue'] ?? 'all',
				        'custom_data_key' => $data['custom_data_key'] ?? null,
				        'custom_data_value' => $data['custom_data_value'] ?? null,
				    ];

        $jobs = QueueMonitor::getModel()
						->newQuery()
            ->when(($type = $filters['type']) && 'all' !== $type, static function (Builder $builder) use ($type) {
                switch ($type) {
										case 'pending':
												$builder->whereNull('started_at');
												break;

                    case 'running':
                        $builder->WhereNotNull('started_at')->whereNull('finished_at');
                        break;

                    case 'failed':
                        $builder->where('failed', True)->whereNotNull('finished_at');
                        break;

                    case 'succeeded':
                        $builder->where('failed', False)->whereNotNull('finished_at');
                        break;
                }
            })
            ->when(($queue = $filters['queue']) && 'all' !== $queue, static function (Builder $builder) use ($queue) {
                $builder->where('queue', $queue);
            })
            ->when($filters['custom_data_key'] && $filters['custom_data_value'], static function (Builder $builder) use ($filters) {
                $customDataKey = $filters['custom_data_key'];
                $customDataValue = $filters['custom_data_value'];
                $builder->where('data', 'regexp', sprintf('/"%s":\s*"%s"/', $customDataKey, $customDataValue));
            })
            ->ordered()
            ->paginate(
                config('queue-monitor.ui.per_page')
            )
            ->appends(
                $request->all()
            );

        $queues = QueueMonitor::getModel()
            ->newQuery()
            ->select('queue')
            ->groupBy('queue')
            ->get()
            ->map(function (MonitorContract $monitor) {
                return $monitor->queue;
            })
            ->toArray();

        $metrics = null;

        if (config('queue-monitor.ui.show_metrics')) {
            $metrics = $this->collectMetrics();
        }

				if (!View::exists($viewname)) {
					$viewname = 'queue-monitor::jobs';
				}

        return view($viewname, [
            'jobs' => $jobs,
            'filters' => $filters,
            'queues' => $queues,
            'metrics' => $metrics
        ]);
    }

    public function collectMetrics(): Metrics
    {
        $timeFrame = config('queue-monitor.ui.metrics_time_frame') ?? 2;
				$lowerlimit = new UTCDateTime(Carbon::now()->subDays($timeFrame)->timestamp);
				$comparelowerlimit = new UTCDateTime(Carbon::now()->subDays($timeFrame * 2)->timestamp);

        $metrics = new Metrics();

				$emptyAggregationResult = [
																		'count' => 0
																		, 'total_time_elapsed' => 0
																		, 'average_time_elapsed' => 0
																	];

				$aggregationColumns = ['$group' => [
																						'_id'=> Null
																						, 'count' => [ '$sum' => 1 ]
																						, 'total_time_elapsed' => [ '$sum' => '$time_elapsed']
																						, 'average_time_elapsed' => [ '$avg' => '$time_elapsed']
																						 ]
															];

				$aggregatedInfo = QueueMonitor::getModel()
					->raw( function ( $collection ) use ($aggregationColumns, $lowerlimit) {
										return $collection->aggregate(
												[
													[
														'$match' => [ 'started_at' => [ '$gt' => $lowerlimit ] ]
													]
													, $aggregationColumns
												]
										);
									})
					->first();

				if (null === $aggregatedInfo) {
					$aggregatedInfo = (object) $emptyAggregationResult;

        }

				$pendingJobsCount = QueueMonitor::getModel()->Pending()->count();

				$pendingJobsMinDate = QueueMonitor::getModel()->Pending()->min('queued_at');

				$pendingJobsMinDate = ($pendingJobsMinDate) ? $pendingJobsMinDate->toDateTime()->format('Y-m-d H:i:s') : "";

				$aggregatedComparisonInfo = QueueMonitor::getModel()->raw( function ( $collection ) use ($aggregationColumns, $lowerlimit, $comparelowerlimit) {
									return $collection->aggregate(
											[
												[
													'$match' => [ 'started_at' => [ '$gte' => $comparelowerlimit ] ]
												]
												,[
													'$match' => [ 'started_at' => [ '$lte' => $lowerlimit ] ]
												]
												, $aggregationColumns
											]
									);
								})->first();

				if (null === $aggregatedComparisonInfo) {
					$aggregatedComparisonInfo = (object) $emptyAggregationResult;
        }

        $metrics->push(
          new Metric('Total Jobs Executed', $aggregatedInfo->count, Null, $aggregatedComparisonInfo->count, '%d')
        );
				$metrics->push(
          new Metric('Pending Jobs', $pendingJobsCount, "Oldest pending job date : " . $pendingJobsMinDate, Null, '%d')
        );
				$metrics->push(
          new Metric('Total Execution Time', $aggregatedInfo->total_time_elapsed, Null, $aggregatedComparisonInfo->total_time_elapsed, '%0.4fs')
        );
				$metrics->push(
          new Metric('Average Execution Time', $aggregatedInfo->average_time_elapsed, Null, $aggregatedComparisonInfo->average_time_elapsed, '%0.4fs')
        );
				return $metrics;
		}
}
