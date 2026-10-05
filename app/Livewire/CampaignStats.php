<?php

namespace App\Livewire;

use App\Models\Campaign;
use App\Models\CampaignResult;
use App\Models\EmailRecipient;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Layout;

class CampaignStats extends Component
{
    use WithPagination;

    public $campaignId;
    public $search = '';
    public $statusFilter = '';

    public function mount($id)
    {
        $this->campaignId = $id;

        // Verificăm dacă campania există
        Campaign::query()
            ->where('id', '=', (int) $this->campaignId)
            ->firstOrFail();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $campaign = Campaign::with(['emailTemplate', 'emailList'])
            ->where('id', '=', (int) $this->campaignId)
            ->firstOrFail();

        $resultsQuery = CampaignResult::query()
            ->with('emailRecipient')
            ->where('campaign_id', '=', (int) $this->campaignId)
            ->when($this->search, function ($query) {
                $query->whereHas('emailRecipient', function ($q) {
                    $q->where('email', 'like', '%' . $this->search . '%')
                        ->orWhere('name', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', '=', $this->statusFilter);
            })
            ->orderBy('id', 'desc');

        $recipients = $resultsQuery->paginate(15);

        // Stats for cards
        $stats = [
            'total' => CampaignResult::query()->where('campaign_id', '=', $this->campaignId)->count(),
            'sent' => CampaignResult::query()->where('campaign_id', '=', $this->campaignId)->where('status', '=', 'sent')->count(),
            'opened' => CampaignResult::query()->where('campaign_id', '=', $this->campaignId)->where('status', '=', 'opened')->count(),
            'failed' => CampaignResult::query()->where('campaign_id', '=', $this->campaignId)->where('status', '=', 'failed')->count(),
            'pending' => CampaignResult::query()->where('campaign_id', '=', $this->campaignId)->where('status', '=', 'pending')->count(),
        ];

        return view('livewire.campaign-stats', [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'stats' => $stats,
        ]);
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
}
