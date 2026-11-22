<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Template;
use App\Jobs\SendCertificateEmailJob;
use App\Services\AuditLogger;
use App\Services\TemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailController extends Controller
{
    public function __construct(private readonly TemplateService $templateService)
    {
    }

    public function preview(Request $request)
    {

        $name = $request->nome ?? 'Participante';

        $template = Template::where(
            'type',
            'email'
        )->latest('id')->first();

        if (!$template) {

            return 'Template não encontrado';
        }

        $html = $this->templateService->render(
            $template->html,
            [
                'name' => $name
            ]
        );

        return $html;
    }

    /*
    |--------------------------------------------------------------------------
    | ENVIAR E-MAILS
    |--------------------------------------------------------------------------
    */

    public function sendForm(Request $request): View
    {
        $projects = $request->user()->projects()
            ->withCount(['participants as participants_with_certificates_count' => function ($query) {
                $query->whereHas('latestCertificate');
            }])
            ->latest()
            ->get();

        return view('emails.send', compact('projects'));
    }

    public function send(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
        ]);
        $project = $request->user()->projects()->findOrFail($data['project_id']);

        if (!Template::query()->where('type', 'email')->exists()) {
            return back()->withErrors(['project_id' => 'Template de e-mail não encontrado.']);
        }

        $participants = $project->participants()
            ->with('latestCertificate')
            ->whereHas('latestCertificate')
            ->get();

        if ($participants->isEmpty()) {
            return back()->withErrors(['project_id' => 'Este projeto não tem participantes com certificados gerados.']);
        }

        $delay = 0;
        $queuedCount = 0;
        foreach ($participants as $participant) {
            SendCertificateEmailJob::dispatch(
                $participant->id,
                $participant->latestCertificate->id,
                $request->user()->id
            )->delay(now()->addSeconds($delay));

            $delay += 40;
            $queuedCount++;
        }

        $auditLogger->record($request->user(), 'email.queue_created', $project, [
            'project_name' => $project->name,
            'queued_count' => $queuedCount,
        ], $request);

        return redirect()->route('email.send.form')->with(
            'status',
            "Fila criada: {$queuedCount} e-mails do projeto {$project->name}."
        );
    }

}
