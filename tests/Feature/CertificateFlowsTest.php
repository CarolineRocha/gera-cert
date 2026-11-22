<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\CertificateService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Tests\Feature\Support\CreatesApplicationRecords;
use Tests\TestCase;

class CertificateFlowsTest extends TestCase
{
    use CreatesApplicationRecords;
    use DatabaseMigrations;

    public function test_certificate_list_searches_by_name_or_email_and_filters_by_owned_project(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin, 'Workshop A');
        $otherProject = $this->createProject($admin, 'Workshop B');
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $matched = $this->createParticipant($project, 'Pessoa Localizável', 'unique@example.test');
        $another = $this->createParticipant($project, 'Outra Pessoa', 'other@example.test');
        $outsideFilter = $this->createParticipant($otherProject, 'Pessoa Localizável', 'second@example.test');
        $this->createCertificate($matched, $template);
        $this->createCertificate($another, $template);
        $this->createCertificate($outsideFilter, $template);

        $this->actingAs($admin)
            ->get(route('certificate.index', ['q' => 'unique@example.test', 'project_id' => $project->id]))
            ->assertOk()
            ->assertSee($matched->name)
            ->assertDontSee($another->name)
            ->assertDontSee($outsideFilter->email)
            ->assertViewHas('certificates', fn ($certificates) => $certificates->total() === 1);
    }

    public function test_certificate_list_paginates_25_records_and_keeps_search_query(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');

        for ($index = 1; $index <= 27; $index++) {
            $participant = $this->createParticipant($project, sprintf('Pessoa %02d', $index), sprintf('pessoa%02d@example.test', $index));
            $this->createCertificate($participant, $template);
        }

        $this->actingAs($admin)
            ->get(route('certificate.index', ['q' => 'Pessoa']))
            ->assertOk()
            ->assertViewHas('certificates', fn ($certificates) => $certificates->total() === 27 && $certificates->count() === 25)
            ->assertSee('q=Pessoa');

        $this->get(route('certificate.index', ['q' => 'Pessoa', 'page' => 2]))
            ->assertViewHas('certificates', fn ($certificates) => $certificates->total() === 27 && $certificates->count() === 2);
    }

    public function test_generation_processes_only_the_selected_owned_project_and_audits_counts(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $participantA = $this->createParticipant($project, 'Pessoa A', 'a@example.test');
        $participantB = $this->createParticipant($project, 'Pessoa B', 'b@example.test');
        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $otherProject = $this->createProject($otherAdmin, 'Projeto fora do escopo');
        $this->createParticipant($otherProject, 'Pessoa fora do projeto', 'outside@example.test');
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');

        $generator = \Mockery::mock(CertificateService::class);
        $generator->shouldReceive('generate')->twice();
        $this->app->instance(CertificateService::class, $generator);

        $this->actingAs($admin)
            ->post(route('certificate.generate'), ['project_id' => $project->id])
            ->assertRedirect(route('certificate.generate.form'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'certificate.generation_started']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'certificate.generation_completed']);
        $completed = \App\Models\AuditLog::query()->where('action', 'certificate.generation_completed')->firstOrFail();
        $this->assertSame(2, $completed->properties['generated_count']);
        $this->assertNotNull($participantA->fresh());
        $this->assertNotNull($participantB->fresh());
    }

    public function test_generation_rejects_a_project_owned_by_another_admin(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $project = $this->createProject($otherAdmin);

        $this->actingAs($admin)
            ->post(route('certificate.generate'), ['project_id' => $project->id])
            ->assertNotFound();
    }

    public function test_certificate_preview_escapes_name_and_pdf_preview_uses_generator(): void
    {
        $admin = $this->createAdmin();
        $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');

        $this->actingAs($admin)
            ->get(route('certificate.preview', ['nome' => '<script>alert(1)</script>']))
            ->assertOk()
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);

        $generator = \Mockery::mock(CertificateService::class);
        $generator->shouldReceive('PdfGenerate')->once()->andReturn(response('fake-pdf'));
        $this->app->instance(CertificateService::class, $generator);

        $this->get(route('certificate.pdf', ['nome' => 'Pessoa Teste']))
            ->assertOk()
            ->assertSeeText('fake-pdf');
    }

    public function test_owned_certificate_can_be_viewed_and_downloaded_and_both_actions_are_audited(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $participant = $this->createParticipant($project);
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $filename = 'feature-test-'.Str::uuid().'.pdf';
        $directory = public_path('pdf');
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        file_put_contents($directory.DIRECTORY_SEPARATOR.$filename, '%PDF test fixture');
        $certificate = $this->createCertificate($participant, $template, ['pdf_path' => 'pdf/'.$filename]);

        try {
            $this->actingAs($admin)
                ->get(route('certificate.view', $certificate))
                ->assertOk()
                ->assertHeader('content-type', 'application/pdf');

            $this->get(route('certificate.download', $certificate))
                ->assertOk()
                ->assertHeader('content-disposition');
        } finally {
            @unlink($directory.DIRECTORY_SEPARATOR.$filename);
        }

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'certificate.viewed']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'certificate.downloaded']);
    }

    public function test_certificate_file_route_does_not_expose_another_administrators_record(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $project = $this->createProject($otherAdmin);
        $participant = $this->createParticipant($project);
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $certificate = $this->createCertificate($participant, $template, ['pdf_path' => 'pdf/missing.pdf']);

        $this->actingAs($admin)->get(route('certificate.view', $certificate))->assertNotFound();
        $this->assertDatabaseMissing('audit_logs', ['action' => 'certificate.viewed']);
    }
}
