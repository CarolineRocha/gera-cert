<?php

namespace Tests\Feature;

use App\Jobs\SendCertificateEmailJob;
use App\Models\EmailLog;
use App\Services\AuditLogger;
use App\Services\EmailService;
use App\Services\SmtpSettingsManager;
use App\Services\TemplateService;
use Database\Seeders\EmailTemplateSeeder;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Feature\Support\CreatesApplicationRecords;
use Tests\TestCase;

class EmailAndSmtpFlowsTest extends TestCase
{
    use CreatesApplicationRecords;
    use DatabaseMigrations;

    public function test_admin_saves_smtp_password_encrypted_and_change_is_audited(): void
    {
        $admin = $this->createAdmin();
        $secret = 'app-password-test-secret';

        $this->actingAs($admin)->put(route('smtp.settings.update'), [
            'scheme' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer@example.test',
            'password' => $secret,
            'from_address' => 'mailer@example.test',
            'from_name' => 'GeraCert Teste',
        ])->assertRedirect(route('smtp.settings.edit'));

        $stored = \DB::table('smtp_settings')->where('user_id', $admin->id)->value('password');
        $this->assertNotSame($secret, $stored);
        $this->assertSame($secret, $admin->smtpSetting()->first()?->password);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'smtp.updated']);

        $this->get(route('smtp.settings.edit'))->assertOk()->assertDontSee($secret);
    }

    public function test_smtp_test_mail_uses_authenticated_users_settings_and_is_audited(): void
    {
        $admin = $this->createAdmin();
        $admin->smtpSetting()->create([
            'scheme' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer@example.test',
            'password' => 'encrypted-test-password',
            'from_address' => 'mailer@example.test',
            'from_name' => 'GeraCert Teste',
        ]);

        $recipient = 'recipient@example.test';
        $mailer = Mockery::mock(Mailer::class);
        $mailer->shouldReceive('raw')->once()->withArgs(function (string $body, callable $callback) use ($recipient): bool {
            $this->assertStringContainsString('e-mail de teste', $body);
            $message = Mockery::mock(Message::class);
            $message->shouldReceive('to')->once()->with($recipient)->andReturnSelf();
            $message->shouldReceive('subject')->once()->with('Teste de configuração SMTP - GeraCert')->andReturnSelf();
            $callback($message);

            return true;
        });

        $manager = Mockery::mock(SmtpSettingsManager::class);
        $manager->shouldReceive('mailer')->once()->with(Mockery::on(fn ($user) => $user->is($admin)))->andReturn($mailer);
        $this->app->instance(SmtpSettingsManager::class, $manager);

        $this->actingAs($admin)->post(route('smtp.settings.test'), ['recipient' => $recipient])
            ->assertRedirect(route('smtp.settings.edit'));

        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'smtp.test_sent']);
    }

    public function test_send_flow_queues_only_certificates_for_selected_project(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $otherProject = $this->createProject($admin, 'Outro projeto');
        $participant = $this->createParticipant($project, 'Pessoa com certificado', 'person@example.test');
        $withoutCertificate = $this->createParticipant($project, 'Pessoa sem certificado', 'no-cert@example.test');
        $otherParticipant = $this->createParticipant($otherProject, 'Pessoa de outro projeto', 'other@example.test');
        $certificateTemplate = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $this->createTemplate('email', 'E-mail de teste', '<p>Olá {{name}}</p>');
        $this->createCertificate($participant, $certificateTemplate);
        $this->createCertificate($otherParticipant, $certificateTemplate);

        $this->actingAs($admin)->post(route('email.send'), ['project_id' => $project->id])
            ->assertRedirect(route('email.send.form'))
            ->assertSessionHas('status');

        Queue::assertPushed(SendCertificateEmailJob::class, 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'email.queue_created',
            'subject_id' => $project->id,
        ]);
        $this->assertNotNull($withoutCertificate->fresh());
    }

    public function test_send_flow_cannot_queue_another_admins_project(): void
    {
        Queue::fake();
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $project = $this->createProject($otherAdmin);

        $this->actingAs($admin)->post(route('email.send'), ['project_id' => $project->id])->assertNotFound();
        Queue::assertNothingPushed();
    }

    public function test_send_job_uses_the_latest_template_and_preserves_the_sender(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $participant = $this->createParticipant($project, 'Nome para e-mail', 'person@example.test');
        $certificateTemplate = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $this->createTemplate('email', 'Antigo', '<p>Antigo {{name}}</p>');
        $this->createTemplate('email', 'Mais recente', '<p>Olá {{name}}</p>');
        $certificate = $this->createCertificate($participant, $certificateTemplate);

        $emailService = Mockery::mock(EmailService::class);
        $emailService->shouldReceive('send')->once()->withArgs(function ($sentParticipant, $sentCertificate, $subject, $html, $sender) use ($participant, $certificate, $admin): bool {
            return $sentParticipant->is($participant)
                && $sentCertificate->is($certificate)
                && $subject === 'Seu certificado está pronto'
                && $html === '<p>Olá Nome para e-mail</p>'
                && $sender->is($admin);
        })->andReturn(true);

        (new SendCertificateEmailJob($participant->id, $certificate->id, $admin->id))
            ->handle(app(TemplateService::class), $emailService);
    }

    public function test_email_service_records_success_and_failure_without_copying_smtp_error_details(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin);
        $participant = $this->createParticipant($project, 'Pessoa E-mail', 'person@example.test');
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $certificate = $this->createCertificate($participant, $template);

        $successMailer = Mockery::mock(Mailer::class);
        $successMailer->shouldReceive('html')->once();
        $successManager = Mockery::mock(SmtpSettingsManager::class);
        $successManager->shouldReceive('mailer')->once()->with($admin)->andReturn($successMailer);
        $successService = new EmailService($successManager, app(AuditLogger::class));

        $this->assertTrue($successService->send($participant, $certificate, 'Assunto teste', '<p>Olá</p>', $admin));
        $this->assertDatabaseHas('email_logs', [
            'participant_id' => $participant->id,
            'sent_by_user_id' => $admin->id,
            'recipient' => 'person@example.test',
            'status' => 'sent',
        ]);

        $failureMailer = Mockery::mock(Mailer::class);
        $failureMailer->shouldReceive('html')->once()->andThrow(new \RuntimeException('SMTP password: confidential-value'));
        $failureManager = Mockery::mock(SmtpSettingsManager::class);
        $failureManager->shouldReceive('mailer')->once()->with($admin)->andReturn($failureMailer);
        $failureService = new EmailService($failureManager, app(AuditLogger::class));

        $this->assertFalse($failureService->send($participant, $certificate, 'Assunto teste', '<p>Olá</p>', $admin));
        $failedLog = EmailLog::query()->where('status', 'error')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('confidential-value', $failedLog->error_message);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'email.sent']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'email.failed']);
    }

    public function test_default_email_template_seeder_is_repeatable_and_does_not_overwrite_existing_rows(): void
    {
        $this->seed(EmailTemplateSeeder::class);
        $template = \App\Models\Template::query()->where('name', 'Template de teste')->firstOrFail();
        $this->assertStringContainsString('{{name}}', $template->html);
        $originalHtml = $template->html;

        $this->seed(EmailTemplateSeeder::class);

        $this->assertDatabaseCount('templates', 1);
        $this->assertSame($originalHtml, $template->fresh()->html);
    }
}
