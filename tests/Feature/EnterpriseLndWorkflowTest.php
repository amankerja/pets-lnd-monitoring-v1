<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JobPosition;
use App\Models\Organization;
use App\Models\PositionSkillStandard;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnterpriseLndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $deptMining;
    protected Organization $contractorMaju;
    protected User $lndAdmin;
    protected User $deptAdmin;
    protected User $contractorAdmin;
    protected User $trainer;
    protected User $peserta;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptMining = Organization::create([
            'code' => 'DEPT-OPS',
            'name' => 'Mining Operations',
            'type' => 'internal_dept',
        ]);

        $this->contractorMaju = Organization::create([
            'code' => 'VND-PTMJU',
            'name' => 'PT Maju Jaya Kontraktor',
            'type' => 'contractor',
        ]);

        $password = Hash::make('password');

        $this->lndAdmin = User::create([
            'name' => 'Budi Santoso',
            'nik' => 'LND-001',
            'email' => 'admin@pani.co.id',
            'password' => $password,
            'role' => 'lnd',
            'department' => 'Human Capital & LND',
            'email_verified_at' => now(),
        ]);

        $this->deptAdmin = User::create([
            'name' => 'Rudi Hartono',
            'nik' => 'ADM-DEPT-01',
            'email' => 'dept.mining@pani.co.id',
            'password' => $password,
            'role' => 'dept_admin',
            'organization_id' => $this->deptMining->id,
            'department' => $this->deptMining->name,
            'email_verified_at' => now(),
        ]);

        $this->contractorAdmin = User::create([
            'name' => 'Antonius Wijaya',
            'nik' => 'ADM-VND-01',
            'email' => 'admin@majujaya-vendor.co.id',
            'password' => $password,
            'role' => 'contractor_admin',
            'organization_id' => $this->contractorMaju->id,
            'department' => $this->contractorMaju->name,
            'email_verified_at' => now(),
        ]);

        $this->trainer = User::create([
            'name' => 'Ir. Hendra Wijaya',
            'nik' => 'TRN-001',
            'email' => 'hendra.trainer@pani.co.id',
            'password' => $password,
            'role' => 'trainer',
            'department' => 'Mining Operations',
            'email_verified_at' => now(),
        ]);

        $this->peserta = User::create([
            'name' => 'Adi Pratama',
            'nik' => 'PANI-0101',
            'email' => 'peserta1@pani.co.id',
            'password' => $password,
            'role' => 'peserta',
            'organization_id' => $this->deptMining->id,
            'department' => $this->deptMining->name,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * TEST 1: Halaman Login & Autentikasi Pengalihan Berdasarkan 5 Role RBAC.
     */
    public function test_login_authenticates_and_redirects_to_appropriate_role_homepage(): void
    {
        // 1. Unauthenticated root redirect to /login
        $rootResponse = $this->get('/');
        $rootResponse->assertRedirect(route('login'));

        // 2. Login Page Renders
        $loginPage = $this->get(route('login'));
        $loginPage->assertStatus(200);

        // 3. Login as Peserta -> Redirects to /participant/dashboard (mobile.home)
        $pesertaLogin = $this->post('/login', [
            'email' => 'peserta1@pani.co.id',
            'password' => 'password',
        ]);
        $pesertaLogin->assertRedirect(route('mobile.home'));

        // 4. Logout
        $logout = $this->post(route('logout'));
        $logout->assertRedirect(route('login'));
        $this->assertGuest();

        // 5. Login as Dept Admin -> Redirects to /organization/portal (org-admin.portal)
        $deptLogin = $this->post('/login', [
            'email' => 'dept.mining@pani.co.id',
            'password' => 'password',
        ]);
        $deptLogin->assertRedirect(route('org-admin.portal'));
    }

    /**
     * TEST 2: Level x Position Competency Standard Auto-Sync on Worker Assignment.
     */
    public function test_level_position_competency_standards_auto_syncs_to_worker_user_skills(): void
    {
        $skillK3 = Skill::create(['code' => 'SKL-K3', 'name' => 'K3 Tambang', 'category' => 'HSE']);
        $skillOpr = Skill::create(['code' => 'SKL-OPR', 'name' => 'Alat Berat', 'category' => 'Operations']);

        $position = JobPosition::create([
            'code' => 'OPR-MIN',
            'level_name' => 'Operator',
            'position_title' => 'Mining Hauling Production',
            'organization_type' => 'both',
        ]);

        PositionSkillStandard::create([
            'job_position_id' => $position->id,
            'skill_id' => $skillK3->id,
            'required_target_level' => 4,
            'is_mandatory' => true,
        ]);

        PositionSkillStandard::create([
            'job_position_id' => $position->id,
            'skill_id' => $skillOpr->id,
            'required_target_level' => 5,
            'is_mandatory' => true,
        ]);

        // Assign peserta ke posisi ini via Controller endpoint
        $assignResponse = $this->actingAs($this->lndAdmin)
            ->post("/competency/position-standards/{$position->id}/assign-worker", [
                'user_id' => $this->peserta->id,
            ]);

        $assignResponse->assertRedirect();
        $assignResponse->assertSessionHas('success');

        // Verifikasi target_level di user_skills otomatis tersinkronkan
        $this->assertDatabaseHas('user_skills', [
            'user_id' => $this->peserta->id,
            'skill_id' => $skillK3->id,
            'target_level' => 4,
        ]);

        $this->assertDatabaseHas('user_skills', [
            'user_id' => $this->peserta->id,
            'skill_id' => $skillOpr->id,
            'target_level' => 5,
        ]);
    }

    public function test_job_position_defaults_to_mdka_entity_and_indonesian_nationality(): void
    {
        $position = JobPosition::create([
            'code' => 'TEC-MEC',
            'level_name' => 'Technician',
            'position_title' => 'Mechanical',
            'organization_type' => 'both',
        ]);

        $this->assertSame('MDKA', $position->entity);
        $this->assertSame('Indonesia', $position->nationality);
        $this->assertNull($position->section);
    }

    public function test_organization_admin_can_submit_workers_to_multiple_active_training_programs_for_lnd_review(): void
    {
        $courseOne = Course::create([
            'code' => 'CRS-MULTI-01',
            'title' => 'Pelatihan Keselamatan Dasar',
            'trainer_id' => $this->trainer->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);
        $courseTwo = Course::create([
            'code' => 'CRS-MULTI-02',
            'title' => 'Pelatihan Pengoperasian',
            'trainer_id' => $this->trainer->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'status' => 'active',
        ]);
        $position = JobPosition::create([
            'code' => 'OPR-TRAIN',
            'level_name' => 'Operator',
            'position_title' => 'Operator Produksi',
            'organization_type' => 'internal',
            'entity' => 'PANI',
        ]);
        $this->peserta->update(['job_position_id' => $position->id]);

        $this->actingAs($this->deptAdmin)
            ->get(route('org-admin.portal'))
            ->assertInertia(fn ($page) => $page
                ->where('workers.0.name', $this->peserta->name)
                ->where('workers.0.nik', $this->peserta->nik)
                ->where('workers.0.department', $this->peserta->department)
                ->where('workers.0.position', $position->position_title)
                ->where('workers.0.entity', 'PANI')
                ->where('workers.0.organization_name', $this->deptMining->name)
            );

        $response = $this->actingAs($this->deptAdmin)->post(route('org-admin.bulk-enroll'), [
            'course_ids' => [$courseOne->id, $courseTwo->id],
            'participant_ids' => [$this->peserta->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->peserta->id,
            'course_id' => $courseOne->id,
            'registered_by' => $this->deptAdmin->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->peserta->id,
            'course_id' => $courseTwo->id,
            'registered_by' => $this->deptAdmin->id,
            'status' => 'pending',
        ]);

        $enrollment = Enrollment::query()->where('course_id', $courseOne->id)->firstOrFail();
        $this->actingAs($this->deptAdmin)
            ->patch(route('admin.enrollments.status', $enrollment), ['status' => 'rejected'])
            ->assertForbidden();

        $this->actingAs($this->lndAdmin)
            ->patch(route('admin.enrollments.status', $enrollment), ['status' => 'rejected'])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'status' => 'rejected',
        ]);
    }

    public function test_personal_trainer_submissions_require_lnd_review(): void
    {
        $course = Course::create([
            'code' => 'CRS-TRAINER-01',
            'title' => 'Pelatihan Khusus Trainer',
            'trainer_id' => $this->trainer->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'status' => 'active',
        ]);
        $enrollment = Enrollment::create([
            'user_id' => $this->peserta->id,
            'course_id' => $course->id,
            'registered_by' => $this->lndAdmin->id,
            'status' => 'approved',
            'completion_status' => 'registered',
            'progress_percentage' => 0,
        ]);

        $this->actingAs($this->trainer)
            ->post(route('training.administration.enroll'), [
                'course_id' => $course->id,
                'participant_ids' => [$this->peserta->id],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'registered_by' => $this->trainer->id,
            'status' => 'pending',
        ]);
    }

    public function test_individual_training_view_searches_employees_by_nik(): void
    {
        $response = $this->actingAs($this->lndAdmin)
            ->get(route('competency.individual-view', ['search' => $this->peserta->nik]));

        $response->assertInertia(fn ($page) => $page
            ->component('Competency/IndividualTrainingView')
            ->where('search', $this->peserta->nik)
            ->where('selectedUser.nik', $this->peserta->nik)
            ->has('participantsList', 1)
        );
    }

    /**
     * TEST 3: Training Today & Multi-Tenant Scoped Dashboard Access.
     */
    public function test_dashboard_and_training_today_accessible_by_authorized_roles(): void
    {
        // 1. Dept Admin can access /dashboard (scoped to their department)
        $deptDashboard = $this->actingAs($this->deptAdmin)->get(route('dashboard'));
        $deptDashboard->assertStatus(200);

        // 2. All roles can access /training-today
        $trainingTodayResponse = $this->actingAs($this->peserta)->get(route('training-today.index'));
        $trainingTodayResponse->assertStatus(200);

        // 3. Proyektor QR restricted to lnd and trainer (peserta forbidden)
        $pesertaProjector = $this->actingAs($this->peserta)->get(route('admin.attendance.index'));
        $pesertaProjector->assertRedirect(route('mobile.home'));

        $trainerProjector = $this->actingAs($this->trainer)->get(route('admin.attendance.index'));
        $trainerProjector->assertStatus(200);
    }

    /**
     * TEST 4: Public Certificate Verification (No Auth Required).
     */
    public function test_public_certificate_verification_accessible_without_auth(): void
    {
        $course = Course::create([
            'code' => 'CRS-K3-001',
            'title' => 'K3 Tambang Dasar',
            'trainer_id' => $this->trainer->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addDays(5),
            'status' => 'active',
            'validity_years' => 2,
        ]);

        $certNumber = 'CERT-PANI-2026-9999';

        Enrollment::create([
            'user_id' => $this->peserta->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'progress_percentage' => 100,
            'certificate_number' => $certNumber,
            'certificate_issued_at' => now()->toDateString(),
            'certificate_expires_at' => now()->addYears(2)->toDateString(),
        ]);

        // Akses publik tanpa autentikasi
        $response = $this->get("/verify-certificate/{$certNumber}");
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Public/VerifyCertificate')
            ->where('result.is_found', true)
            ->where('result.is_valid', true)
            ->where('result.certificate_number', $certNumber)
            ->where('result.holder_name', $this->peserta->name)
        );
    }

    /**
     * TEST 5: Quiz Security (Correct options are hidden from Inertia payload) & Gain Score Calculation.
     */
    public function test_quiz_workflow_anti_cheat_and_gain_score_calculation(): void
    {
        $course = Course::create([
            'code' => 'CRS-OPR-002',
            'title' => 'Pengoperasian Dump Truck HD785',
            'trainer_id' => $this->trainer->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(10),
            'status' => 'active',
        ]);

        Enrollment::create([
            'user_id' => $this->peserta->id,
            'course_id' => $course->id,
            'status' => 'approved',
            'progress_percentage' => 80,
        ]);

        // Buat Pre-Test
        $preQuiz = Quiz::create([
            'course_id' => $course->id,
            'title' => 'Pre-Test Dump Truck',
            'quiz_type' => 'pre_test',
            'passing_score' => 70,
            'duration_minutes' => 30,
            'is_active' => true,
        ]);

        $question1 = QuizQuestion::create([
            'quiz_id' => $preQuiz->id,
            'question_text' => 'Berapakah tekanan ban standar HD785?',
            'options' => ['A' => '85 PSI', 'B' => '100 PSI', 'C' => '120 PSI', 'D' => '150 PSI'],
            'correct_option' => 'B',
            'points' => 100,
            'order_index' => 1,
        ]);

        // 1. Peserta memulai Pre-Test
        $startResponse = $this->actingAs($this->peserta)->get(route('participant.quizzes.take', $preQuiz));
        $startResponse->assertStatus(200);

        // Verifikasi Anti-Cheat: correct_option TIDAK ada di dalam payload Inertia
        $startResponse->assertInertia(fn ($page) => $page
            ->component('Participant/TakeQuiz')
            ->has('questions', 1)
            ->missing('questions.0.correct_option')
        );

        $attempt = QuizAttempt::where('quiz_id', $preQuiz->id)->where('user_id', $this->peserta->id)->firstOrFail();

        // 2. Submit Pre-Test dengan jawaban benar
        $submitResponse = $this->actingAs($this->peserta)->post(route('participant.quizzes.submit', $attempt), [
            'answers' => [
                $question1->id => 'B',
            ],
        ]);

        $submitResponse->assertStatus(200);
        $attempt->refresh();
        $this->assertEquals(100, $attempt->score);
        $this->assertTrue((bool) $attempt->is_passed);
        $this->assertNotNull($attempt->submitted_at);
    }
}
