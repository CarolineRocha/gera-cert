<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Certificate;
use App\Models\Template;

use App\Services\TemplateService;
use App\Services\CertificateService;
use App\Services\AuditLogger;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CertificateController extends Controller
{
    protected $templateService;

    protected $certificateService;

    public function __construct(
        TemplateService $templateService,
        CertificateService $certificateService
    ) {

        $this->templateService = $templateService;

        $this->certificateService = $certificateService;

    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW HTML
    |--------------------------------------------------------------------------
    */

    public function preview(Request $request)
    {

        $name = $request->nome ?? 'Participante';

        $template = Template::where(
            'type',
            'certificate'
        )->first();

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
    | PREVIEW PDF
    |--------------------------------------------------------------------------
    */

    public function pdf(Request $request)
    {

        $name = $request->nome ?? 'Participante';

        $template = Template::where(
            'type',
            'certificate'
        )->first();

        if (!$template) {

            return 'Template não encontrado';
        }

        $html = $this->templateService->render(
            $template->html,
            [
                'name' => $name
            ]
        );

        $pdf = $this->certificateService->PdfGenerate($html);
  
        return $pdf;
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $search = $request->query('q');
        $search = is_string($search) ? trim($search) : '';
        $projectId = $request->query('project_id');
        $projectId = is_scalar($projectId) && ctype_digit((string) $projectId) ? (int) $projectId : null;
        $projects = $user->projects()->orderBy('name')->get(['id', 'name']);

        $query = Certificate::query()
            ->with(['participant.project'])
            ->whereHas('participant.project', fn ($projectQuery) => $projectQuery->where('user_id', $user->id));

        if ($search !== '') {
            $query->whereHas('participant', function ($participantQuery) use ($search) {
                $participantQuery->where(function ($matches) use ($search) {
                    $matches->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            });
        }

        if ($projectId !== null) {
            $query->whereHas('participant', fn ($participantQuery) => $participantQuery->where('project_id', $projectId));
        }

        $certificates = $query->latest()->paginate(25)->withQueryString();

        return view('certificates.index', compact('certificates', 'projects', 'search', 'projectId'));
    }

    public function viewPdf(Request $request, Certificate $certificate, AuditLogger $auditLogger): BinaryFileResponse
    {
        $certificate = $this->ownedCertificate($request, $certificate);
        $auditLogger->record($request->user(), 'certificate.viewed', $certificate, [
            'project_id' => $certificate->participant->project_id,
            'participant_id' => $certificate->participant_id,
        ], $request);

        return response()->file($this->pdfFilePath($certificate), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function downloadPdf(Request $request, Certificate $certificate, AuditLogger $auditLogger): BinaryFileResponse
    {
        $certificate = $this->ownedCertificate($request, $certificate);
        $auditLogger->record($request->user(), 'certificate.downloaded', $certificate, [
            'project_id' => $certificate->participant->project_id,
            'participant_id' => $certificate->participant_id,
        ], $request);

        return response()->download($this->pdfFilePath($certificate), 'certificado-'.$certificate->uuid.'.pdf');
    }

    private function ownedCertificate(Request $request, Certificate $certificate): Certificate
    {
        return Certificate::query()
            ->with('participant.project')
            ->whereKey($certificate->getKey())
            ->whereHas('participant.project', fn ($query) => $query->where('user_id', $request->user()->id))
            ->firstOrFail();
    }

    private function pdfFilePath(Certificate $certificate): string
    {
        $directory = realpath(public_path('pdf'));
        $path = realpath(public_path($certificate->pdf_path));

        abort_unless(
            $directory && $path && is_file($path) && str_starts_with($path, $directory.DIRECTORY_SEPARATOR),
            404
        );

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | GERAR CERTIFICADOS
    |--------------------------------------------------------------------------
    */
    public function generateForm(Request $request): View
    {
        $projects = $request->user()->projects()->withCount('participants')->latest()->get();

        return view('certificates.generate', compact('projects'));
    }

    public function generate(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
        ]);
        $project = $request->user()->projects()->findOrFail($data['project_id']);
        $participants = $project->participants()->get();
        $auditLogger->record($request->user(), 'certificate.generation_started', $project, [
            'project_name' => $project->name,
            'participant_count' => $participants->count(),
        ], $request);

        $certificateTemplate = Template::where(
            'type',
            'certificate'
        )->first();

        if (!$certificateTemplate) {

            return back()->withErrors(['project_id' => 'Template de certificado não encontrado.']);
        }

        $generatedCount = 0;
        $failedCount = 0;
        foreach ($participants as $participant) {

            try {

                /*
                |--------------------------------------------------------------------------
                | Renderizar HTML certificado
                |--------------------------------------------------------------------------
                */

                $certificateHtml = $this->templateService->render(
                    $certificateTemplate->html,
                    [
                        'name' => $participant->name,
                        'email' => $participant->email
                    ]
                );

                /*
                |--------------------------------------------------------------------------
                | Gerar PDF e salvar
                |--------------------------------------------------------------------------
                */

                $this->certificateService->generate(
                    $participant,
                    $certificateTemplate,
                    $certificateHtml
                );
                $generatedCount++;

            } catch (\Throwable $e) {
                $failedCount++;
            }
        }

        $auditLogger->record($request->user(), 'certificate.generation_completed', $project, [
            'project_name' => $project->name,
            'generated_count' => $generatedCount,
            'failed_count' => $failedCount,
        ], $request);

        return redirect()->route('certificate.generate.form')->with(
            'status',
            "Geração concluída para {$project->name}: {$generatedCount} gerados, {$failedCount} com erro."
        );
    }
}
