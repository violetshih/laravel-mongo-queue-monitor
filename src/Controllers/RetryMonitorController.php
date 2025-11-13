<?php

namespace violetshih\MongoQueueMonitor\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;

use violetshih\MongoQueueMonitor\Models\MongoMonitorQueueModel;

class RetryMonitorController
{
    public function __invoke(Request $request, MongoMonitorQueueModel $monitor)
    {
        try {
            // Get the job data from the monitor record
            $jobData = $monitor->getData();
            
            // Reconstruct the job payload
            $jobPayload = $this->reconstructJobPayload($monitor, $jobData);
            
            if ($jobPayload) {
                // Retry the job by pushing it back to the queue
                Queue::push($jobPayload);
                
            } else {
                throw new \Exception('Failed to reconstruct job payload for retry');
            }
        } catch (\Exception $e) {
            throw $e;
        }

        if (($request->viewname) && (View::exists($request->viewname))) {
            $viewname = $request->viewname;
        } else {
            $viewname = '';
        }

        return redirect()->route('queue-monitor::index', ['viewname' => $viewname]);
    }

    /**
     * Reconstruct the job payload from monitor data.
     *
     * @param MongoMonitorQueueModel $monitor
     * @param array $jobData
     * @return array|null
     */
    private function reconstructJobPayload($monitor, $jobData)
    {
        try {
            // Get the job class name
            $jobClass = $monitor->name;
            
            // Create the job instance with the original data
            $job = new $jobClass($jobData);
            
            // Set the queue name
            if ($monitor->queue) {
                $job->onQueue($monitor->queue);
            }
            
            // Return the job payload that can be pushed to the queue
            return $job;
            
        } catch (\Exception $e) {
            throw $e;
        }
    }
}