<?php

use App\Http\Controllers\Admin\AccommodationController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\EnrollmentProgressController;
use App\Http\Controllers\Admin\ExternalTrainingController;
use App\Http\Controllers\Admin\HistoricalMigrationController;
use App\Http\Controllers\Admin\ManpowerController;
use App\Http\Controllers\Admin\ProgramScheduleController;
use App\Http\Controllers\Admin\ReportExportController;
use App\Http\Controllers\Admin\SkillMatrixController;
use App\Http\Controllers\Admin\TrainerManagementController;
use App\Http\Controllers\Admin\TrainingAdministrationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\Competency\CompetencyReportController;
use App\Http\Controllers\Competency\CompetencyMasterController;
use App\Http\Controllers\Competency\PositionSkillSettingController;
use App\Http\Controllers\Training\CourseMasterController;
use App\Http\Controllers\Training\TrainingCalendarController;
use App\Http\Controllers\Training\TrainingHoursController;
use App\Http\Controllers\OrganizationAdmin\OrganizationPortalController;
use App\Http\Controllers\Participant\ParticipantPortalController;
use App\Http\Controllers\ParticipantQuizController;
use App\Http\Controllers\PublicCertificateVerificationController;
use App\Http\Controllers\SelfReportTrainingController;
use App\Http\Controllers\Trainer\TrainerDashboardController;
use App\Http\Controllers\Trainer\TrainerPortalController;
use App\Http\Controllers\Trainer\TrainerTodoController;
use App\Http\Controllers\TrainingTodayController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (!auth()->check()) {
        return redirect()->route('login');
    }

    return match (auth()->user()->role) {
        'peserta' => redirect()->route('mobile.home'),
        'dept_admin', 'contractor_admin' => redirect()->route('org-admin.portal'),
        'trainer' => redirect()->route('trainer.dashboard'),
        default => redirect()->route('dashboard'),
    };
});

/*
|--------------------------------------------------------------------------
| Route Publik: Verifikasi Keaslian Sertifikat & Rapor (Bebas Akses Tanpa Login)
|--------------------------------------------------------------------------
*/
Route::get('/verify-certificate/{certificate_number}', [PublicCertificateVerificationController::class, 'verify'])
    ->name('public.certificate.verify')
    ->where('certificate_number', '.*');


