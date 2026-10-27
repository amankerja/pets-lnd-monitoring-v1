<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\JobPosition;
use App\Models\ModuleProgress;
use App\Models\Organization;
use App\Models\PositionSkillStandard;
use App\Models\Skill;
use App\Models\TrainerCompetency;
use App\Models\TrainingRequest;
use App\Models\User;
use App\Models\UserSkill;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $password = Hash::make('password');

            // 0. Buat Organisasi (Departemen Internal & Perusahaan Kontraktor)
            $orgMining = Organization::create([
                'code' => 'DEPT-OPS',
                'name' => 'Mining Operations',
                'type' => 'internal_dept',
                'site' => 'Site Pani Project (PETS/GSM)',
            ]);

            $orgPlant = Organization::create([
                'code' => 'DEPT-MNT',
                'name' => 'Plant & Maintenance',
                'type' => 'internal_dept',
                'site' => 'Site Pani Project (PETS/GSM)',
            ]);

            $orgContractor = Organization::create([
                'code' => 'VND-PTMJU',
                'name' => 'PT Maju Jaya Kontraktor',
                'type' => 'contractor',
                'site' => 'PT Gorontalo Sejahtera Mining',
            ]);

            // 1. Buat Super Admin Tim LnD (`lnd`)
            $admin = User::create([
                'name' => 'Budi Santoso (Super Admin LnD)',
                'nik' => 'EMP-0001',
                'email' => 'admin@pani.co.id',
                'password' => $password,
                'role' => 'lnd',
                'department' => 'Human Capital & LND',
                'site' => 'Site Pani Project (PETS/GSM)',
                'job_level' => 'Manager',
                'email_verified_at' => now(),
            ]);

            // 1B. Buat Admin Departemen Internal (`dept_admin`) & Admin Perusahaan Kontraktor (`contractor_admin`)
            $deptAdmin = User::create([
                'name' => 'Rudi Hartono (Admin Dept. Mining)',
                'nik' => 'ADM-DEPT-01',
                'email' => 'dept.mining@pani.co.id',
                'password' => $password,
                'role' => 'dept_admin',
                'organization_id' => $orgMining->id,
                'department' => $orgMining->name,
                'site' => 'Site Pani Project (PETS/GSM)',
                'job_level' => 'Supervisor',
                'email_verified_at' => now(),
            ]);

            $contractorAdmin = User::create([
                'name' => 'Antonius Wijaya (Admin PT Maju Jaya)',
                'nik' => 'ADM-VND-01',
                'email' => 'admin@majujaya-vendor.co.id',
                'password' => $password,
                'role' => 'contractor_admin',
                'organization_id' => $orgContractor->id,
                'department' => $orgContractor->name,
                'site' => 'PT Gorontalo Sejahtera Mining',
                'job_level' => 'Supervisor',
                'email_verified_at' => now(),
            ]);

            // 2. Buat 2 Trainer
            $trainer1 = User::create([
                'name' => 'Ir. Hendra Wijaya, M.T.',
                'nik' => 'TRN-0001',
                'email' => 'hendra.trainer@pani.co.id',
                'password' => $password,
                'role' => 'trainer',
                'department' => 'Mining Operations',
                'site' => 'Site Pani Project (PETS/GSM)',
                'job_level' => 'Superintendent',
                'email_verified_at' => now(),
            ]);

            $trainer2 = User::create([
                'name' => 'Dr. Siti Rahmawati',
                'nik' => 'TRN-0002',
                'email' => 'siti.trainer@pani.co.id',
                'password' => $password,
                'role' => 'trainer',
                'department' => 'HSE & Sustainability',
                'site' => 'PT Gorontalo Sejahtera Mining',
                'job_level' => 'Supervisor',
                'email_verified_at' => now(),
            ]);

            // 3. Buat 25 Peserta di 3 Organisasi Berbeda (2 Departemen Internal + 1 Perusahaan Kontraktor)
            $organizationsList = [
                $orgMining,     // 9 peserta internal
                $orgPlant,      // 8 peserta internal
                $orgContractor, // 8 pekerja kontraktor
            ];

            $siteOptions = [
                'PT Gorontalo Sejahtera Mining',
                'PT Elabram Systems',
                'Site Pani Project (PETS/GSM)',
                'Site Wetar (BKP)',
            ];
            $jobLevelOptions = [
                'Non-Staff',
                'Staff',
                'Supervisor',
                'Superintendent',
                'Manager',
            ];

            $pesertaNames = [
                'Adi Pratama', 'Bagus Setiawan', 'Candra Wijaya', 'Dedi Kurniawan', 'Eko Saputra',
                'Fajar Nugroho', 'Gilang Ramadhan', 'Hadi Sucipto', 'Indra Permana', 'Joko Susilo',
                'Kiki Amelia', 'Lukman Hakim', 'M. Rizky Pratama', 'Nanda Putri', 'Oki Setiana',
                'Putra Mahendra', 'Qori Sandioriva', 'Rendy Septian', 'Surya Dinata', 'Taufik Hidayat',
                'Umar Bakri', 'Vino Bastian', 'Wahyu Hidayat', 'Yoga Pratama', 'Zaki Zimah',
            ];

            $participants = collect();

            foreach ($pesertaNames as $index => $name) {
                $num = $index + 1;
                $orgIndex = $index < 9 ? 0 : ($index < 17 ? 1 : 2);
                $org = $organizationsList[$orgIndex];
                $site = $siteOptions[$index % count($siteOptions)];
                $jobLevel = $jobLevelOptions[$index % count($jobLevelOptions)];

                $participants->push(User::create([
                    'name' => $name,
                    'nik' => sprintf('PANI-%04d', $num + 100),
                    'email' => sprintf('peserta%d@pani.co.id', $num),
                    'password' => $password,
                    'role' => 'peserta',
                    'organization_id' => $org->id,
                    'department' => $org->name,
                    'site' => $site,
                    'job_level' => $jobLevel,
                    'email_verified_at' => now(),
                ]));
            }

            // 4. Buat Master Kompetensi (Skills) dengan Kategori & Jenis Baku
            $skillsData = [
                [
                    'code' => 'SKL-HSE-01',
                    'name' => 'K3 Pertambangan & Tanggap Darurat',
                    'category' => 'Safety',
                    'competency_type' => 'EXTERNAL_MANDATORY',
                    'description' => 'Standar kepatuhan K3 pertambangan mineral & batubara sesuai regulasi Kepmen ESDM No. 1827 K/30/MEM/2018 dan prosedur tanggap darurat.',
                ],
                [
                    'code' => 'SKL-HSE-02',
                    'name' => 'Hazard Identification & Risk Assessment (HIRA)',
                    'category' => 'Safety',
                    'competency_type' => 'INTERNAL_MANDATORY',
                    'description' => 'Metodologi identifikasi bahaya tempat kerja, analisis risiko residual, dan penyusunan Job Safety Analysis (JSA) terstandar.',
                ],
                [
                    'code' => 'SKL-OPS-01',
                    'name' => 'Pengoperasian Alat Berat & Hauling',
                    'category' => 'Operation',
                    'competency_type' => 'INTERNAL_MANDATORY',
                    'description' => 'Keahlian mengemudikan dan mengoperasikan unit dump truck, excavator, dan loader pada medan tambang terbuka secara aman dan efisien.',
                ],
                [
                    'code' => 'SKL-OPS-02',
                    'name' => 'Mine Dispatch & Fleet Management',
                    'category' => 'Operation',
                    'competency_type' => 'INTERNAL_MANDATORY',
                    'description' => 'Optimalisasi siklus hauling, alokasi armada tambang (fleet dispatching), dan pengendalian rasio keterisian unit produksi.',
                ],
                [
                    'code' => 'SKL-MNT-01',
                    'name' => 'Preventive Maintenance Hidrolik',
                    'category' => 'Plant',
                    'competency_type' => 'INTERNAL_MANDATORY',
                    'description' => 'Prosedur pemeliharaan preventif, pengecekan tekanan sirkuit pompa hidrolik, dan overhaul sistem mekanikal unit pabrik/alat berat.',
                ],
                [
                    'code' => 'SKL-LDR-01',
                    'name' => 'Supervisory & Field Leadership',
                    'category' => 'Leadership',
                    'competency_type' => 'NON_MANDATORY',
                    'description' => 'Kepemimpinan lapangan, komunikasi keselamatan (Safety Talk), pembinaan tim kerja, dan resolusi konflik operasional tingkat pengawas.',
                ],
            ];

            $skills = collect($skillsData)->map(fn (array $data) => Skill::create($data));

            // 4B. Buat Master "Level x Position" (`job_positions`) & Standar Kompetensi Jabatan (`position_skill_standards`)
            $posMiningOpr = JobPosition::create([
                'code' => 'OPR-MIN',
                'level_name' => 'Operator',
                'position_title' => 'Heavy Equipment & Hauling Production',
                'organization_type' => 'both',
            ]);
            $posPlantTech = JobPosition::create([
                'code' => 'TCH-MNT',
                'level_name' => 'Technician',
                'position_title' => 'Hydraulic & Heavy Plant Maintenance',
                'organization_type' => 'both',
            ]);
            $posHseSup = JobPosition::create([
                'code' => 'SUP-HSE',
                'level_name' => 'Supervisor',
                'position_title' => 'HSE & Mine Safety Compliance (POP)',
                'organization_type' => 'both',
            ]);

            foreach ([
                [$posMiningOpr->id, $skills[0]->id, 4, true],
                [$posMiningOpr->id, $skills[2]->id, 4, true],
                [$posMiningOpr->id, $skills[3]->id, 3, true],
                [$posPlantTech->id, $skills[0]->id, 4, true],
                [$posPlantTech->id, $skills[4]->id, 5, true],
                [$posHseSup->id, $skills[0]->id, 5, true],
                [$posHseSup->id, $skills[1]->id, 5, true],
                [$posHseSup->id, $skills[5]->id, 4, true],
            ] as [$posId, $skId, $reqLvl, $isMand]) {
                PositionSkillStandard::create([
                    'job_position_id' => $posId,
                    'skill_id' => $skId,
                    'required_target_level' => $reqLvl,
                    'is_mandatory' => $isMand,
                ]);
            }

            foreach ($participants as $pIdx => $peserta) {
                $assignedPos = $pIdx < 9 ? $posMiningOpr : ($pIdx < 17 ? $posPlantTech : $posHseSup);
                $peserta->updateQuietly(['job_position_id' => $assignedPos->id]);
            }

            // 5. Buat Katalog Course Aktif dengan Variasi Lengkap Atribut Klasifikasi Training & Siklus Masa Berlaku
            $course1 = Course::create([
                'code' => 'CRS-2025-001',
                'title' => 'Sertifikasi Keselamatan Kerja Tambang & HIRA (POP)',
                'description' => 'Pelatihan wajib pengawas operasional pertama terkait identifikasi bahaya, investigasi insiden, dan kepatuhan standar K3 pertambangan.',
                'trainer_id' => $trainer2->id,
                'start_date' => Carbon::now()->subDays(10)->toDateString(),
                'end_date' => Carbon::now()->addDays(14)->toDateString(),
                'status' => 'active',
                'is_mandatory' => true,
                'training_source' => 'external',
                'training_category' => 'Safety',
                'validity_years' => 2,
                'validity_months' => 24,
                'skill_id' => $skills[0]->id,
                'granted_skill_level' => 4,
            ]);

            $course1ModulesData = [
                'Regulasi K3 Pertambangan & SMKP',
                'Identifikasi Bahaya & Penilaian Risiko (HIRA)',
                'Investigasi Kecelakaan Kerja & Root Cause Analysis',
                'Inspeksi Keselamatan Terencana & Job Safety Analysis (JSA)',
            ];

            $course1Modules = collect($course1ModulesData)->map(function (string $title, int $idx) use ($course1) {
                return CourseModule::create([
                    'course_id' => $course1->id,
                    'title' => $title,
                    'order_index' => $idx + 1,
                ]);
            });

            $course2 = Course::create([
                'code' => 'CRS-2025-002',
                'title' => 'Optimalisasi Fleet Operations & Preventive Maintenance',
                'description' => 'Program peningkatan efisiensi siklus alat berat, penghematan bahan bakar, dan pemeliharaan preventif sistem hidrolik.',
                'trainer_id' => $trainer1->id,
                'start_date' => Carbon::now()->subDays(7)->toDateString(),
                'end_date' => Carbon::now()->addDays(21)->toDateString(),
                'status' => 'active',
                'is_mandatory' => true,
                'training_source' => 'internal',
                'training_category' => 'Operation',
                'validity_years' => 2,
                'validity_months' => 24,
                'skill_id' => $skills[2]->id,
                'granted_skill_level' => 4,
            ]);

            // Tambahan variasi Course untuk mencakup kategori Plant, Pengawas, Engineering, Leadership, & General (Mandatory & Non-Mandatory, Internal & External)
            Course::create([
                'code' => 'CRS-2025-003',
                'title' => 'Troubleshooting Sistem Hidrolik & Overhaul Heavy Plant',
                'description' => 'Pelatihan teknis pemeliharaan unit plant dan diagnostik tekanan hidrolik ekskavator.',
                'trainer_id' => $trainer1->id,
                'start_date' => Carbon::now()->subDays(3)->toDateString(),
                'end_date' => Carbon::now()->addDays(25)->toDateString(),
                'status' => 'active',
                'is_mandatory' => true,
                'training_source' => 'internal',
                'training_category' => 'Plant',
                'validity_years' => 3,
                'validity_months' => 36,
                'skill_id' => $skills[4]->id,
                'granted_skill_level' => 5,
            ]);

            Course::create([
                'code' => 'CRS-2025-004',
                'title' => 'Sertifikasi Kompetensi Pengawas Operasional Madya (POM) BNSP',
                'description' => 'Sertifikasi eksternal BNSP bagi jajaran supervisor dan superintendent tambang.',
                'trainer_id' => $trainer2->id,
                'start_date' => Carbon::now()->addDays(2)->toDateString(),
                'end_date' => Carbon::now()->addDays(30)->toDateString(),
                'status' => 'active',
                'is_mandatory' => true,
                'training_source' => 'external',
                'training_category' => 'Pengawas',
                'validity_years' => 3,
                'validity_months' => 36,
                'skill_id' => $skills[1]->id,
                'granted_skill_level' => 5,
            ]);

            Course::create([
                'code' => 'CRS-2025-005',
                'title' => 'Geotechnical Slope Stability & Mine Engineering Design',
                'description' => 'Pelatihan desain geoteknik lereng tambang dan analisis kestabilan pit.',
                'trainer_id' => $trainer1->id,
                'start_date' => Carbon::now()->addDays(5)->toDateString(),
                'end_date' => Carbon::now()->addDays(35)->toDateString(),
                'status' => 'active',
                'is_mandatory' => false,
                'training_source' => 'external',
                'training_category' => 'Engineering',
                'validity_years' => 2,
                'validity_months' => 12,
                'skill_id' => $skills[3]->id,
                'granted_skill_level' => 4,
            ]);

            Course::create([
                'code' => 'CRS-2025-006',
                'title' => 'Supervisory Coaching & Effective Field Leadership',
                'description' => 'Pengembangan kepemimpinan lapangan non-mandatory bagi calon group leader.',
                'trainer_id' => $trainer2->id,
                'start_date' => Carbon::now()->addDays(7)->toDateString(),
                'end_date' => Carbon::now()->addDays(40)->toDateString(),
                'status' => 'active',
                'is_mandatory' => false,
                'training_source' => 'internal',
                'training_category' => 'Leadership',
                'validity_years' => 2,
                'validity_months' => null, // Berlaku Selamanya / Permanent
                'skill_id' => $skills[5]->id,
                'granted_skill_level' => 4,
            ]);

            $course2ModulesData = [
                'Fundamental Fleet Management & Cycle Time Optimization',
                'Teknik Eco-Operation & Efisiensi Bahan Bakar',
                'Troubleshooting Sistem Hidrolik & Preventive Check',
                'Pelaporan Digital & Analitik Downtime Unit',
            ];

            $course2Modules = collect($course2ModulesData)->map(function (string $title, int $idx) use ($course2) {
                return CourseModule::create([
                    'course_id' => $course2->id,
                    'title' => $title,
                    'order_index' => $idx + 1,
                ]);
            });

            Course::query()->with('modules')->get()->each(function (Course $course) {
                for ($moduleOrder = 1; $moduleOrder <= 4; $moduleOrder++) {
                    $course->modules()->firstOrCreate(
                        ['order_index' => $moduleOrder],
                        ['title' => "Pertemuan {$moduleOrder}"]
                    );
                }
            });

            // 6. Buat Attendance Sessions untuk kedua Course (Lengkap dengan trainer_id & duration_hours)
            $c1Sessions = collect([
                [
                    'trainer_id' => $trainer2->id,
                    'title' => 'Sesi 1: Kick-off & Regulasi SMKP',
                    'session_date' => Carbon::now()->subDays(7)->toDateString(),
                    'duration_hours' => 3.5,
                    'qr_token' => 'QR-C1-S1-' . Str::upper(Str::random(16)),
                    'expires_at' => Carbon::now()->subDays(7)->endOfDay(),
                    'is_active' => false,
                ],
                [
                    'trainer_id' => $trainer2->id,
                    'title' => 'Sesi 2: Workshop HIRA & JSA Lapangan',
                    'session_date' => Carbon::now()->toDateString(),
                    'duration_hours' => 4.0,
                    'qr_token' => 'QR-C1-S2-ACTIVE2025',
                    'expires_at' => Carbon::now()->addHours(8),
                    'is_active' => true,
                ],
            ])->map(fn ($s) => AttendanceSession::create(array_merge($s, ['course_id' => $course1->id])));

            $c2Sessions = collect([
                [
                    'trainer_id' => $trainer1->id,
                    'title' => 'Sesi 1: Analisa Cycle Time & Hauling',
                    'session_date' => Carbon::now()->subDays(4)->toDateString(),
                    'duration_hours' => 3.0,
                    'qr_token' => 'QR-C2-S1-' . Str::upper(Str::random(16)),
                    'expires_at' => Carbon::now()->subDays(4)->endOfDay(),
                    'is_active' => false,
                ],
                [
                    'trainer_id' => $trainer1->id,
                    'title' => 'Sesi 2: Praktek Preventive Hidrolik',
                    'session_date' => Carbon::now()->toDateString(),
                    'duration_hours' => 4.5,
                    'qr_token' => 'QR-C2-S2-ACTIVE2025',
                    'expires_at' => Carbon::now()->addHours(8),
                    'is_active' => true,
                ],
            ])->map(fn ($s) => AttendanceSession::create(array_merge($s, ['course_id' => $course2->id])));

            // 6B. Buat Kompetensi Mengajar Trainer (`trainer_competencies`) & Contoh Pengajuan TNA (`training_requests`)
            TrainerCompetency::create([
                'trainer_id' => $trainer2->id,
                'skill_id' => $skills[0]->id,
                'certification_no' => 'BNSP-K3-POP-2024-881',
                'valid_until' => Carbon::now()->addMonths(18)->toDateString(),
            ]);
            TrainerCompetency::create([
                'trainer_id' => $trainer1->id,
                'skill_id' => $skills[2]->id,
                'certification_no' => 'BNSP-OPS-HD-2024-109',
                'valid_until' => Carbon::now()->addDays(45)->toDateString(),
            ]);

            TrainingRequest::create([
                'organization_id' => $orgContractor->id,
                'requested_by' => $contractorAdmin->id,
                'skill_id' => $skills[0]->id,
                'course_id' => $course1->id,
                'request_type' => 'renewal_refreshment',
                'target_participants_count' => 3,
                'participant_ids' => $participants->slice(17, 3)->pluck('id')->values()->all(),
                'reason_notes' => 'Perpanjangan sertifikat K3 POP pekerja PT Maju Jaya Kontraktor yang akan habis dalam < 45 hari.',
                'status' => 'pending_lnd',
            ]);

            // 7. Buat Enrollments, Module Progress, Attendances, serta Contoh Sertifikat Aktif, H-60, Expired, & Superseded
            foreach ($participants->take(20) as $idx => $peserta) {
                $status = $idx === 19 ? 'pending' : 'approved';
                $completedCount = $status === 'approved' ? ($idx < 10 ? 4 : ($idx % 5)) : 0;
                $progressPct = (int) round(($completedCount / $course1Modules->count()) * 100);
                $isPassed = $progressPct === 100;

                // Variasi masa berlaku sertifikat untuk demonstrasi Buku Induk & Skill Matrix:
                // idx 0..5 -> VALID (> H-60)
                // idx 6..7 -> WARNING H-60 (Sisa 30 hari)
                // idx 8..9 -> EXPIRED (Lewat 20 hari lalu)
                $issuedDate = null;
                $expiresDate = null;
                $certNo = null;
                $enrType = 'regular';

                if ($isPassed) {
                    if ($idx === 0) {
                        // Buat 1 baris sertifikat lama (Tahun 2023) yang sudah SUPERSEDED oleh sertifikat Refreshment baru!
                        Enrollment::create([
                            'user_id' => $peserta->id,
                            'course_id' => $course1->id,
                            'registered_by' => $lndAdmin->id,
                            'enrollment_type' => 'historical_migration',
                            'status' => 'approved',
                            'completion_status' => 'passed',
                            'progress_percentage' => 100,
                            'completed_at' => Carbon::now()->subMonths(26)->toDateString(),
                            'certificate_number' => sprintf('CERT/%s/2023/08/%04d', $course1->code, 1),
                            'certificate_issued_at' => Carbon::now()->subMonths(26)->toDateString(),
                            'certificate_expires_at' => Carbon::now()->subMonths(2)->toDateString(),
                            'is_superseded' => true,
                        ]);
                        $enrType = 'refreshment';
                    }

                    if ($idx <= 5) {
                        $issuedDate = Carbon::now()->subMonths(4)->toDateString();
                        $expiresDate = Carbon::now()->addMonths(20)->toDateString();
                    } elseif ($idx <= 7) {
                        $issuedDate = Carbon::now()->subMonths(23)->toDateString();
                        $expiresDate = Carbon::now()->addDays(32)->toDateString();
                    } else {
                        $issuedDate = Carbon::now()->subMonths(25)->toDateString();
                        $expiresDate = Carbon::now()->subDays(18)->toDateString();
                    }

                    $certNo = sprintf('CERT/%s/%s/%s/%04d', $course1->code, Carbon::parse($issuedDate)->format('Y'), Carbon::parse($issuedDate)->format('m'), $idx + 1);
                }

                $enrollment = Enrollment::create([
                    'user_id' => $peserta->id,
                    'course_id' => $course1->id,
                    'registered_by' => $lndAdmin->id,
                    'enrollment_type' => $enrType,
                    'status' => $status,
                    'completion_status' => $isPassed ? 'passed' : ($progressPct > 0 ? 'ongoing' : 'registered'),
                    'progress_percentage' => $progressPct,
                    'completed_at' => $issuedDate,
                    'certificate_number' => $certNo,
                    'certificate_issued_at' => $issuedDate,
                    'certificate_expires_at' => $expiresDate,
                    'is_superseded' => false,
                ]);

                foreach ($course1Modules as $mIdx => $module) {
                    $isDone = $mIdx < $completedCount;
                    ModuleProgress::create([
                        'enrollment_id' => $enrollment->id,
                        'course_module_id' => $module->id,
                        'is_completed' => $isDone,
                        'completed_at' => $isDone ? Carbon::now()->subDays(rand(1, 6)) : null,
                    ]);
                }

                if ($status === 'approved') {
                    $attStatusS1 = $idx % 9 === 0 ? 'permit' : ($idx % 13 === 0 ? 'absent' : 'present');
                    Attendance::create([
                        'attendance_session_id' => $c1Sessions[0]->id,
                        'user_id' => $peserta->id,
                        'status' => $attStatusS1,
                        'scanned_at' => $attStatusS1 === 'present' ? Carbon::now()->subDays(7)->setHour(8)->setMinute(rand(1, 45)) : null,
                        'device_info' => 'Mozilla/5.0 (Linux; Android 14; Mobile) Chrome/124.0',
                    ]);

                    if ($idx < 14) {
                        Attendance::create([
                            'attendance_session_id' => $c1Sessions[1]->id,
                            'user_id' => $peserta->id,
                            'status' => 'present',
                            'scanned_at' => Carbon::now()->subMinutes(rand(10, 90)),
                            'device_info' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) Mobile/15E148',
                        ]);
                    }
                }
            }

            foreach ($participants->slice(8, 17)->values() as $idx => $peserta) {
                $status = $idx === 16 ? 'pending' : 'approved';
                $completedCount = $status === 'approved' ? (($idx + 1) % 5) : 0;
                $progressPct = (int) round(($completedCount / $course2Modules->count()) * 100);

                $enrollment = Enrollment::create([
                    'user_id' => $peserta->id,
                    'course_id' => $course2->id,
                    'status' => $status,
                    'progress_percentage' => $progressPct,
                ]);

                foreach ($course2Modules as $mIdx => $module) {
                    $isDone = $mIdx < $completedCount;
                    ModuleProgress::create([
                        'enrollment_id' => $enrollment->id,
                        'course_module_id' => $module->id,
                        'is_completed' => $isDone,
                        'completed_at' => $isDone ? Carbon::now()->subDays(rand(1, 4)) : null,
                    ]);
                }

                if ($status === 'approved') {
                    $attStatusS1 = $idx % 8 === 0 ? 'permit' : 'present';
                    Attendance::create([
                        'attendance_session_id' => $c2Sessions[0]->id,
                        'user_id' => $peserta->id,
                        'status' => $attStatusS1,
                        'scanned_at' => $attStatusS1 === 'present' ? Carbon::now()->subDays(4)->setHour(8)->setMinute(rand(5, 50)) : null,
                        'device_info' => 'Mozilla/5.0 (Linux; Android 14; Mobile) Chrome/124.0',
                    ]);

                    if ($idx < 11) {
                        Attendance::create([
                            'attendance_session_id' => $c2Sessions[1]->id,
                            'user_id' => $peserta->id,
                            'status' => 'present',
                            'scanned_at' => Carbon::now()->subMinutes(rand(5, 60)),
                            'device_info' => 'Mozilla/5.0 (Linux; Android 14; Mobile) Chrome/124.0',
                        ]);
                    }
                }
            }

            // 8. Buat User Skills (Matriks Kompetensi 25 Peserta x 6 Skills = 150 baris data)
            foreach ($participants as $pIdx => $peserta) {
                foreach ($skills as $sIdx => $skill) {
                    $targetLevel = match ($peserta->department) {
                        'Mining Operations' => in_array($skill->code, ['SKL-OPS-01', 'SKL-OPS-02', 'SKL-HSE-01']) ? 4 : 3,
                        'Plant & Maintenance' => in_array($skill->code, ['SKL-MNT-01', 'SKL-HSE-01']) ? 5 : 3,
                        'HSE & Quality Control' => in_array($skill->code, ['SKL-HSE-01', 'SKL-HSE-02', 'SKL-LDR-01']) ? 5 : 3,
                        default => 3,
                    };

                    $currentLevel = max(1, min(5, (($pIdx + $sIdx * 2) % 5) + 1));

                    // Variasi valid_until & last_certificate_number agar 4 warna Heatmap Skill Matrix (Valid, H-60, Expired, Gap) langsung terlihat
                    $validUntil = null;
                    $lastCertNo = null;
                    if ($currentLevel >= $targetLevel) {
                        if (($pIdx + $sIdx) % 7 === 0) {
                            // Contoh Sertifikat EXPIRED (Merah Tua / Coret)
                            $validUntil = Carbon::now()->subDays(15 + ($pIdx % 20))->toDateString();
                            $lastCertNo = sprintf('CERT/CRS-2025-00%d/2023/10/%04d', ($sIdx % 4) + 1, $pIdx * 10 + $sIdx);
                        } elseif (($pIdx + $sIdx) % 5 === 0) {
                            // Contoh Sertifikat WARNING H-60 (Kuning/Amber)
                            $validUntil = Carbon::now()->addDays(18 + (($pIdx * 3) % 35))->toDateString();
                            $lastCertNo = sprintf('CERT/CRS-2025-00%d/2024/11/%04d', ($sIdx % 4) + 1, $pIdx * 10 + $sIdx);
                        } else {
                            // Contoh Sertifikat VALID AKTIF (Hijau)
                            $validUntil = Carbon::now()->addMonths(14 + ($sIdx % 10))->toDateString();
                            $lastCertNo = sprintf('CERT/CRS-2025-00%d/2025/02/%04d', ($sIdx % 4) + 1, $pIdx * 10 + $sIdx);
                        }
                    }

                    UserSkill::create([
                        'user_id' => $peserta->id,
                        'skill_id' => $skill->id,
                        'target_level' => $targetLevel,
                        'current_level' => $currentLevel,
                        'valid_until' => $validUntil,
                        'last_certificate_number' => $lastCertNo,
                        'updated_by' => $sIdx % 2 === 0 ? $trainer1->id : $trainer2->id,
                    ]);
                }
            }
        });

        // Panggil seeder master data terstruktur MDKA Group (Excel / Roster 1.511 Manpower)
        $this->call([
            ImportMdkadataSeeder::class,
        ]);
    }
}
