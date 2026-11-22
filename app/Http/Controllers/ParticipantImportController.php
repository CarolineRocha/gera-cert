<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use App\Services\ParticipantImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ParticipantImportController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $request->user()->projects()->withCount('participants')->latest()->get();

        return view('participants.import', compact('projects'));
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['nome', 'email']);
            fclose($output);
        }, 'modelo-importacao-participantes.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(
        Request $request,
        ParticipantImportService $importService,
        AuditLogger $auditLogger
    ): RedirectResponse {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $project = $request->user()->projects()->findOrFail($data['project_id']);
        /** @var UploadedFile $file */
        $file = $data['file'];

        try {
            $result = $importService->import($project, $file);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        $auditLogger->record($request->user(), 'participants.imported', $project, [
            'project_name' => $project->name,
            'added_count' => $result['added'],
            'rejected_count' => $result['rejected'],
        ], $request);

        return redirect()->route('participants.import.form')->with('import_result', $result)
            ->with('import_project_name', $project->name);
    }
}
