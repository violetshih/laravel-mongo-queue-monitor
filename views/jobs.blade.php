<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mongo Queue Monitor</title>
    <link href="https://unpkg.com/tailwindcss@^1.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="font-sans p-6 pb-64 bg-gray-100">
    <h1 class="mb-6 text-5xl text-blue-900 font-bold">
        Mongo Queue Monitor
    </h1>
    
    <!-- Notification Messages -->
    @if(session('notification'))
        <div class="mb-4 p-4 rounded-lg {{ session('notification')['type'] === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700' }}">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    @if(session('notification')['type'] === 'success')
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    @else
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    @endif
                </svg>
                <span>{{ session('notification')['message'] }}</span>
            </div>
        </div>
        @php(session()->forget('notification'))@endphp
    @endif

		@if(config('queue-monitor.ui.show_metrics'))
	    @isset($metrics)
	      <div class="flex flex-wrap -mx-4 mb-2">
	        @foreach($metrics->all() as $metric)
					<x-metric-card :metric="$metric"></x-metric-card>
	        @endforeach
	      </div>
	    @endisset
		@endif
		<x-filters-form :filters="$filters" :queues="$queues"></x-filters-form>
		<x-jobs-list :jobs="$jobs"></x-jobs-list>
    @if(config('queue-monitor.ui.allow_purge'))
			<x-job-purge-form></x-job-purge-form>
		@endif
</body>
</html>
