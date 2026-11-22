<?php

namespace Tests\Feature\Support;

use App\Models\Certificate;
use App\Models\Participant;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;

trait CreatesApplicationRecords
{
    protected function createAdmin(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    protected function createProject(User $owner, string $name = 'Projeto de teste'): Project
    {
        return $owner->projects()->create(['name' => $name]);
    }

    protected function createParticipant(Project $project, string $name = 'Pessoa de teste', string $email = 'pessoa@example.test'): Participant
    {
        return $project->participants()->create(compact('name', 'email'));
    }

    protected function createTemplate(string $type, string $name, string $html): Template
    {
        return Template::unguarded(fn () => Template::query()->create([
            'name' => $name,
            'type' => $type,
            'version' => 1,
            'html' => $html,
        ]));
    }

    protected function createCertificate(Participant $participant, Template $template, array $attributes = []): Certificate
    {
        return Certificate::query()->create(array_merge([
            'participant_id' => $participant->id,
            'template_id' => $template->id,
            'pdf_path' => null,
            'status' => 'generated',
            'generated_at' => now(),
        ], $attributes));
    }
}
