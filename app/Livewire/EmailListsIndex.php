<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\EmailList;
use Livewire\Attributes\Layout;

class EmailListsIndex extends Component
{
    use WithPagination;

    public function deleteList($listId)
    {
        /** @var EmailList|null $list */
        $list = EmailList::query()
            ->where('id', '=', (int) $listId)
            ->first();

        if ($list instanceof EmailList) {
            // Delete associated file
            if ($list->file_path) {
                \Storage::delete('public/' . $list->file_path);
            }

            // Delete associated recipients
            $list->emailRecipients()->delete();

            // Delete the list
            EmailList::destroy($list->id);

            session()->flash('message', 'Lista de emailuri ștearsă cu succes!');
        }
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $emailLists = EmailList::query()
            ->latest()
            ->paginate(10);

        return view('livewire.email-lists-index', [
            'emailLists' => $emailLists
        ]);
    }
}
