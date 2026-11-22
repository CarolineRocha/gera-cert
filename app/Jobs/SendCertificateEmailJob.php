<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Queue\Queueable;

use App\Models\Participant;
use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;

use App\Services\TemplateService;
use App\Services\EmailService;

class SendCertificateEmailJob implements ShouldQueue
{
    use Queueable;

    /*
    |--------------------------------------------------------------------------
    | PROPRIEDADES
    |--------------------------------------------------------------------------
    */

    protected $participantId;

    protected $certificateId;
    protected $senderId;

    /*
    |--------------------------------------------------------------------------
    | CONSTRUTOR
    |--------------------------------------------------------------------------
    */

    public function __construct(
        $participantId,
        $certificateId,
        $senderId
    ) {

        $this->participantId = $participantId;

        $this->certificateId = $certificateId;
        $this->senderId = $senderId;
    }

    /*
    |--------------------------------------------------------------------------
    | PROCESSAR JOB
    |--------------------------------------------------------------------------
    */

    public function handle(
        TemplateService $templateService,
        EmailService $emailService
    ): void {

        /*
        |--------------------------------------------------------------------------
        | PARTICIPANTE
        |--------------------------------------------------------------------------
        */

        $participant = Participant::query()->with('project')->find($this->participantId);

        if (!$participant) {

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | CERTIFICADO
        |--------------------------------------------------------------------------
        */

        $certificate = Certificate::query()
            ->whereKey($this->certificateId)
            ->where('participant_id', $participant->id)
            ->first();

        if (!$certificate) {

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | TEMPLATE EMAIL
        |--------------------------------------------------------------------------
        */

        $emailTemplate = Template::where(
            'type',
            'email'
        )->latest('id')->first();

        if (!$emailTemplate) {

            return;
        }

        $sender = User::query()->whereKey($this->senderId)->where('is_admin', true)->first();

        if (!$sender) {
            return;
        }

        if ($participant->project?->user_id !== $sender->id) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | RENDER HTML EMAIL
        |--------------------------------------------------------------------------
        */

        $emailHtml = $templateService->render(
            $emailTemplate->html,
            [
                'name' => $participant->name,

                'company' => 'Minha Empresa',

                'logo' => 'https://site.com/logo.png'
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | ENVIAR EMAIL
        |--------------------------------------------------------------------------
        */

        $emailService->send(

            $participant,

            $certificate,

            'Seu certificado está pronto',

            $emailHtml,

            $sender
        );
    }
}
