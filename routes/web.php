<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\SmtpSettingsController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ParticipantImportController;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.store');

    Route::get('/senha/esqueci', [AuthController::class, 'forgotPasswordForm'])->name('password.request');
    Route::post('/senha/esqueci', [AuthController::class, 'sendResetLink'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/senha/redefinir/{token}', [AuthController::class, 'resetPasswordForm'])->name('password.reset');
    Route::post('/senha/redefinir', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'admin'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');

    Route::get('/certificados', [CertificateController::class, 'index'])->name('certificate.index');
    Route::get('/certificados/{certificate}/visualizar', [CertificateController::class, 'viewPdf'])->name('certificate.view');
    Route::get('/certificados/{certificate}/download', [CertificateController::class, 'downloadPdf'])->name('certificate.download');
    Route::get('/certificado-pdf', [CertificateController::class, 'pdf'])->name('certificate.pdf');
    Route::get('/preview', [CertificateController::class, 'preview'])->name('certificate.preview');
    Route::get('/email', [EmailController::class, 'preview'])->name('email.preview');
    Route::get('/configuracoes/smtp', [SmtpSettingsController::class, 'edit'])->name('smtp.settings.edit');
    Route::put('/configuracoes/smtp', [SmtpSettingsController::class, 'update'])->name('smtp.settings.update');
    Route::post('/configuracoes/smtp/teste', [SmtpSettingsController::class, 'sendTest'])->name('smtp.settings.test');
    Route::get('/admin/auditoria', [AuditLogController::class, 'index'])->name('audit.index');
    Route::get('/projetos', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projetos', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/importar/modelo', [ParticipantImportController::class, 'template'])->name('participants.import.template');
    Route::get('/importar', [ParticipantImportController::class, 'index'])->name('participants.import.form');
    Route::post('/importar', [ParticipantImportController::class, 'store'])->name('participants.import.store');

    Route::get('/generate', [CertificateController::class, 'generateForm'])->name('certificate.generate.form');
    Route::post('/generate', [CertificateController::class, 'generate'])->name('certificate.generate');
    Route::get('/send', [EmailController::class, 'sendForm'])->name('email.send.form');
    Route::post('/send', [EmailController::class, 'send'])->name('email.send');
});
