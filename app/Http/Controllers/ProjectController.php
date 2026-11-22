<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(Request $request): View
    {
        $projects = $request->user()->projects()
            ->withCount('participants')
            ->latest()
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function store(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project = $request->user()->projects()->create($data);
        $auditLogger->record($request->user(), 'project.created', $project, [
            'project_name' => $project->name,
        ], $request);

        return redirect()->route('projects.index')->with('status', 'Projeto criado.');
    }
}
