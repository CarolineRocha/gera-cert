<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\EmailLog;
use App\Models\User;
use App\Services\AuditLogger;

class EmailService
{
    public function __construct(
        private readonly SmtpSettingsManager $smtpSettingsManager,
        private readonly AuditLogger $auditLogger
    )
    {
    }

    public function send(
        $participant,
        $certificate,
        $subject,
        $html,
        User $sender
    ) {

        try {

            $emails = array_filter(
                array_map(
                    'trim',
                    explode(';', $participant->email)
                )
            );

            $this->smtpSettingsManager->mailer($sender)->html(
                $html,
                function ($message) use (
                    $participant,
                    $certificate,
                    $subject,
                    $emails
                ) {
                    $message->to($emails)
                        ->subject($subject)
                        ->attach(
                            public_path(
                                $certificate->pdf_path
                            )
                        );
                }
            );

            /*
            |--------------------------------------------------------------------------
            | LOG SUCESSO
            |--------------------------------------------------------------------------
            */

            EmailLog::create([
                'participant_id' => $participant->id,
                'certificate_id' => $certificate->id,
                'sent_by_user_id' => $sender->id,
                'recipient' => implode(';', $emails),
                'subject' => $subject,
                'rendered_html' => $html,
                'status' => 'sent',
                'sent_at' => now()
            ]);

            $this->auditLogger->record($sender, 'email.sent', $certificate, [
                'project_id' => $participant->project_id,
                'participant_id' => $participant->id,
                'recipients' => $emails,
            ]);

            return true;

        } catch (\Exception $e) {

            /*
            |--------------------------------------------------------------------------
            | LOG ERRO
            |--------------------------------------------------------------------------
            */

            EmailLog::create([
                'participant_id' => $participant->id,
                'certificate_id' => $certificate->id,
                'sent_by_user_id' => $sender->id,
                'recipient' => implode(';', $emails ?? []),
                'subject' => $subject,
                'rendered_html' => $html,
                'status' => 'error',
                'error_message' => 'Falha no envio SMTP ('.class_basename($e).').'
            ]);
            $this->auditLogger->record($sender, 'email.failed', $certificate, [
                'project_id' => $participant->project_id,
                'participant_id' => $participant->id,
                'recipients' => $emails ?? [],
                'exception' => class_basename($e),
            ]);
            return false;
        }
    }
}
