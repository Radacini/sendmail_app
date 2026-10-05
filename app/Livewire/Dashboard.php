<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\EmailList;
use App\Models\EmailTemplate;
use App\Models\Campaign;
use App\Models\CampaignResult;
use App\Models\EmailRecipient;
use App\Models\RateLimitLog;

class Dashboard extends Component
{
    public $stats = [];

    public function mount()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $this->stats = [
            'email_lists' => EmailList::query()->where('status', '=', 'completed')->count(),
            'templates' => EmailTemplate::query()->count(),
            'campaigns' => Campaign::query()->count(),
            'emails_sent' => CampaignResult::query()->where('status', '=', 'sent')->count(),
            'system_status' => $this->getSystemStatus(),
        ];
    }

    protected function getSystemStatus()
    {
        $limit = config('app.email_rate_limit');
        $currentUsage = CampaignResult::query()
            ->where('status', '=', 'sent')
            ->where('sent_at', '>=', now()->subMinute())
            ->count();

        return [
            'smtp_connected' => $this->checkSmtpConnection(),
            'database_connected' => $this->checkDatabaseConnection(),
            'rate_limit' => "{$currentUsage} / {$limit} per min",
        ];
    }

    protected function checkSmtpConnection()
    {
        try {
            // Try to connect to SMTP server
            $connection = @fsockopen('smtp.office365.com', 587, $errno, $errstr, 5);
            if ($connection) {
                fclose($connection);
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function checkDatabaseConnection()
    {
        try {
            // Test database connection by running a simple query
            \DB::select('SELECT 1');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function render()
    {
        return view('livewire.dashboard');
    }
}
