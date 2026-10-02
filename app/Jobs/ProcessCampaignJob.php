<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Campaign;
use App\Models\EmailRecipient;
use App\Models\CampaignResult;
use App\Jobs\SendEmailJob;

class ProcessCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignId;

    /**
     * Create a new job instance.
     */
    public function __construct($campaignId)
    {
        $this->campaignId = $campaignId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessCampaignJob: Starting campaign processing for ID: {$this->campaignId}");

        $campaign = Campaign::find($this->campaignId, ['*']);

        if (!$campaign) {
            Log::error("ProcessCampaignJob: Campaign not found: {$this->campaignId}");
            return;
        }

        Log::info("ProcessCampaignJob: Found campaign {$campaign->name} with status: {$campaign->status}");

        // Update campaign status to running
        $campaign->update([
            'status' => 'running',
            'started_at' => now()
        ]);

        Log::info("ProcessCampaignJob: Updated campaign {$campaign->name} status to running");

        // Get pending results for this campaign
        Log::info("ProcessCampaignJob: Getting pending results for campaign {$campaign->name}");
        $results = $campaign->campaignResults()->where('status', '=', 'pending')->get();

        Log::info("ProcessCampaignJob: Found {$results->count()} pending results for campaign {$campaign->name}");

        if ($results->isEmpty()) {
            Log::info("ProcessCampaignJob: No pending results found for campaign {$campaign->name}");
            $campaign->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);
            return;
        }

        // Spread the sends evenly over time so we never exceed the per-minute SMTP limit.
        $limit = max(1, (int) config('app.email_rate_limit'));
        $interval = 60 / $limit;
        $startAt = now();

        Log::info("ProcessCampaignJob: Queueing {$results->count()} emails for campaign {$campaign->name} at {$limit}/min");

        foreach ($results->values() as $i => $result) {
            SendEmailJob::dispatch($result->id)->delay($startAt->copy()->addSeconds((int) round($i * $interval)));
        }

        // Campaign is marked completed by SendEmailJob once no pending results remain.
        Log::info("ProcessCampaignJob: Queued {$results->count()} email jobs for campaign {$campaign->name}");
    }
}