/*
|--------------------------------------------------------------------------
| BLUEPRINT HIERARKI 7 GRUP SIDEBAR UTAMA & RBAC ENDPOINTS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| 1. DASHBOARD & OVERVIEW
| - Training Program Dashboard (Internal, Induksi, Refresh)
| - Simulator Dashboard (Refresh, New-Hire)
| - Training Competency Dashboard (Gap, Dept Competency, Dept Progress)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/training-program', [AdminDashboardController::class, 'index'])->name('dashboard.training-program');
    Route::get('/dashboard/simulator', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Simulator Dashboard',
        'subtitle' => 'Refresh & New-Hire Operator Simulation Performance Analytics',
        'section' => 'Dashboard & Overview',
    ]))->name('dashboard.simulator');
    Route::get('/dashboard/competency', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Training Competency Dashboard',
        'subtitle' => 'Competency Gap, Department Competency & Progress Overview',
        'section' => 'Dashboard & Overview',
    ]))->name('dashboard.competency');
    Route::get('/admin/analytics/gain-score', [AdminDashboardController::class, 'gainScoreAnalytics'])->name('admin.analytics.gain-score');
});

/*
|--------------------------------------------------------------------------
| 2. TRAINER (Khusus role 'trainer' dan 'lnd')
| - Trainer Hours
| - Trainer Schedule
| - Trainer Programs
| - Trainer To'do
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:trainer,lnd,admin'])->group(function () {
    // Kelola Semua Trainer (Master Data Trainer, Roster 360°, Lisensi & KPI - Admin L&D)
    Route::get('/trainer/management', [TrainerManagementController::class, 'index'])->name('trainer.management');

    // Summary All Trainers untuk Training Hours, Riwayat & Target Management
    Route::get('/trainer/hours', [TrainingHoursController::class, 'index'])->name('trainer.hours');
    Route::get('/training/hours', [TrainingHoursController::class, 'index'])->name('training.hours');
    Route::post('/trainer/hours/target', [TrainingHoursController::class, 'storeTarget'])->name('trainer.hours.target.store');
    Route::get('/trainer/hours/{trainer}/programs', [TrainingHoursController::class, 'trainerPrograms'])->name('trainer.hours.programs');
    Route::get('/trainer/schedule', [TrainerManagementController::class, 'masterRosterCalendar'])->name('trainer.schedule');
    Route::get('/trainer/programs', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Trainer Programs',
        'subtitle' => 'Daftar Program & Silabus yang Diampu Instruktur L&D',
        'section' => 'Trainer',
    ]))->name('trainer.programs');
    // Trainer To-Do: Perintah, Tugas & Jadwal dari Admin LnD & Trainer View
    Route::get('/trainer/todos', [TrainerTodoController::class, 'index'])->name('trainer.todos');
    Route::post('/trainer/todos', [TrainerTodoController::class, 'store'])->name('trainer.todos.store');
    Route::put('/trainer/todos/{todo}', [TrainerTodoController::class, 'update'])->name('trainer.todos.update');
    Route::patch('/trainer/todos/{todo}/toggle', [TrainerTodoController::class, 'toggleStatus'])->name('trainer.todos.toggle');
    Route::delete('/trainer/todos/{todo}', [TrainerTodoController::class, 'destroy'])->name('trainer.todos.destroy');

    // Portal Instruktur Lengkap (Command Center, Profil & Log Jam Mengajar)
    Route::get('/trainer/dashboard', [TrainerPortalController::class, 'dashboard'])->name('trainer.dashboard');
    Route::get('/trainer/my-profile', [TrainerPortalController::class, 'dashboard'])->name('trainer.my-profile');
    Route::get('/trainer/schedule-and-hours', [TrainerPortalController::class, 'dashboard'])->name('trainer.schedule-hours');
    Route::get('/trainer/sessions/{session}/qr-payload', [TrainerPortalController::class, 'sessionQrPayload'])->name('trainer.sessions.qr-payload');
});

/*
|--------------------------------------------------------------------------
| 3. TRAINING ADMINISTRATION
| - Pendaftaran Training (Global Admin)
| - Akomodasi / Process Training (STRICT RBAC: role:lnd,trainer)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/training-administration/registration', [TrainingAdministrationController::class, 'index'])->name('training.registration');
    Route::get('/training/administration', [TrainingAdministrationController::class, 'index'])->name('training.administration.index');
    Route::post('/training/courses', [CourseMasterController::class, 'store'])->name('training.courses.store');
    Route::put('/training/courses/{course}', [CourseMasterController::class, 'update'])->name('training.courses.update');
    Route::delete('/training/courses/{course}', [CourseMasterController::class, 'destroy'])->name('training.courses.destroy');
    Route::post('/training/administration/batch-certify', [TrainingAdministrationController::class, 'batchGraduateAndCertify'])->name('training.administration.batch-certify');
    Route::post('/training/administration/batch-graduate', [TrainingAdministrationController::class, 'batchGraduateAndCertify'])->name('training.administration.batch-graduate');
    Route::post('/training/administration/enroll', [TrainingAdministrationController::class, 'storeEnrollment'])->name('training.administration.enroll');
    Route::post('/training/administration/external-certificate', [TrainingAdministrationController::class, 'storeExternalCertificate'])->name('training.administration.external-certificate');
    Route::get('/training/administration/export-csv', [TrainingAdministrationController::class, 'exportLedgerCsv'])->name('training.administration.export-csv');

    // Enrollments & Module Progress
    Route::get('/admin/enrollments', [EnrollmentProgressController::class, 'index'])->name('admin.enrollments.index');
    Route::get('/admin/course-progress', [EnrollmentProgressController::class, 'index'])->name('admin.progress.index');
    Route::post('/admin/enrollments/bulk-enroll', [EnrollmentProgressController::class, 'bulkEnroll'])->name('admin.enrollments.bulk-enroll');
    Route::post('/admin/enrollments/bulk-status', [EnrollmentProgressController::class, 'bulkUpdateStatus'])->name('admin.enrollments.bulk-status');
    Route::patch('/admin/enrollments/{enrollment}/status', [EnrollmentProgressController::class, 'updateStatus'])->name('admin.enrollments.status');
    Route::patch('/admin/enrollments/{enrollment}/link-session', [EnrollmentProgressController::class, 'linkSession'])->name('admin.enrollments.link-session');
    Route::patch('/admin/enrollments/{enrollment}/toggle-module', [EnrollmentProgressController::class, 'toggleModule'])->name('admin.enrollments.toggle-module');
    Route::get('/admin/enrollments/{enrollment}/documents/download', [ExternalTrainingController::class, 'downloadParticipantDocument'])->name('admin.enrollments.documents.download');
    Route::put('/admin/training/courses/{course}/logistics', [ExternalTrainingController::class, 'updateLogistics'])->name('admin.training.courses.logistics.update');
    Route::patch('/admin/training/courses/{course}/accommodation-status', [ExternalTrainingController::class, 'updateAccommodationStatus'])->name('admin.training.courses.accommodation-status');
});

// Akomodasi / Process Training (STRICT BACKEND RBAC: hanya role 'lnd' dan 'trainer')
Route::get('/training-administration/accommodation', [AccommodationController::class, 'index'])
    ->middleware(['auth', 'verified', 'role:lnd,trainer'])
    ->name('training.accommodation');

/*
|--------------------------------------------------------------------------
| 4. TRAINING PROGRAM
| - Program & Topik Pelatihan (Training Topics / Courses)
| - Jadwal Training (Training Calendar / ATMP)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/training-program/courses', [CourseMasterController::class, 'index'])->name('training-program.courses');
    Route::post('/training-program/courses/{course}/schedule', [CourseMasterController::class, 'scheduleSession'])->name('training-program.courses.schedule');
    Route::get('/training-program/schedules', [TrainingCalendarController::class, 'index'])->name('training-program.schedules');
});

/*
|--------------------------------------------------------------------------
| 5. TRAINING COMPETENCY
| - Training Matrix
| - Competency Matrix
| - TNA & Individu Checkup Competency
| - Position x Competency
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/competency/training-matrix', [SkillMatrixController::class, 'index'])->name('competency.training-matrix');
    Route::get('/competency/matrix', [SkillMatrixController::class, 'index'])->name('competency.matrix.index');
    Route::get('/admin/skill-matrix', [SkillMatrixController::class, 'index'])->name('admin.skill-matrix.index');
    Route::patch('/admin/skill-matrix/cell', [SkillMatrixController::class, 'updateCell'])->name('admin.skill-matrix.update-cell');

    Route::get('/competency/individual-view', [CompetencyReportController::class, 'individualView'])->name('competency.individual-view');
    Route::get('/competency/training-summary', [CompetencyReportController::class, 'trainingSummary'])->name('competency.training-summary');

    // Setting Standar Level x Position
    Route::get('/competency/position-standards', [PositionSkillSettingController::class, 'index'])->name('competency.position-standards.index');
    Route::post('/competency/position-standards/positions', [PositionSkillSettingController::class, 'storePosition'])->name('competency.position-standards.store-position');
    Route::post('/competency/position-standards/{jobPosition}/save', [PositionSkillSettingController::class, 'saveStandards'])->name('competency.position-standards.save');
    Route::post('/competency/position-standards/{jobPosition}/sync-workers', [PositionSkillSettingController::class, 'syncToWorkers'])->name('competency.position-standards.sync');
    Route::post('/competency/position-standards/{jobPosition}/assign-worker', [PositionSkillSettingController::class, 'assignWorkerPosition'])->name('competency.position-standards.assign-worker');

    // Master Competency List
    Route::get('/competency/list', [CompetencyMasterController::class, 'index'])->name('competency.list.index');
    Route::get('/admin/competencies', [CompetencyMasterController::class, 'index'])->name('admin.competencies.index');
    Route::post('/competency/list', [CompetencyMasterController::class, 'store'])->name('competency.list.store');
    Route::put('/competency/list/{competency}', [CompetencyMasterController::class, 'update'])->name('competency.list.update');
    Route::delete('/competency/list/{competency}', [CompetencyMasterController::class, 'destroy'])->name('competency.list.destroy');
});

/*
|--------------------------------------------------------------------------
| 6. DOCUMENT MANAGEMENT SYSTEM
| - Arsip
| (Catatan: Sub-menu Report dipindahkan ke grup Reporting)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/document-management/archive', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Document Management System - Arsip',
        'subtitle' => 'Pusat Pengarsipan Sertifikat, Dokumen K3 & Berkas Pelatihan Digital',
        'section' => 'Document Management System',
        'description' => 'Arsip digital terintegrasi untuk penyimpanan dokumen pelatihan, surat tugas, absensi bertandatangan, dan salinan sertifikat resmi.',
    ]))->name('documents.archive');
});

/*
|--------------------------------------------------------------------------
| 7. REPORTING
| - Training Report
| - Simulator Report
| - Competency Report
| - Trainers Report
| - Document Report
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/reporting/training', [CompetencyReportController::class, 'trainingSummary'])->name('reporting.training');
    Route::get('/reporting/simulator', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Simulator Report',
        'subtitle' => 'Laporan Rekapitulasi Pelatihan, Jam Terbang & Evaluasi Simulator Operasional',
        'section' => 'Reporting',
    ]))->name('reporting.simulator');
    Route::get('/reporting/competency', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Competency Report',
        'subtitle' => 'Laporan Analisis Kesenjangan Kompetensi (Gap Analysis) & Kepatuhan Standar Jabatan',
        'section' => 'Reporting',
    ]))->name('reporting.competency');
    Route::get('/reporting/trainers', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Trainers Report',
        'subtitle' => 'Laporan Rekapitulasi Jam Mengajar, Audit Roster & Evaluasi Peserta Instruktur',
        'section' => 'Reporting',
    ]))->name('reporting.trainers');
    Route::get('/reporting/documents', fn () => Inertia::render('UnderConstruction', [
        'title' => 'Document Report',
        'subtitle' => 'Laporan Rekapitulasi Kelengkapan Berkas, Validitas Masa Berlaku & Audit Dokumen',
        'section' => 'Reporting',
    ]))->name('reporting.documents');
});

/*
|--------------------------------------------------------------------------
| Rute Operasional Pendukung (Proyektor, Presensi, Roster & Ekspor)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer'])->group(function () {
    Route::get('/admin/attendance-sessions', [AttendanceController::class, 'projector'])->name('admin.attendance.index');
    Route::post('/admin/attendance-sessions', [AttendanceController::class, 'store'])->name('admin.attendance.store');
    Route::patch('/admin/attendance-sessions/{session}/regenerate', [AttendanceController::class, 'regenerateSecret'])->name('admin.attendance.regenerate');
    Route::patch('/admin/attendance-sessions/{session}/toggle', [AttendanceController::class, 'toggleStatus'])->name('admin.attendance.toggle');
    Route::get('/admin/quiz-attempts/{attempt}/result', [ParticipantQuizController::class, 'result'])->name('admin.quizzes.result');
});

Route::middleware(['auth', 'verified', 'role:lnd,admin,dept_admin,contractor_admin'])->group(function () {
    Route::get('/organization/portal', [OrganizationPortalController::class, 'index'])->name('org-admin.portal');
    Route::get('/organization/renewal-alerts', [OrganizationPortalController::class, 'renewalAlerts'])->name('org.renewal.index');
    Route::post('/organization/renewal-requests', [OrganizationPortalController::class, 'requestRenewal'])->name('org.renewal.store');
    Route::post('/organization/bulk-enroll', [OrganizationPortalController::class, 'bulkEnroll'])->name('org-admin.bulk-enroll');
    Route::post('/organization/training-requests', [OrganizationPortalController::class, 'storeTrainingRequest'])->name('org-admin.training-requests.store');
    Route::patch('/organization/training-requests/{trainingRequest}/status', [OrganizationPortalController::class, 'updateRequestStatus'])->name('org-admin.training-requests.status');

    // Manpower CRUD, Import CSV, Batch Enroll, Migrasi Masa Lalu
    Route::get('/admin/manpower', [ManpowerController::class, 'index'])->name('admin.manpower.index');
    Route::post('/admin/manpower', [ManpowerController::class, 'store'])->name('admin.manpower.store');
    Route::put('/admin/manpower/{manpower}', [ManpowerController::class, 'update'])->name('admin.manpower.update');
    Route::post('/admin/manpower/{manpower}/map-position-org', [ManpowerController::class, 'mapWorkerPositionOrg'])->name('admin.manpower.map-position-org');
    Route::delete('/admin/manpower/{manpower}', [ManpowerController::class, 'destroy'])->name('admin.manpower.destroy');
    Route::post('/admin/manpower/import-csv', [ManpowerController::class, 'importCsv'])->name('admin.manpower.import-csv');
    Route::get('/admin/manpower/template-csv', [ManpowerController::class, 'downloadTemplate'])->name('admin.manpower.template-csv');
    Route::post('/admin/manpower/enrollments', [ManpowerController::class, 'storeEnrollment'])->name('admin.manpower.enrollments.store');
    Route::post('/admin/historical-migrations', [HistoricalMigrationController::class, 'store'])->name('admin.historical-migrations.store');
    Route::patch('/admin/self-reports/{selfReport}/review', [SelfReportTrainingController::class, 'review'])->name('admin.self-reports.review');
});

Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin'])->group(function () {
    Route::get('/admin/exports/skill-matrix', [ReportExportController::class, 'exportSkillMatrix'])->name('admin.exports.skill-matrix');
    Route::get('/admin/export/matrix', [ReportExportController::class, 'exportSkillMatrix'])->name('admin.export.matrix');
    Route::get('/admin/exports/courses/{course}/attendance', [ReportExportController::class, 'exportAttendance'])->name('admin.exports.course-attendance');
    Route::get('/admin/participants/{user}/report-print', [ReportExportController::class, 'printParticipantReport'])->name('admin.participants.report-print');
    Route::get('/admin/participants/{user}/print-report', [ReportExportController::class, 'printParticipantReport'])->name('admin.participants.print-report');
});

// Training Today & Calendar
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin,peserta'])->group(function () {
    Route::get('/training-today', [TrainingTodayController::class, 'index'])->name('training-today.index');
    Route::get('/training/calendar', [TrainingCalendarController::class, 'index'])->name('training.calendar.index');
});

// Perencanaan jadwal & Trainer Management (Admin LnD)
Route::middleware(['auth', 'verified', 'role:lnd,admin'])->group(function () {
    Route::post('/training/calendar/sessions', [TrainingCalendarController::class, 'store'])->name('training.calendar.store');
    Route::patch('/training/calendar/sessions/{session}/plan-status', [TrainingCalendarController::class, 'updatePlanStatus'])->name('training.calendar.plan-status');
    Route::get('/admin/program-schedules', [ProgramScheduleController::class, 'index'])->name('admin.program-schedules.index');
    Route::get('/admin/program-schedules/check-availability', [ProgramScheduleController::class, 'checkTrainerAvailability'])->name('admin.program-schedules.check-availability');
    Route::get('/admin/program-schedules/export', [ProgramScheduleController::class, 'export'])->name('admin.program-schedules.export');
    Route::post('/admin/program-schedules', [ProgramScheduleController::class, 'store'])->name('admin.program-schedules.store');
    Route::put('/admin/program-schedules/{session}', [ProgramScheduleController::class, 'update'])->name('admin.program-schedules.update');
    Route::patch('/admin/program-schedules/{session}/status', [ProgramScheduleController::class, 'updateStatus'])->name('admin.program-schedules.status');
    Route::delete('/admin/program-schedules/{session}', [ProgramScheduleController::class, 'destroy'])->name('admin.program-schedules.destroy');

    // Modul Trainer Management, Roster, KPI & Log Mengajar (Admin LnD)
    Route::get('/admin/trainers/master-roster', [TrainerManagementController::class, 'masterRosterCalendar'])->name('admin.trainers.roster.master');
    Route::get('/admin/trainers', [TrainerManagementController::class, 'index'])->name('admin.trainers.index');
    Route::post('/admin/trainers/promote', [TrainerManagementController::class, 'promoteManpower'])->name('admin.trainers.promote');
    Route::get('/admin/trainers/{trainer}', [TrainerManagementController::class, 'show'])->name('admin.trainers.show');
    Route::put('/admin/trainers/{trainer}', [TrainerManagementController::class, 'updateProfile'])->name('admin.trainers.update');
    Route::post('/admin/trainers/{trainer}/authorized-skills', [TrainerManagementController::class, 'storeAuthorizedSkill'])->name('admin.trainers.skills.store');
    Route::delete('/admin/trainers/{trainer}/authorized-skills/{skill}', [TrainerManagementController::class, 'destroyAuthorizedSkill'])->name('admin.trainers.skills.destroy');
    Route::post('/admin/trainers/{trainer}/roster/set', [TrainerManagementController::class, 'setRoster'])->name('admin.trainers.roster.set');
    Route::post('/admin/trainers/{trainer}/roster/bulk', [TrainerManagementController::class, 'bulkSetRoster'])->name('admin.trainers.roster.bulk');
    Route::post('/admin/trainers/{trainer}/teaching-logs/manual', [TrainerManagementController::class, 'storeManualTeachingLog'])->name('admin.trainers.logs.manual');
    Route::get('/admin/attendance-sessions/{session}/scanned-participants', [TrainerManagementController::class, 'getScannedParticipants'])->name('admin.attendance.participants');
    Route::post('/admin/trainers/{trainer}/kpis', [TrainerManagementController::class, 'storeKpiTask'])->name('admin.trainers.kpis.store');
    Route::patch('/admin/trainers/{trainer}/kpis/{kpi}', [TrainerManagementController::class, 'updateKpiTask'])->name('admin.trainers.kpis.update');
    Route::delete('/admin/trainers/{trainer}/kpis/{kpi}', [TrainerManagementController::class, 'destroyKpiTask'])->name('admin.trainers.kpis.destroy');
});

// Endpoint Submit Evaluasi & Rating Bintang Trainer dari Peserta
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/participant/trainer-evaluations', [TrainerManagementController::class, 'storeEvaluation'])->name('participant.trainer-evaluations.store');
});

/*
|--------------------------------------------------------------------------
| Mobile Web App (Peserta Internal & Pekerja Kontraktor)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:peserta'])->group(function () {
    Route::get('/participant/dashboard', [ParticipantPortalController::class, 'dashboard'])->name('mobile.home');
    Route::get('/m/home', [ParticipantPortalController::class, 'dashboard']);
    Route::get('/m/my-courses', [ParticipantPortalController::class, 'dashboard'])->name('mobile.courses');
    Route::post('/participant/courses/enroll', [ParticipantPortalController::class, 'enrollCourse'])->name('participant.courses.enroll');
    Route::post('/participant/enrollments/{enrollment}/documents', [ExternalTrainingController::class, 'uploadParticipantDocuments'])->name('participant.enrollments.documents.store');
    Route::get('/participant/certificate/{user}/print', [ReportExportController::class, 'printParticipantReport'])->name('participant.certificate.print');

    // Scanner QR Kamera HP & Submit Presensi
    Route::get('/participant/attendance/scan', [ParticipantPortalController::class, 'showScanner'])->name('mobile.scan');
    Route::get('/m/scan-qr', [ParticipantPortalController::class, 'showScanner']);
    Route::post('/participant/attendance/scan', [AttendanceController::class, 'scan'])->name('participant.attendance.scan');

    // Profil Kompetensi & Radar Chart Peserta
    Route::get('/participant/profile-matrix', [ParticipantPortalController::class, 'profileMatrix'])->name('mobile.skills');
    Route::get('/m/skill-profile', [ParticipantPortalController::class, 'profileMatrix']);

    // Self-Report Sertifikat Mandiri
    Route::get('/participant/self-reports', [SelfReportTrainingController::class, 'index'])->name('participant.self-reports.index');
    Route::post('/participant/self-reports', [SelfReportTrainingController::class, 'store'])->name('participant.self-reports.store');

    // Modul Quiz Interaktif
    Route::get('/participant/quizzes/{quiz}/take', [ParticipantQuizController::class, 'start'])->name('participant.quizzes.take');
    Route::post('/participant/quiz-attempts/{attempt}/submit', [ParticipantQuizController::class, 'submit'])->name('participant.quizzes.submit');
    Route::get('/participant/quiz-attempts/{attempt}/result', [ParticipantQuizController::class, 'result'])->name('participant.quizzes.result');
});

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}

/*
|--------------------------------------------------------------------------
| Grup 4: Route TRAINING TODAY (Operasional Hari Ini - Semua Role Sesuai Kapasitasnya)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:lnd,admin,trainer,dept_admin,contractor_admin,peserta'])->group(function () {
    Route::get('/training-today', [TrainingTodayController::class, 'index'])->name('training-today.index');

    // Interactive Training Calendar (ATMP / Bulanan / Mingguan) + Live Control Center
    Route::get('/training/calendar', [TrainingCalendarController::class, 'index'])->name('training.calendar.index');
});

// Perencanaan jadwal: hanya Tim LnD / Super Admin
Route::middleware(['auth', 'verified', 'role:lnd,admin'])->group(function () {
    Route::post('/training/calendar/sessions', [TrainingCalendarController::class, 'store'])->name('training.calendar.store');
    Route::patch('/training/calendar/sessions/{session}/plan-status', [TrainingCalendarController::class, 'updatePlanStatus'])->name('training.calendar.plan-status');
    Route::get('/admin/program-schedules', [ProgramScheduleController::class, 'index'])->name('admin.program-schedules.index');
    Route::get('/admin/program-schedules/check-availability', [ProgramScheduleController::class, 'checkTrainerAvailability'])->name('admin.program-schedules.check-availability');
    Route::get('/admin/program-schedules/export', [ProgramScheduleController::class, 'export'])->name('admin.program-schedules.export');
    Route::post('/admin/program-schedules', [ProgramScheduleController::class, 'store'])->name('admin.program-schedules.store');
    Route::put('/admin/program-schedules/{session}', [ProgramScheduleController::class, 'update'])->name('admin.program-schedules.update');
    Route::patch('/admin/program-schedules/{session}/status', [ProgramScheduleController::class, 'updateStatus'])->name('admin.program-schedules.status');
    Route::delete('/admin/program-schedules/{session}', [ProgramScheduleController::class, 'destroy'])->name('admin.program-schedules.destroy');

    // Modul Trainer Management, Roster, KPI & Log Mengajar (Admin LnD)
    Route::get('/admin/trainers/master-roster', [TrainerManagementController::class, 'masterRosterCalendar'])->name('admin.trainers.roster.master');
    Route::get('/admin/trainers', [TrainerManagementController::class, 'index'])->name('admin.trainers.index');
    Route::post('/admin/trainers/promote', [TrainerManagementController::class, 'promoteManpower'])->name('admin.trainers.promote');
    Route::get('/admin/trainers/{trainer}', [TrainerManagementController::class, 'show'])->name('admin.trainers.show');
    Route::put('/admin/trainers/{trainer}', [TrainerManagementController::class, 'updateProfile'])->name('admin.trainers.update');
    Route::post('/admin/trainers/{trainer}/authorized-skills', [TrainerManagementController::class, 'storeAuthorizedSkill'])->name('admin.trainers.skills.store');
    Route::delete('/admin/trainers/{trainer}/authorized-skills/{skill}', [TrainerManagementController::class, 'destroyAuthorizedSkill'])->name('admin.trainers.skills.destroy');
    Route::post('/admin/trainers/{trainer}/roster/set', [TrainerManagementController::class, 'setRoster'])->name('admin.trainers.roster.set');
    Route::post('/admin/trainers/{trainer}/roster/bulk', [TrainerManagementController::class, 'bulkSetRoster'])->name('admin.trainers.roster.bulk');
    Route::post('/admin/trainers/{trainer}/teaching-logs/manual', [TrainerManagementController::class, 'storeManualTeachingLog'])->name('admin.trainers.logs.manual');
    Route::get('/admin/attendance-sessions/{session}/scanned-participants', [TrainerManagementController::class, 'getScannedParticipants'])->name('admin.attendance.participants');
    Route::post('/admin/trainers/{trainer}/kpis', [TrainerManagementController::class, 'storeKpiTask'])->name('admin.trainers.kpis.store');
    Route::patch('/admin/trainers/{trainer}/kpis/{kpi}', [TrainerManagementController::class, 'updateKpiTask'])->name('admin.trainers.kpis.update');
    Route::delete('/admin/trainers/{trainer}/kpis/{kpi}', [TrainerManagementController::class, 'destroyKpiTask'])->name('admin.trainers.kpis.destroy');
});


// Endpoint Submit Evaluasi & Rating Bintang Trainer dari Peserta (Bisa diakses peserta, lnd, admin)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/participant/trainer-evaluations', [TrainerManagementController::class, 'storeEvaluation'])->name('participant.trainer-evaluations.store');
});

/*
|--------------------------------------------------------------------------
| Grup 5: Route Mobile Web App (Peserta Internal & Pekerja Kontraktor)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'role:peserta'])->group(function () {
    // Portal Peserta, Jadwal Mendatang, Riwayat Pelatihan & Unduh Sertifikat Pribadi
    Route::get('/participant/dashboard', [ParticipantPortalController::class, 'dashboard'])->name('mobile.home');
    Route::get('/m/home', [ParticipantPortalController::class, 'dashboard']);
    Route::get('/m/my-courses', [ParticipantPortalController::class, 'dashboard'])->name('mobile.courses');
    Route::post('/participant/courses/enroll', [ParticipantPortalController::class, 'enrollCourse'])->name('participant.courses.enroll');
    Route::post('/participant/enrollments/{enrollment}/documents', [ExternalTrainingController::class, 'uploadParticipantDocuments'])->name('participant.enrollments.documents.store');
    Route::get('/participant/certificate/{user}/print', [ReportExportController::class, 'printParticipantReport'])->name('participant.certificate.print');

    // Scanner QR Kamera HP & Endpoint Submit Presensi (Stateless Rotating QR Verification)
    Route::get('/participant/attendance/scan', [ParticipantPortalController::class, 'showScanner'])->name('mobile.scan');
    Route::get('/m/scan-qr', [ParticipantPortalController::class, 'showScanner']);
    Route::post('/participant/attendance/scan', [AttendanceController::class, 'scan'])->name('participant.attendance.scan');

    // Profil Kompetensi & Radar Chart Peserta
    Route::get('/participant/profile-matrix', [ParticipantPortalController::class, 'profileMatrix'])->name('mobile.skills');
    Route::get('/m/skill-profile', [ParticipantPortalController::class, 'profileMatrix']);

    // Self-Report Sertifikat Mandiri Peserta (Pending Approval LnD)
    Route::get('/participant/self-reports', [SelfReportTrainingController::class, 'index'])->name('participant.self-reports.index');
    Route::post('/participant/self-reports', [SelfReportTrainingController::class, 'store'])->name('participant.self-reports.store');

    // Modul Quiz Interaktif (Pre-Test & Post-Test) Peserta
    Route::get('/participant/quizzes/{quiz}/take', [ParticipantQuizController::class, 'start'])->name('participant.quizzes.take');
    Route::post('/participant/quiz-attempts/{attempt}/submit', [ParticipantQuizController::class, 'submit'])->name('participant.quizzes.submit');
    Route::get('/participant/quiz-attempts/{attempt}/result', [ParticipantQuizController::class, 'result'])->name('participant.quizzes.result');
});

if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}
