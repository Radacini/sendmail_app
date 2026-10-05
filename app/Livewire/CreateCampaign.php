<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Campaign;
use App\Models\EmailTemplate;
use App\Models\EmailList;

use Livewire\Attributes\Layout;

class CreateCampaign extends Component
{
    public $name;
    public $emailTemplateId;
    public $emailListId;
    public $scheduled_at;

    protected $rules = [
        'name' => 'required|string|max:255',
        'emailTemplateId' => 'required|exists:email_templates,id',
        'emailListId' => 'required|exists:email_lists,id',
        'scheduled_at' => 'nullable|date|after_or_equal:now',
    ];

    public function createCampaign()
    {
        $this->validate();

        // Check if email list has valid emails
        $emailList = EmailList::query()->find($this->emailListId);
        if (!$emailList || $emailList->valid_emails === 0) {
            session()->flash('error', 'Lista de emailuri nu conține adrese valide!');
            return;
        }

        Campaign::create([
            'name' => $this->name,
            'email_template_id' => $this->emailTemplateId,
            'email_list_id' => $this->emailListId,
            'status' => 'pending',
            'scheduled_at' => $this->scheduled_at ?: null,
            'user_id' => auth()->id(),
        ]);

        session()->flash('message', 'Campanie creată cu succes!');

        return redirect()->route('campaigns');
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $templates = EmailTemplate::query()->get();
        $emailLists = EmailList::query()
            ->where('status', '=', 'completed')
            ->get();

        return view('livewire.create-campaign', [
            'templates' => $templates,
            'emailLists' => $emailLists
        ]);
    }
}
