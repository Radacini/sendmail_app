<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\EmailTemplate;

class EmailTemplates extends Component
{
    public $name;
    public $subject;
    public $content;
    public $isHtml = true;
    public $editingTemplateId = null;
    public $editorType = 'tinymce'; // 'simple' sau 'tinymce'

    protected $rules = [
        'name' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'content' => 'required|string',
        'isHtml' => 'boolean',
    ];

    public function render()
    {
        $templates = EmailTemplate::query()
            ->latest()
            ->paginate(10);

        return view('livewire.email-templates', [
            'templates' => $templates
        ]);
    }

    public function saveTemplate()
    {
        $this->validate();

        if ($this->editingTemplateId) {
            $template = EmailTemplate::where('id', $this->editingTemplateId)
                ->first();

            if ($template) {
                $template->update([
                    'name' => $this->name,
                    'subject' => $this->subject,
                    'content' => $this->content,
                    'is_html' => $this->isHtml,
                ]);
                session()->flash('message', 'Șablon actualizat cu succes!');
            }
        } else {
            EmailTemplate::create([
                'name' => $this->name,
                'subject' => $this->subject,
                'content' => $this->content,
                'is_html' => $this->isHtml,
                'user_id' => auth()->id(),
            ]);
            session()->flash('message', 'Șablon creat cu succes!');
        }

        $this->resetForm();
    }

    public function editTemplate($templateId)
    {
        $template = EmailTemplate::where('id', $templateId)
            ->first();

        if ($template) {
            $this->editingTemplateId = $template->id;
            $this->name = $template->name;
            $this->subject = $template->subject;
            $this->content = $template->content;
            $this->isHtml = $template->is_html;
        }
    }

    public function deleteTemplate($templateId)
    {
        $template = EmailTemplate::where('id', $templateId)
            ->first();

        if ($template) {
            $template->delete();
            session()->flash('message', 'Șablon șters cu succes!');
        }
    }

    public function resetForm()
    {
        $this->reset(['name', 'subject', 'content', 'isHtml', 'editingTemplateId']);
    }

    public function previewTemplate()
    {
        return $this->content;
    }

    /**
     * Schimbă tipul de editor la simplu
     */
    public function useSimpleEditor()
    {
        $this->editorType = 'simple';
        $this->isHtml = false; // Setăm text simplu pentru editorul simplu
        $this->dispatch('editorTypeChanged');
    }

    /**
     * Schimbă tipul de editor la TinyMCE
     */
    public function useTinyMCE()
    {
        $this->editorType = 'tinymce';
        $this->isHtml = true; // Forțăm HTML pentru TinyMCE
        $this->dispatch('editorTypeChanged');
    }

    /**
     * Ascultă evenimentul de actualizare a conținutului din TinyMCE
     */
    protected $listeners = ['contentUpdated'];

    public function contentUpdated($content)
    {
        $this->content = $content;
    }
}
