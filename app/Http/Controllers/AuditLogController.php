<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $entries = AuditLog::query()
            ->with('user')
            ->latest()
            ->limit(200)
            ->get();

        return view('admin.audit-log', compact('entries'));
    }
}
