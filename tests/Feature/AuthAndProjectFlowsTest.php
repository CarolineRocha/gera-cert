<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\SmtpSetting;
use App\Models\Certificate;
use App\Models\User;
use App\Services\SmtpSettingsManager;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Notification;
use Mockery;
use Tests\Feature\Support\CreatesApplicationRecords;
use Tests\TestCase;

class AuthAndProjectFlowsTest extends TestCase
{
    use CreatesApplicationRecords;
    use DatabaseMigrations;

    public function test_guest_is_redirected_and_admin_can_sign_in_and_sign_out(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $admin = $this->createAdmin(['email' => 'admin@example.test', 'password' => 'safe-test-password']);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'safe-test-password',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'auth.login',
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'auth.logout',
        ]);
    }

    public function test_non_admin_cannot_open_the_dashboard_or_project_pages(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('home'))->assertForbidden();
        $this->actingAs($user)->get(route('projects.index'))->assertForbidden();
        $this->get('/register')->assertNotFound();
    }

    public function test_admin_creates_project_and_sees_only_owned_projects(): void
    {
        $admin = $this->createAdmin();
        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $otherProject = $this->createProject($otherAdmin, 'Projeto privado');

        $this->actingAs($admin)
            ->post(route('projects.store'), ['name' => 'Workshop teste'])
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseHas('projects', [
            'name' => 'Workshop teste',
            'user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'project.created',
        ]);

        $response = $this->get(route('projects.index'));
        $response->assertOk()->assertSee('Workshop teste')->assertDontSee($otherProject->name);
    }

    public function test_project_name_is_required_and_limited_to_255_characters(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin)
            ->from(route('projects.index'))
            ->post(route('projects.store'), ['name' => str_repeat('x', 256)])
            ->assertRedirect(route('projects.index'))
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_initial_admin_command_claims_legacy_smtp_and_unowned_projects(): void
    {
        $project = Project::query()->create(['name' => 'Projeto legado']);
        $smtp = SmtpSetting::query()->create([
            'scheme' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'from_address' => 'mailer@example.test',
            'from_name' => 'Teste',
        ]);

        $this->artisan('app:create-admin')
            ->expectsQuestion('Nome', 'Admin de teste')
            ->expectsQuestion('E-mail', 'admin@example.test')
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'senha-ficticia-de-teste')
            ->expectsQuestion('Confirme a senha', 'senha-ficticia-de-teste')
            ->assertExitCode(0);

        $admin = User::query()->where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertSame($admin->id, $project->fresh()->user_id);
        $this->assertSame($admin->id, $smtp->fresh()->user_id);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'auth.admin_created']);
    }

    public function test_home_summary_counts_only_data_owned_by_the_signed_in_admin(): void
    {
        $admin = $this->createAdmin();
        $project = $this->createProject($admin, 'Meu projeto');
        $participant = $this->createParticipant($project, 'Pessoa', 'pessoa@example.test');
        $template = $this->createTemplate('certificate', 'Certificado', '<p>{{name}}</p>');
        $this->createCertificate($participant, $template);

        $otherAdmin = $this->createAdmin(['email' => 'other@example.test']);
        $otherProject = $this->createProject($otherAdmin, 'Projeto de outra conta');
        $this->createParticipant($otherProject, 'Pessoa externa', 'externa@example.test');

        $this->actingAs($admin)->get(route('home'))
            ->assertOk()
            ->assertViewHas('projectCount', 1)
            ->assertViewHas('participantCount', 1)
            ->assertViewHas('certificateCount', 1);
    }

    public function test_admin_password_reset_link_is_sent_without_exposing_account_existence(): void
    {
        Notification::fake();
        $admin = $this->createAdmin(['email' => 'admin@example.test']);

        $this->mock(SmtpSettingsManager::class, function ($mock) use ($admin): void {
            $mock->shouldReceive('mailer')->once()->with(Mockery::on(
                fn ($user) => $user instanceof User && $user->is($admin)
            ));
        });

        $this->post(route('password.email'), ['email' => $admin->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($admin, ResetPassword::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $admin->email]);
    }

    public function test_password_reset_flow_changes_password_and_writes_audit_record(): void
    {
        $admin = $this->createAdmin(['email' => 'admin@example.test']);
        $token = \Illuminate\Support\Facades\Password::createToken($admin);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'another-safe-password',
            'password_confirmation' => 'another-safe-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(password_verify('another-safe-password', $admin->fresh()->password));
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'auth.password_reset',
        ]);
    }
}
