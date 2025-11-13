<tr class="font-sm leading-relaxed">
	<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
			@if(!$job->isStarted())
				<div class="inline-flex flex-1 px-2 text-xs font-medium leading-5 rounded-full bg-yellow-200 text-yellow-800">
						Pending
				</div>
			@elseif(!$job->isFinished())
					<div class="inline-flex flex-1 px-2 text-xs font-medium leading-5 rounded-full bg-blue-200 text-blue-800">
							Running
					</div>
			@elseif($job->hasSucceeded())
					<div class="inline-flex flex-1 px-2 text-xs font-medium leading-5 rounded-full bg-green-200 text-green-800">
							Success
					</div>
			@else
					<div class="inline-flex flex-1 px-2 text-xs font-medium leading-5 rounded-full bg-red-200 text-red-800">
							Failed
					</div>
			@endif
	</td>
		<td class="p-4 text-gray-800 text-sm leading-5 font-medium border-b border-gray-200">
				{{ $job->getBaseName() }}
				<span class="ml-1 text-xs text-gray-600">
						#{{ $job->job_id }}
				</span>
		</td>
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
				<div class="text-xs">
						<span class="text-gray-600 font-medium">Queue:</span>
						<span class="font-semibold">{{ $job->queue }}</span>
				</div>
				<div class="text-xs">
						<span class="text-gray-600 font-medium">Attempt:</span>
						<span class="font-semibold">{{ $job->attempt }}</span>
				</div>
		</td>
		@if(config('queue-monitor.ui.show_custom_data'))
				<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
								<textarea rows="4"
													class="w-64 text-xs p-1 border rounded"
													readonly>{{ json_encode($job->getData(), JSON_PRETTY_PRINT) }}
								</textarea>
				</td>
		@endif
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
				@if($job->progress !== null)
						<div class="w-32">
								<div class="flex items-stretch h-3 rounded-full bg-gray-300 overflow-hidden">
										<div class="h-full bg-green-500" style="width: {{ $job->progress }}%"></div>
								</div>
								<div class="flex justify-center mt-1 text-xs text-gray-800 font-semibold">
										{{ $job->progress }}%
								</div>
						</div>
				@else
						-
				@endif
		</td>
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
				{{ $job->queued_at }}
		</td>
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
				{{ $job->getElapsedInterval()->format('%H:%I:%S') }}
		</td>
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
			@if ($job->isStarted())
				{{ $job->started_at->diffForHumans() }}
			@endif
		</td>
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
			@if($job->hasFailed() && $job->exception_message !== null)
					<textarea rows="4" class="w-64 text-xs p-1 border rounded" readonly>{{ $job->exception_message }}</textarea>
			@else
					-
			@endif
		</td>
		<td class="p-4 text-gray-800 text-sm leading-5 border-b border-gray-200">
			@if(config('queue-monitor.ui.allow_retry') && $job->hasFailed())
				<x-job-retry-form :job="$job" :viewname="$viewname"></x-job-retry-form>
			@endif
			@if ($allowDeletion)
				<x-job-delete-form :job="$job" :viewname="$viewname"></x-job-delete-form>
			@endif
		</td>
</tr>
