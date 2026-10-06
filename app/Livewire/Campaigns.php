<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\EmailList;
use App\Models\EmailRecipient;
use App\Models\CampaignResult;
use App\Jobs\SendEmailJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Campaigns extends Component
{
    // Progress tracking properties
    public $runningCampaignId = null;
    public $progressPercentage = 0;
    public $progressMessage = '';
    public $emailsProcessed = 0;
    public $totalEmails = 0;

    #[Layout('layouts.app')]
    public function render()
    {
        $campaigns = Campaign::query()
            ->with(['emailTemplate', 'emailList'])
            ->latest()
            ->paginate(10);

        // Check if any campaign is running or scheduled to trigger UI polling
        $anyRunning = Campaign::query()
            ->whereIn('status', ['running', 'scheduled'])
            ->exists();

        return view('livewire.campaigns', [
            'campaigns' => $campaigns,
            'anyRunning' => $anyRunning,
        ]);
    }


    public function startCampaign($campaignId)
    {
        $campaign = Campaign::query()
            ->where('id', '=', (int) $campaignId)
            ->first();

        if (!$campaign || ($campaign->status !== 'pending' && $campaign->status !== 'failed')) {
            session()->flash('error', 'Campania nu poate fi pornită!');
            return;
        }

        $now = now();
        $isScheduled = $campaign->scheduled_at && $campaign->scheduled_at->isAfter($now);

        // Update campaign status
        $campaign->update([
            'status' => $isScheduled ? 'scheduled' : 'running',
            'started_at' => $isScheduled ? null : $now,
            'error_message' => null
        ]);

        // Create campaign results for each recipient in the list if not already created
        $recipients = $campaign->emailList->emailRecipients;

        // Clear previous results if any (e.g. if restarting a failed campaign)
        $campaign->campaignResults()->delete();

        // Start a fresh (empty) failure log for this run
        \App\Services\CampaignFailureLog::reset($campaign->id);

        $results = [];
        foreach ($recipients as $recipient) {
            $results[] = [
                'campaign_id' => $campaign->id,
                'email_recipient_id' => $recipient->id,
                'tracking_token' => \Illuminate\Support\Str::random(64),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Insert in chunks of 500 to avoid memory issues
            if (count($results) >= 500) {
                \App\Models\CampaignResult::insert($results);
                $results = [];
            }
        }

        if (!empty($results)) {
            \App\Models\CampaignResult::insert($results);
        }

        // Dispatch background job
        if ($isScheduled) {
            \App\Jobs\ProcessCampaignJob::dispatch($campaign->id)->delay($campaign->scheduled_at);
            session()->flash('message', 'Campania a fost programată pentru ' . $campaign->scheduled_at->format('d.m.Y H:i') . '!');
        } else {
            \App\Jobs\ProcessCampaignJob::dispatch($campaign->id);
            session()->flash('message', 'Campania a fost adăugată în coada de procesare!');
        }
    }

    protected function resetProgress()
    {
        $this->runningCampaignId = null;
        $this->progressPercentage = 0;
        $this->progressMessage = '';
        $this->emailsProcessed = 0;
        $this->totalEmails = 0;
    }

    public function pollRefresh()
    {
        // This method is called by Livewire polling
        // We can add any additional logic here if needed
        return;
    }

    public function pauseCampaign($campaignId)
    {
        $campaign = Campaign::query()
            ->where('id', '=', (int) $campaignId)
            ->first();

        if ($campaign && $campaign->status === 'running') {
            $campaign->update(['status' => 'paused']);
            session()->flash('message', 'Campanie oprită temporar!');
        }
    }

    public function deleteCampaign($campaignId)
    {
        $campaign = Campaign::query()
            ->where('id', '=', (int) $campaignId)
            ->first();

        if ($campaign) {
            Campaign::destroy($campaign->id);
            session()->flash('message', 'Campanie ștearsă cu succes!');
        }

        $this->dispatch('campaignDeleted');
    }

    public function getStatusColor($status)
    {
        switch ($status) {
            case 'pending':
                return 'bg-gray-100 text-gray-800';
            case 'running':
                return 'bg-green-100 text-green-800';
            case 'scheduled':
                return 'bg-purple-100 text-purple-800';
            case 'completed':
                return 'bg-blue-100 text-blue-800';
            case 'failed':
                return 'bg-red-100 text-red-800';
            case 'paused':
                return 'bg-yellow-100 text-yellow-800';
            default:
                return 'bg-gray-100 text-gray-800';
        }
    }
}
