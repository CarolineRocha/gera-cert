<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $ownedProjects = fn ($query) => $query->where('user_id', $userId);

        return view('home', [
            'projectCount' => $request->user()->projects()->count(),
            'participantCount' => Participant::query()
                ->whereHas('project', $ownedProjects)
                ->count(),
            'certificateCount' => Certificate::query()
                ->whereHas('participant.project', $ownedProjects)
                ->count(),
        ]);
    }
}
