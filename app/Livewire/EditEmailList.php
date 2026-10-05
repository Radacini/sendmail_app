<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\EmailList;
use App\Models\EmailRecipient;
use Livewire\Attributes\Layout;

class EditEmailList extends Component
{
    use WithPagination;

    public $listId;
    public $search = '';
    public $editingContactId = null;
    public $editName;
    public $editEmail;

    public function mount($id)
    {
        $this->listId = $id;

        // Verificăm dacă lista există
        $list = EmailList::query()
            ->where('id', '=', (int) $this->listId)
            ->firstOrFail();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $list = EmailList::findOrFail($this->listId);

        $contacts = EmailRecipient::query()
            ->where('email_list_id', '=', (int) $this->listId)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('livewire.edit-email-list', [
            'list' => $list,
            'contacts' => $contacts
        ]);
    }

    public function editContact($id)
    {
        $contact = EmailRecipient::query()
            ->where('id', '=', (int) $id)
            ->where('email_list_id', '=', (int) $this->listId)
            ->firstOrFail();

        $this->editingContactId = $id;
        $this->editName = $contact->name;
        $this->editEmail = $contact->email;
    }

    public function cancelEdit()
    {
        $this->editingContactId = null;
        $this->reset(['editName', 'editEmail']);
    }

    public function saveContact()
    {
        $this->validate([
            'editName' => 'nullable|string|max:255',
            'editEmail' => 'required|email|max:255'
        ]);

        $contact = EmailRecipient::query()
            ->where('id', '=', (int) $this->editingContactId)
            ->where('email_list_id', '=', (int) $this->listId)
            ->firstOrFail();

        $contact->update([
            'name' => $this->editName,
            'email' => $this->editEmail
        ]);

        $this->cancelEdit();
        session()->flash('message', 'Contact actualizat cu succes.');
    }

    public function deleteContact($id)
    {
        $contact = EmailRecipient::query()
            ->where('id', '=', (int) $id)
            ->where('email_list_id', '=', (int) $this->listId)
            ->firstOrFail();

        if ($contact instanceof EmailRecipient) {
            EmailRecipient::destroy($contact->id);
        }

        // Actualizăm contoarele listei
        $list = EmailList::query()->where('id', '=', (int) $this->listId)->first();
        if ($list instanceof EmailList) {
            $list->decrement('total_emails', 1, []);
            $list->decrement('valid_emails', 1, []);
        }

        session()->flash('message', 'Contact șters cu succes.');
    }

    public $newContactName = '';
    public $newContactEmail = '';

    public function addContact()
    {
        $this->validate([
            'newContactName' => 'nullable|string|max:255',
            'newContactEmail' => 'required|email|max:255',
        ]);

        // Verificăm dacă emailul există deja în această listă
        $exists = EmailRecipient::query()
            ->where('email_list_id', '=', (int) $this->listId)
            ->where('email', '=', $this->newContactEmail)
            ->exists();

        if ($exists) {
            $this->addError('newContactEmail', 'Acest email există deja în listă.');
            return;
        }

        // Creăm contactul
        EmailRecipient::create([
            'email_list_id' => $this->listId,
            'name' => $this->newContactName,
            'email' => $this->newContactEmail,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Actualizăm statistica listei
        $list = EmailList::query()->find($this->listId);
        if ($list instanceof EmailList) {
            $list->increment('total_emails', 1, []);
            $list->increment('valid_emails', 1, []);
        }

        // Resetăm formularul
        $this->reset(['newContactName', 'newContactEmail']);
        session()->flash('message', 'Contact adăugat cu succes!');
    }
}
