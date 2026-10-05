<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Storage;

class EmailTemplateController extends Controller
{
    public function index()
    {
        $templates = EmailTemplate::query()
            ->latest()
            ->paginate(10);

        return view('email-templates.index', [
            'templates' => $templates
        ]);
    }

    public function create()
    {
        return view('email-templates.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'is_html' => 'boolean',
            'attachment' => 'nullable|file|max:2048'
        ]);

        $attachmentPath = null;
        $attachmentName = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentName = $file->getClientOriginalName();
            $attachmentPath = $file->store('attachments', 'local');
        }

        EmailTemplate::create([
            'name' => $request->input('name'),
            'subject' => $request->input('subject'),
            'content' => $request->input('content'),
            'is_html' => $request->is_html ?? true,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'user_id' => auth()->id(),
        ]);

        return redirect()->route('email-templates.index')
            ->with('message', 'Șablon creat cu succes!');
    }

    public function edit($id)
    {
        $template = EmailTemplate::query()
            ->where('id', '=', (int) $id)
            ->firstOrFail();

        return view('email-templates.edit', [
            'template' => $template
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'content' => 'required|string',
            'is_html' => 'boolean',
            'attachment' => 'nullable|file|max:2048'
        ]);

        $template = EmailTemplate::query()
            ->where('id', '=', (int) $id)
            ->firstOrFail();

        $data = [
            'name' => $request->input('name'),
            'subject' => $request->input('subject'),
            'content' => $request->input('content'),
            'is_html' => $request->input('is_html') ?? true,
        ];

        if ($request->hasFile('attachment')) {
            // Delete old attachment if exists
            if ($template->attachment_path) {
                Storage::disk('local')->delete($template->attachment_path);
            }

            $file = $request->file('attachment');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_path'] = $file->store('attachments', 'local');
        } elseif ($request->boolean('remove_attachment')) {
            if ($template->attachment_path) {
                Storage::disk('local')->delete($template->attachment_path);
            }
            $data['attachment_name'] = null;
            $data['attachment_path'] = null;
        }

        $template->update($data);

        return redirect()->route('email-templates.index')
            ->with('message', 'Șablon actualizat cu succes!');
    }

    public function destroy($id)
    {
        $template = EmailTemplate::query()
            ->where('id', '=', (int) $id)
            ->first();

        if ($template) {
            if ($template->attachment_path) {
                Storage::disk('local')->delete($template->attachment_path);
            }
            EmailTemplate::destroy($template->id);
        }

        return redirect()->route('email-templates.index')
            ->with('message', 'Șablon șters cu succes!');
    }
}
