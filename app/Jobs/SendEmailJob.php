<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\EmailRecipient;
use App\Models\CampaignResult;
use App\Models\EmailTracking;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaignResultId;

    /**
     * Create a new job instance.
     */
    public function __construct($campaignResultId)
    {
        $this->campaignResultId = $campaignResultId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("SendEmailJob: Starting job for result ID: {$this->campaignResultId}");

        $result = CampaignResult::with('emailRecipient')->find($this->campaignResultId, ['*']);

        if (!$result) {
            Log::error("SendEmailJob: Result not found with ID: {$this->campaignResultId}");
            return;
        }

        $recipient = $result->emailRecipient;
        Log::info("SendEmailJob: Found recipient {$recipient->email} for campaign result ID: {$result->id}");

        if ($result->status === 'sent') {
            Log::warning("SendEmailJob: Email already sent for result ID: {$result->id}");
            return;
        }

        try {
            // Get the campaign and template
            Log::info("SendEmailJob: Getting campaign and template for recipient {$recipient->email}");
            $campaign = $result->campaign;
            $template = $campaign->emailTemplate;

            Log::info("SendEmailJob: Campaign: {$campaign->name}, Template: {$template->name}");

            // Personalize the email content
            Log::info("SendEmailJob: Personalizing content for {$recipient->email}");
            $content = $this->personalizeContent($template->content, $recipient, $campaign);
            $subject = $this->personalizeContent($template->subject, $recipient, $campaign);

            // Add tracking pixel to HTML content
            if ($template->is_html) {
                Log::info("SendEmailJob: Adding tracking pixel for {$recipient->email}");
                $trackingPixel = $this->generateTrackingPixel($result->tracking_token);
                $content .= $trackingPixel;
                $content = $this->wrapInHtmlDocument($content);
            }

            // Send the email
            Log::info("SendEmailJob: Attempting to send email to {$recipient->email}");
            Log::info("SendEmailJob: Content length: " . strlen($content));
            Log::info("SendEmailJob: Subject: {$subject}");

            Mail::html($content, function ($message) use ($recipient, $subject, $template) {
                $message->to($recipient->email, $recipient->name)
                    ->subject($subject)
                    ->from(config('mail.from.address'), config('mail.from.name'));

                if ($template->attachment_path) {
                    $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($template->attachment_path);
                    Log::info("SendEmailJob: Attaching file: " . $fullPath);
                    if (file_exists($fullPath)) {
                        $message->attach($fullPath, [
                            'as' => $template->attachment_name,
                        ]);
                        Log::info("SendEmailJob: File attached successfully to message object.");
                    } else {
                        Log::error("SendEmailJob: Attachment file NOT FOUND at: " . $fullPath);
                    }
                }
            });

            Log::info("SendEmailJob: Email sent successfully to {$recipient->email}");

            // Update result status
            $result->update([
                'status' => 'sent',
                'sent_at' => now()
            ]);

            // Log success
            Log::info("Email sent successfully to {$recipient->email} for campaign {$campaign->name}");

            // Update campaign stats
            $campaign->increment('emails_sent', 1, []);

        } catch (\Exception $e) {
            Log::error("SendEmailJob: Exception occurred while sending email to {$recipient->email}: " . $e->getMessage());
            Log::error("SendEmailJob: Exception trace: " . $e->getTraceAsString());

            // Update result status to failed
            $result->update([
                'status' => 'failed',
                'sent_at' => now()
            ]);

            // Update campaign stats
            $campaign->increment('emails_failed', 1, []);

            // Log error
            Log::error("Failed to send email to {$recipient->email}: " . $e->getMessage());
        }
    }

    /**
     * Personalize email content with recipient data
     */
    protected function personalizeContent($content, $recipient, $campaign)
    {
        $replacements = [
            '{name}' => $recipient->name,
            '{email}' => $recipient->email,
            '{date}' => now()->format('Y-m-d'),
            '{campaign_name}' => $campaign->name,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    protected function wrapInHtmlDocument($content)
    {
        $tile = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAAUCAIAAAAC64paAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAAHUlEQVQ4jWP8//8/A7mAiWydo5pHNY9qHtVMFc0AnKADJRpd72EAAAAASUVORK5CYII=';

        return <<<HTML
        <!DOCTYPE html>
        <html lang="ro" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="x-apple-disable-message-reformatting">
        <meta name="color-scheme" content="light only">
        <meta name="supported-color-schemes" content="light only">
        <!--[if mso]>
        <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
        <![endif]-->
        <style>
        :root { color-scheme: light only; supported-color-schemes: light only; }
        html, body { margin: 0 !important; padding: 0 !important; background-color: #ffffff !important; }
        .sm-wrap, .sm-wrap .sm-cell { background-color: #ffffff !important; }
        [data-ogsb] .sm-wrap, [data-ogsb] .sm-wrap .sm-cell,
        [data-ogsb] .sm-cell { background-color: #ffffff !important; }
        u + .sm-body .sm-wrap, u + .sm-body .sm-cell { background-color: #ffffff !important; }
        @media (prefers-color-scheme: dark) {
        html, body, .sm-body, .sm-wrap, .sm-cell { background-color: #ffffff !important; }
        }
        </style>
        </head>
        <body class="sm-body" bgcolor="#ffffff" style="margin:0; padding:0; background-color:#ffffff;">
        <table class="sm-wrap" role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" background="{$tile}" style="background-color:#ffffff; background-image:url('{$tile}'); background-repeat:repeat; width:100%;">
        <tr>
        <td class="sm-cell" align="center" bgcolor="#ffffff" background="{$tile}" style="background-color:#ffffff; background-image:url('{$tile}'); background-repeat:repeat; padding:24px 12px;">
        {$content}
        </td>
        </tr>
        </table>
        </body>
        </html>
        HTML;
    }

    /**
     * Generate tracking pixel HTML
     */
    protected function generateTrackingPixel($trackingToken)
    {
        $trackingUrl = url("/track/{$trackingToken}");
        return "<img src=\"{$trackingUrl}\" width=\"1\" height=\"1\" style=\"display:none;\" alt=\"\">";
    }
}
