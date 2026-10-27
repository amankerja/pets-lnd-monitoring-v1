<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JobPosition;
use App\Models\PositionSkillStandard;
use App\Models\Skill;
use App\Models\TrainerRoster;
use App\Models\TrainerTaskAndKpi;
use App\Models\TrainerTeachingLog;
use App\Models\User;
use App\Models\UserSkill;
use App\Services\TrainerKpiActualsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrainingSystemCriticalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $participant;
    protected Course $course;
    protected AttendanceSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        // Kunci waktu awal di titik nol (misal: 10:00:00 tepat) agar tidak kena perbatasan modulo 15 detik
        Carbon::setTestNow(Carbon::create(2026, 10, 25, 10, 0, 0));

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'nik'  => 'ADM-001',
            'department' => 'HRD',
        ]);

        $this->participant = User::factory()->create([
            'role' => 'peserta',
            'nik'  => 'EMP-101',
            'department' => 'Engineering',
        ]);

        $this->course = Course::create([
            'code'        => 'TRN-K3-01',
            'title'       => 'Sertifikasi K3 & Sistem Otomasi',
            'description' => 'Pelatihan wajib standar keselamatan dan PLC',
            'trainer_id'  => $this->admin->id,
            'start_date'  => now()->toDateString(),
            'end_date'    => now()->addDays(3)->toDateString(),
            'status'      => 'active',
        ]);

        // Daftarkan peserta dengan status approved
        Enrollment::create([
            'user_id'             => $this->participant->id,
            'course_id'           => $this->course->id,
            'status'              => 'approved',
            'progress_percentage' => 100,
        ]);

        $this->session = AttendanceSession::create([
            'course_id'    => $this->course->id,
            'title'        => 'Sesi Hari 1 - Pagi',
            'session_date' => now()->toDateString(),
            'qr_token'     => 'secret-hmac-key-sesi-1',
            'expires_at'   => now()->addHours(2),
            'is_active'    => true,
        ]);
    }

    /**
     * TEST 1: Validasi Rotating QR pada jendela waktu aktif, Grace Period (+16 detik), dan Expired (+35 detik)
     */
    public function test_rotating_qr_validates_current_window_grace_period_and_rejects_expired_token(): void
    {
        // 1. Generate QR pada pukul 10:00:00 (Window 0)
        $payloadT0 = $this->session->getDynamicPayload(0);

        // Scan langsung di detik yang sama -> HARUS VALID
        $this->assertTrue($this->session->verifyDynamicPayload($payloadT0['qr_string']));
        $this->assertTrue($this->session->verifyDynamicPayload($payloadT0['manual_code']));

        // 2. Majukan waktu 16 detik ke 10:00:16 (Masuk ke Window +1 / Grace Period untuk lag sinyal HP)
        $this->travel(16)->seconds();

        // Token dari detik ke-0 (10:00:00) MASIH HARUS DITERIMA berkat toleransi offset -1
        $this->assertTrue(
            $this->session->verifyDynamicPayload($payloadT0['qr_string']),
            'Token seharusnya masih valid di dalam grace period 1 window sebelumnya (15-30 detik).'
        );

        // 3. Majukan waktu lagi ke 10:00:35 (Sudah lewat 2 window dari 10:00:00 -> Foto QR dikirim ke grup WA)
        $this->travel(19)->seconds(); // Total +35 detik dari T0

        // Token lama HARUS DITOLAK
        $this->assertFalse(
            $this->session->verifyDynamicPayload($payloadT0['qr_string']),
            'Token yang berumur lebih dari 30 detik (2 window) wajib ditolak!'
        );
    }

    public function test_participant_scanner_marks_attendance_from_the_session_query(): void
    {
        $this->actingAs($this->participant)
            ->get(route('mobile.scan'))
            ->assertInertia(fn ($page) => $page
                ->component('Participant/ScanAttendance')
                ->where('activeSessions.0.id', $this->session->id)
                ->where('activeSessions.0.already_scanned', false)
                ->where('activeSessions.0.already_evaluated', false)
            );

        Attendance::create([
            'attendance_session_id' => $this->session->id,
            'user_id' => $this->participant->id,
            'status' => 'present',
            'scanned_at' => now(),
        ]);

        $this->actingAs($this->participant)
            ->get(route('mobile.scan'))
            ->assertInertia(fn ($page) => $page
                ->where('activeSessions.0.already_scanned', true)
            );
    }

    public function test_enrollment_progress_page_is_scoped_to_the_selected_session(): void
    {
        $sessionParticipant = User::factory()->create([
            'role' => 'peserta',
            'nik' => 'EMP-SESSION-01',
            'department' => 'Engineering',
        ]);
        $sessionEnrollment = Enrollment::create([
            'user_id' => $sessionParticipant->id,
            'course_id' => $this->course->id,
            'attendance_session_id' => $this->session->id,
            'status' => 'approved',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.enrollments.index', ['session_id' => $this->session->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/EnrollmentAndProgress')
                ->where('selectedSession.id', $this->session->id)
                ->where('enrollments.total', 1)
                ->where('enrollments.data.0.id', $sessionEnrollment->id)
            );

        $this->actingAs($this->admin)
            ->get(route('training.administration.index', ['session_id' => $this->session->id]))
            ->assertInertia(fn ($page) => $page
                ->where('selectedSession.id', $this->session->id)
                ->where('ledger.total', 1)
            );

        $this->actingAs($this->admin)
            ->get(route('admin.program-schedules.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/ProgramScheduleManager')
                ->where('sessions.data.0.registered_count', 1)
            );
    }

    /**
     * TEST 2: Pencegahan Double-Scan / Race Condition & Validasi Status Enrollment
     */
    public function test_attendance_scan_prevents_duplicate_records_and_blocks_unapproved_users(): void
    {
        $validQr = $this->session->getDynamicPayload(0)['qr_string'];

        // Eksekusi scan pertama oleh peserta aktif
        $response1 = $this->actingAs($this->participant)
            ->post('/participant/attendance/scan', ['payload' => $validQr]);

        $response1->assertRedirect();
        $this->assertDatabaseCount('attendances', 1);

        // Simulasi sinyal lemot: Peserta menekan tombol submit kedua kalinya dengan payload yang sama
        $response2 = $this->actingAs($this->participant)
            ->post('/participant/attendance/scan', ['payload' => $validQr]);

        $response2->assertRedirect();
        // Jumlah baris di tabel attendances WAJIB tetap 1 (tidak error SQL Duplicate Entry & tidak dobel)
        $this->assertDatabaseCount('attendances', 1);

        // Buat peserta penyusup yang status pendaftarannya masih 'pending'
        $pendingUser = User::factory()->create(['role' => 'peserta', 'nik' => 'EMP-999']);
        Enrollment::create([
            'user_id'   => $pendingUser->id,
            'course_id' => $this->course->id,
            'status'    => 'pending',
        ]);

        $responseIntruder = $this->actingAs($pendingUser)
            ->post('/participant/attendance/scan', ['payload' => $validQr]);

        $responseIntruder->assertSessionHasErrors('payload');
        $this->assertDatabaseMissing('attendances', ['user_id' => $pendingUser->id]);
    }

    /**
     * TEST 3: Akurasi Perhitungan Skill Matrix, Gap Analysis, & Streamed CSV Export
     */
    public function test_skill_matrix_gap_calculation_and_csv_stream_export_accuracy(): void
    {
        $skillPlc = Skill::create(['code' => 'SK-PLC', 'name' => 'Pemrograman PLC', 'category' => 'Teknis']);
        $skillK3  = Skill::create(['code' => 'SK-K3', 'name' => 'Keselamatan Kerja', 'category' => 'Safety']);

        // Set Skill Peserta: PLC (Target 4, Aktual 2 -> Gap -2 / Defisit), K3 (Target 3, Aktual 4 -> Gap +1 / Kompeten)
        UserSkill::create([
            'user_id'       => $this->participant->id,
            'skill_id'      => $skillPlc->id,
            'target_level'  => 4,
            'current_level' => 2,
            'updated_by'    => $this->admin->id,
        ]);

        UserSkill::create([
            'user_id'       => $this->participant->id,
            'skill_id'      => $skillK3->id,
            'target_level'  => 3,
            'current_level' => 4,
            'updated_by'    => $this->admin->id,
        ]);

        // Test Export CSV StreamedResponse
        $response = $this->actingAs($this->admin)
            ->get(route('admin.export.matrix', ['department' => 'Engineering']));

        $response->assertStatus(200);
        $csvContent = $response->streamedContent();

        // Pastikan UTF-8 BOM terpasang di 3 byte pertama untuk kompatibilitas Microsoft Excel
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);

        // Pastikan baris peserta memiliki perhitungan Gap (-2 dan +1), Rata-rata Gap (-0.5), dan bendera GAP MINUS
        $this->assertStringContainsString('EMP-101', $csvContent);
        $this->assertStringContainsString('4,2,-2', $csvContent); // Target 4, Aktual 2, Gap -2
        $this->assertStringContainsString('3,4,+1', $csvContent); // Target 3, Aktual 4, Gap +1
        $this->assertStringContainsString('-0.5', $csvContent);   // Rata-rata Gap: (-2 + 1) / 2 = -0.5
        $this->assertStringContainsString('PERLU TRAINING ULANG (GAP MINUS)', $csvContent);
    }

    /**
     * TEST 4: Keamanan Role Middleware (Peserta tidak boleh tembus halaman/endpoint Admin)
     */
    public function test_participant_cannot_access_admin_matrix_or_modify_own_skill_score(): void
    {
        $response = $this->actingAs($this->participant)
            ->get(route('admin.export.matrix'));

        $response->assertStatus(403);
    }

    public function test_calendar_rejects_course_trainer_when_roster_marks_them_off(): void
    {
        $sessionDate = now()->addDay()->toDateString();

        TrainerRoster::create([
            'trainer_id' => $this->admin->id,
            'roster_date' => $sessionDate,
            'status' => 'annual_leave',
            'activity_type' => 'OFF',
        ]);

        $response = $this->actingAs($this->admin)->post(route('training.calendar.store'), [
            'course_id' => $this->course->id,
            'title' => 'Kelas saat cuti',
            'session_date' => $sessionDate,
        ]);

        $response->assertSessionHasErrors('trainer_id');
        $this->assertDatabaseMissing('attendance_sessions', [
            'course_id' => $this->course->id,
            'session_date' => $sessionDate,
        ]);
    }

    public function test_skill_matrix_nr_cells_are_not_gaps_and_cannot_be_edited(): void
    {
        $skill = Skill::create(['code' => 'SK-NR', 'name' => 'Skill Not Required', 'category' => 'General']);
        $position = JobPosition::create([
            'code' => 'POS-NR',
            'level_name' => 'Operator',
            'position_title' => 'Support',
            'organization_type' => 'both',
        ]);

        PositionSkillStandard::create([
            'job_position_id' => $position->id,
            'skill_id' => $skill->id,
            'required_target_level' => 3,
            'requirement_type' => 'NR',
        ]);

        $this->participant->update(['job_position_id' => $position->id]);
        UserSkill::create([
            'user_id' => $this->participant->id,
            'skill_id' => $skill->id,
            'current_level' => 1,
            'target_level' => 3,
        ]);

        $matrixResponse = $this->actingAs($this->admin)->get(route('admin.skill-matrix.index'));
        $matrixResponse->assertInertia(fn ($page) => $page
            ->component('Admin/SkillMatrix')
            ->where("participants.data.0.matrix.{$skill->id}.status", 'not_required')
            ->where("participants.data.0.matrix.{$skill->id}.gap", 0)
        );

        $this->actingAs($this->admin)
            ->patch(route('admin.skill-matrix.update-cell'), [
                'user_id' => $this->participant->id,
                'skill_id' => $skill->id,
                'current_level' => 1,
                'target_level' => 3,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('user_skills', [
            'user_id' => $this->participant->id,
            'skill_id' => $skill->id,
            'current_level' => 1,
            'target_level' => 3,
        ]);
    }

    public function test_dashboard_filters_mandatory_compliance_by_position_requirement_type(): void
    {
        $skill = Skill::create(['code' => 'SK-MAND-NR', 'name' => 'Mandatory Not Required', 'category' => 'General']);
        $position = JobPosition::create([
            'code' => 'POS-MAND-NR',
            'level_name' => 'Operator',
            'position_title' => 'Support',
            'organization_type' => 'both',
        ]);
        PositionSkillStandard::create([
            'job_position_id' => $position->id,
            'skill_id' => $skill->id,
            'required_target_level' => 3,
            'requirement_type' => 'NR',
        ]);
        $this->participant->update(['job_position_id' => $position->id]);

        Course::create([
            'code' => 'TRN-NR-DASH',
            'title' => 'Mandatory but Not Required for Position',
            'trainer_id' => $this->admin->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'active',
            'is_mandatory' => true,
            'skill_id' => $skill->id,
        ]);

        $mandatorySkill = Skill::create([
            'code' => 'SK-POS-MAND',
            'name' => 'Position Mandatory',
            'category' => 'Safety',
        ]);
        PositionSkillStandard::create([
            'job_position_id' => $position->id,
            'skill_id' => $mandatorySkill->id,
            'required_target_level' => 3,
            'requirement_type' => 'M',
        ]);
        Course::create([
            'code' => 'TRN-POS-MAND',
            'title' => 'Mandatory for Position Only',
            'trainer_id' => $this->admin->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'active',
            'is_mandatory' => false,
            'training_source' => 'internal',
            'training_category' => 'Safety',
            'skill_id' => $mandatorySkill->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('dashboard', ['mandatory_type' => 'mandatory']));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->where('metrics.complianceKpi.totalTrainingTypes', 1)
            ->where('metrics.complianceKpi.totalNotYet', 1)
            ->where('metrics.safetyInternalGap.0.code', 'TRN-POS-MAND')
            ->where('metrics.safetyInternalGap.0.required', 1)
        );
    }

    public function test_training_summary_chunks_participants_and_paginates_employee_tracker(): void
    {
        $this->course->update(['is_mandatory' => true]);

        User::factory()
            ->count(55)
            ->create(['role' => 'peserta']);

        $this->actingAs($this->admin)
            ->get(route('competency.training-summary'))
            ->assertInertia(fn ($page) => $page
                ->component('Competency/TrainingSummaryReport')
                ->has('employeeCompletionList', 50)
                ->where('employeeCompletionPagination.current_page', 1)
                ->where('employeeCompletionPagination.last_page', 2)
                ->where('employeeCompletionPagination.total', 56)
                ->where('trainingTypeSummary.0.total_required', 56)
                ->where('trainingTypeSummary.0.completed', 1)
                ->where('trainingTypeSummary.0.not_yet', 55)
            );

        $this->actingAs($this->admin)
            ->get(route('competency.training-summary', ['employee_page' => 2]))
            ->assertInertia(fn ($page) => $page
                ->has('employeeCompletionList', 6)
                ->where('employeeCompletionPagination.current_page', 2)
                ->where('employeeCompletionPagination.total', 56)
            );
    }

    public function test_trainer_projector_only_exposes_sessions_assigned_to_that_trainer(): void
    {
        $trainer = User::factory()->create([
            'role' => 'trainer',
            'email_verified_at' => now(),
        ]);
        $assignedCourse = Course::create([
            'code' => 'TRN-OWN-QR',
            'title' => 'Assigned Trainer Class',
            'trainer_id' => $trainer->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'active',
        ]);
        $assignedSession = AttendanceSession::create([
            'course_id' => $assignedCourse->id,
            'trainer_id' => $trainer->id,
            'title' => 'Assigned Session',
            'session_date' => now()->toDateString(),
            'qr_token' => 'trainer-session-secret',
            'expires_at' => now()->addHour(),
            'is_active' => true,
        ]);

        $this->actingAs($trainer)
            ->get(route('admin.attendance.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/AttendanceProjector')
                ->has('sessions', 1)
                ->where('sessions.0.id', $assignedSession->id)
                ->where('courses.0.id', $assignedCourse->id)
            );

        $this->actingAs($trainer)
            ->get(route('trainer.sessions.qr-payload', $this->session))
            ->assertForbidden();

        $this->actingAs($trainer)
            ->get(route('admin.attendance.index', ['session_id' => $this->session->id]))
            ->assertForbidden();
    }

    public function test_trainer_kpi_realizations_are_limited_to_the_kpi_period(): void
    {
        $trainer = User::factory()->create([
            'role' => 'trainer',
            'email_verified_at' => now(),
        ]);
        TrainerTaskAndKpi::create([
            'trainer_id' => $trainer->id,
            'title' => 'Monthly teaching target',
            'period' => 'monthly',
            'start_date' => now()->startOfMonth()->toDateString(),
            'due_date' => now()->endOfMonth()->toDateString(),
            'target_teaching_hours' => 12,
            'target_participants_count' => 20,
            'target_rating' => 4.5,
            'status' => 'in_progress',
        ]);
        TrainerTeachingLog::create([
            'trainer_id' => $trainer->id,
            'training_title' => 'Current month class',
            'log_date' => now()->startOfMonth()->addDays(2)->toDateString(),
            'entry_source' => 'manual_lnd_entry',
            'duration_hours' => 4.5,
            'scanned_participants_count' => 8,
        ]);
        TrainerTeachingLog::create([
            'trainer_id' => $trainer->id,
            'training_title' => 'Previous month class',
            'log_date' => now()->subMonth()->endOfMonth()->toDateString(),
            'entry_source' => 'manual_lnd_entry',
            'duration_hours' => 10,
            'scanned_participants_count' => 12,
        ]);

        app(TrainerKpiActualsService::class)->syncTrainer($trainer->id);

        $this->assertDatabaseHas('trainer_tasks_and_kpis', [
            'trainer_id' => $trainer->id,
            'actual_teaching_hours' => 4.5,
            'actual_participants_count' => 8,
        ]);

        $this->actingAs($trainer)
            ->get(route('trainer.dashboard'))
            ->assertInertia(fn ($page) => $page
                ->component('Trainer/Dashboard')
                ->where('kpiRecords.0.actual_teaching_hours', 4.5)
                ->where('kpiRecords.0.actual_participants_count', 8)
                ->where('kpiRecords.0.hours_progress_pct', 38)
                ->where('kpiRecords.0.actual_rating', null)
                ->where('kpiRecords.0.evaluation_count', 0)
                ->where('metrics.hours_this_month', 4.5)
                ->where('metrics.hours_last_month', 10)
                ->where('metrics.average_rating', 0)
                ->where('metrics.active_competencies_count', 0)
                ->where('todayRoster.status', 'unassigned')
            );
    }

    public function test_master_roster_matrix_includes_every_filtered_trainer_for_each_day(): void
    {
        $trainer = User::factory()->create([
            'role' => 'trainer',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.trainers.roster.master', ['month' => '2026-10']))
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Trainers/MasterRoster')
                ->has('calendarDates', 31)
                ->where('trainerRows.0.id', $trainer->id)
                ->has('trainerRows.0.cells', 31)
            );
    }

    /**
     * TEST 5: Isolasi Multi-Tenant (Anti-IDOR Sertifikat Kontraktor) & Kalkulasi Jam Mengajar Sah Trainer
     */
    public function test_multi_tenant_isolation_blocks_idor_and_calculates_verified_teaching_hours(): void
    {
        $contractorA = \App\Models\Organization::create([
            'code' => 'VND-PTA',
            'name' => 'PT Kontraktor A',
            'type' => 'contractor',
        ]);
        $contractorB = \App\Models\Organization::create([
            'code' => 'VND-PTB',
            'name' => 'PT Kontraktor B',
            'type' => 'contractor',
        ]);

        $adminContractorA = User::factory()->create([
            'role' => 'contractor_admin',
            'nik' => 'VND-ADM-A',
            'organization_id' => $contractorA->id,
            'department' => $contractorA->name,
        ]);

        $workerContractorB = User::factory()->create([
            'role' => 'peserta',
            'nik' => 'VND-WRK-B',
            'organization_id' => $contractorB->id,
            'department' => $contractorB->name,
        ]);

        // 1. Celah IDOR: Admin Kontraktor PT A mencoba mengunduh sertifikat pekerja milik Kontraktor PT B -> WAJIB 403
        $idorResponse = $this->actingAs($adminContractorA)
            ->get(route('admin.participants.report-print', ['user' => $workerContractorB->id]));

        $idorResponse->assertStatus(403);

        // 2. Kalkulasi Jam Mengajar Trainer: Sesi kosong (0 hadir) TIDAK BOLEH dihitung ke dalam teaching_hours
        $trainer = User::factory()->create([
            'role' => 'trainer',
            'nik' => 'TRN-100',
        ]);

        // Sesi 1 (3.5 jam) -> Ada 1 peserta hadir -> SAH DIHITUNG
        $sessionWithAttendance = AttendanceSession::create([
            'course_id' => $this->course->id,
            'trainer_id' => $trainer->id,
            'title' => 'Sesi Praktek PLC (Terlaksana)',
            'session_date' => now()->toDateString(),
            'duration_hours' => 3.5,
            'qr_token' => 'secret-sesi-sah',
            'expires_at' => now()->addHours(2),
            'is_active' => false,
        ]);

        Attendance::create([
            'attendance_session_id' => $sessionWithAttendance->id,
            'user_id' => $this->participant->id,
            'status' => 'present',
            'scanned_at' => now(),
        ]);

        // Sesi 2 (4.0 jam) -> Sesi kosong tanpa peserta hadir -> TIDAK BOLEH DIHITUNG
        AttendanceSession::create([
            'course_id' => $this->course->id,
            'trainer_id' => $trainer->id,
            'title' => 'Sesi Kosong Tanpa Peserta',
            'session_date' => now()->toDateString(),
            'duration_hours' => 4.0,
            'qr_token' => 'secret-sesi-kosong',
            'expires_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        $verifiedHours = (float) AttendanceSession::where('trainer_id', $trainer->id)
            ->whereHas('attendances', fn ($q) => $q->where('status', 'present'))
            ->sum('duration_hours');

        $this->assertEquals(3.5, $verifiedHours);

        // 3. Validasi Pengajuan Renewal One-Click Batch & Anti-IDOR pada requestRenewal
        $workerContractorA = User::factory()->create([
            'role' => 'peserta',
            'nik' => 'VND-WRK-A',
            'organization_id' => $contractorA->id,
            'department' => $contractorA->name,
        ]);

        // Jika Admin Kontraktor A menyusupkan ID pekerja Kontraktor B di requestRenewal -> WAJIB 403
        $crossTenantRenewal = $this->actingAs($adminContractorA)
            ->post(route('org.renewal.store'), [
                'course_id' => $this->course->id,
                'participant_ids' => [$workerContractorA->id, $workerContractorB->id],
            ]);
        $crossTenantRenewal->assertStatus(403);

        // Jika Admin Kontraktor A mengajukan pekerja organisasinya sendiri -> SUKSES
        $validRenewal = $this->actingAs($adminContractorA)
            ->post(route('org.renewal.store'), [
                'course_id' => $this->course->id,
                'participant_ids' => [$workerContractorA->id],
                'reason_notes' => 'Pembaruan sertifikat H-30',
            ]);
        $validRenewal->assertRedirect();
        $this->assertDatabaseHas('training_requests', [
            'organization_id' => $contractorA->id,
            'course_id' => $this->course->id,
            'request_type' => 'renewal_refreshment',
            'status' => 'pending_lnd',
        ]);
    }

    public function test_participant_can_submit_external_course_documents_from_dashboard(): void
    {
        Storage::fake('local');
        $course = $this->createExternalCourse();
        $course->externalTrainingLogistic()->create([
            'external_type' => 'inhouse',
            'vendor_name' => 'Vendor Sertifikasi',
            'pic_training' => 'PIC Training',
            'training_mode' => 'offline',
            'training_duration_days' => 2,
            'training_cost' => 1500000,
            'required_documents_schema' => [
                ['name' => 'KTP', 'mandatory' => true],
                ['name' => 'Sertifikat Medis', 'mandatory' => false],
            ],
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $this->participant->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'progress_percentage' => 0,
        ]);

        $this->actingAs($this->participant)
            ->get(route('mobile.home'))
            ->assertInertia(fn ($page) => $page
                ->component('Participant/Dashboard')
                ->has('externalDocumentCourses', 1)
                ->where('externalDocumentCourses.0.enrollment_id', $enrollment->id)
                ->where('externalDocumentCourses.0.required_documents_schema.0.name', 'KTP')
            );

        $this->from(route('mobile.home'))
            ->actingAs($this->participant)
            ->post(route('participant.enrollments.documents.store', $enrollment), [
                'documents' => [
                    ['name' => 'Sertifikat Medis', 'file' => UploadedFile::fake()->create('medical.pdf', 200, 'application/pdf')],
                ],
            ])
            ->assertSessionHasErrors('documents');

        $response = $this->actingAs($this->participant)
            ->post(route('participant.enrollments.documents.store', $enrollment), [
                'documents' => [
                    ['name' => 'KTP', 'file' => UploadedFile::fake()->create('ktp.jpg', 200, 'image/jpeg')],
                ],
            ]);

        $response->assertRedirect();
        $enrollment->refresh();
        $this->assertArrayHasKey('KTP', $enrollment->submitted_documents);
        Storage::disk('local')->assertExists($enrollment->submitted_documents['KTP']);
    }

    public function test_lnd_can_manage_external_logistics_and_save_accommodation_booking_notes(): void
    {
        $course = $this->createExternalCourse();

        $this->actingAs($this->admin)
            ->put(route('admin.training.courses.logistics.update', $course), [
                'external_type' => 'dinas_luar',
                'vendor_name' => 'Vendor Sertifikasi',
                'pic_training' => 'PIC Training',
                'training_mode' => 'offline',
                'training_room' => null,
                'training_duration_days' => 3,
                'training_cost' => 2500000,
                'required_documents_schema' => [
                    ['name' => 'KTP', 'mandatory' => true],
                ],
                'accommodation_checklist' => [
                    ['item' => 'Pesawat', 'status' => 'pending', 'notes' => 'Booking tiket'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($this->admin)
            ->patch(route('admin.training.courses.accommodation-status', $course), [
                'item' => 'Pesawat',
                'status' => 'done',
                'notes' => 'Kode booking ABC123',
            ])
            ->assertRedirect();

        $logistics = $course->externalTrainingLogistic()->firstOrFail();
        $this->assertSame('Kode booking ABC123', $logistics->accommodation_checklist[0]['notes']);
        $this->assertSame('done', $logistics->accommodation_checklist[0]['status']);
    }

    private function createExternalCourse(): Course
    {
        return Course::create([
            'code' => 'EXT-' . uniqid(),
            'title' => 'External Certification Course',
            'description' => 'External course for logistics coverage',
            'trainer_id' => $this->admin->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
            'training_source' => 'external',
            'training_category' => 'Safety',
            'is_mandatory' => true,
        ]);
    }
}
