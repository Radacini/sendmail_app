<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Campaign;
use App\Models\CampaignResult;
use App\Models\EmailTracking;
use App\Models\EmailRecipient;

class Analytics extends Component
{
    public $selectedCampaignId = null;

    public function render()
    {
        $campaigns = Campaign::query()
            ->with(['emailTemplate', 'emailList'])
            ->latest()
            ->get();

        $analytics = [];

        if ($this->selectedCampaignId) {
            $campaign = Campaign::query()
                ->where('id', '=', $this->selectedCampaignId)
                ->first();

            if ($campaign) {
                $analytics = $this->getCampaignAnalytics($campaign);
            }
        } else {
            $analytics = $this->getOverallAnalytics();
        }

        return view('livewire.analytics', [
            'campaigns' => $campaigns,
            'analytics' => $analytics
        ]);
    }

    protected function getOverallAnalytics()
    {
        $totalCampaigns = Campaign::query()->count();
        $totalEmailsSent = Campaign::query()->sum('emails_sent');
        $totalEmailsOpened = Campaign::query()->sum('emails_opened');
        $totalEmailsFailed = Campaign::query()->sum('emails_failed');

        $openRate = $totalEmailsSent > 0 ? round(($totalEmailsOpened / $totalEmailsSent) * 100, 2) : 0;

        return [
            'total_campaigns' => $totalCampaigns,
            'total_emails_sent' => $totalEmailsSent,
            'total_emails_opened' => $totalEmailsOpened,
            'total_emails_failed' => $totalEmailsFailed,
            'open_rate' => $openRate,
            'recent_campaigns' => Campaign::query()
                ->with(['emailTemplate', 'emailList'])
                ->latest()
                ->limit(5)
                ->get(),
        ];
    }

    protected function getCampaignAnalytics($campaign)
    {
        $totalEmails = $campaign->emailList->valid_emails;
        $emailsSent = (int) $campaign->emails_sent;
        $emailsOpened = (int) $campaign->emails_opened;
        $emailsFailed = (int) $campaign->emails_failed;

        $openRate = $emailsSent > 0 ? round(($emailsOpened / $emailsSent) * 100, 2) : 0;
        $deliveryRate = $totalEmails > 0 ? round((($emailsSent - $emailsFailed) / $totalEmails) * 100, 2) : 0;

        // Get tracking data
        $trackingData = EmailTracking::query()->whereHas('campaignResult', function ($query) use ($campaign) {
            $query->where('campaign_id', '=', $campaign->id);
        })
            ->selectRaw('event_type, COUNT(*) as count')
            ->groupBy('event_type')
            ->get();

        // Get device distribution
        $deviceData = EmailTracking::query()->whereHas('campaignResult', function ($query) use ($campaign) {
            $query->where('campaign_id', '=', $campaign->id);
        })
            ->selectRaw('device, COUNT(*) as count')
            ->groupBy('device')
            ->get();

        // Get browser distribution
        $browserData = EmailTracking::query()->whereHas('campaignResult', function ($query) use ($campaign) {
            $query->where('campaign_id', '=', $campaign->id);
        })
            ->selectRaw('browser, COUNT(*) as count')
            ->groupBy('browser')
            ->get();

        return [
            'campaign' => $campaign,
            'total_emails' => $totalEmails,
            'emails_sent' => $emailsSent,
            'emails_opened' => $emailsOpened,
            'emails_failed' => $emailsFailed,
            'open_rate' => $openRate,
            'delivery_rate' => $deliveryRate,
            'tracking_data' => $trackingData,
            'device_data' => $deviceData,
            'browser_data' => $browserData,
        ];
    }

    public function updatedSelectedCampaignId()
    {
        $this->dispatch('analyticsUpdated');
    }
}
