<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\FileUploadService;
use App\Models\EmailList;

class FileUpload extends Component
{
    use WithFileUploads;

    public $file;
    public $name;
    public $uploading = false;
    public $uploadProgress = 0;

    protected $rules = [
        'file' => 'required|file|mimes:xlsx,csv,json|max:10240',
        'name' => 'required|string|max:255',
    ];

    public function render()
    {
        return view('livewire.file-upload');
    }

    public function uploadFile()
    {
        $this->validate();

        $this->uploading = true;
        $this->uploadProgress = 0;

        try {
            $service = new FileUploadService();
            $result = $service->processFile($this->file, $this->name, auth()->id());

            if ($result['success']) {
                session()->flash('message', 'Fișier încărcat și procesat cu succes!');
                return redirect()->route('email-lists.index');
            } else {
                session()->flash('error', $result['errors']);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Eroare la procesarea fișierului: ' . $e->getMessage());
        }

        $this->uploading = false;
    }

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
}
