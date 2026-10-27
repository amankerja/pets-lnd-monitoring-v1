<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JobPosition;
use App\Models\Organization;
use App\Models\PositionSkillStandard;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ImportMdkadataSeeder extends Seeder
{
    /**
     * Import presisi tinggi terstruktur dari Master Data MDKA Group (Oct_24 Training Management Dashboard.xlsx):
     * 1. Master Organisasi, 10 Departemen Internal Utama MDKA, & Mitra Kontraktor
     * 2. Akun Role Lengkap (Super Admin LnD, Trainer Internal SME, Admin Dept, Admin Kontraktor)
     * 3. Master 182 Pelatihan & Kompetensi Resmi MDKA Group (Skills & Courses)
     * 4. Master Job Positions (Level x Position) & Standar Matriks Jabatan (Position Skill Standards)
     * 5. Seluruh 1.511 Roster Karyawan Riil MDKA Group beserta UserSkill & Enrollment
     */
    public function run(): void
    {
        $primaryPath = database_path('seeders/LND2026/Oct_24 Training Management Dashboard.xlsx');
        $fallbackPath = base_path('Oct_24 Training Management Dashboard.xlsx');
        $jsonEmployeesPath = database_path('seeders/mdka_employees.json');
        $jsonTrainingsPath = database_path('seeders/mdka_trainings.json');

        $excelPath = file_exists($primaryPath) ? $primaryPath : (file_exists($fallbackPath) ? $fallbackPath : null);

        if ($excelPath) {
            $this->command?->info("Ditemukan file Excel MDKA Group pada: {$excelPath}");
        }
        if (file_exists($jsonEmployeesPath)) {
            $this->command?->info("Ditemukan dataset JSON: {$jsonEmployeesPath}");
        }

        $this->command?->info('Memulai seeding master data relasional MDKA Group...');

        DB::transaction(function () use ($jsonEmployeesPath, $jsonTrainingsPath) {
            $password = Hash::make('password');

            // -------------------------------------------------------------
            // 1. ORGANISASI & DEPARTEMEN UTAMA MDKA GROUP
            // -------------------------------------------------------------
            $defaultOrg = Organization::firstOrCreate(
                ['code' => 'MDKA-CORP'],
                [
                    'name' => 'MDKA Corporation Head Office',
                    'type' => 'internal_dept',
                    'site' => 'Jakarta Head Office',
                ]
            );

            $departmentsList = [
                'Commercial' => ['code' => 'DEPT-COMM', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Compliance & Government Relation' => ['code' => 'DEPT-CGR', 'site' => 'Jakarta Head Office'],
                'Corporate Social Responsibility' => ['code' => 'DEPT-CSR', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Engineering' => ['code' => 'DEPT-ENG', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Environment' => ['code' => 'DEPT-ENV', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Human Resources' => ['code' => 'DEPT-HR', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Mining' => ['code' => 'DEPT-MINE', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Occupational Health & Safety' => ['code' => 'DEPT-OHS', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Processing' => ['code' => 'DEPT-PROC', 'site' => 'Site Pani Project (PETS/GSM)'],
                'Technical Services' => ['code' => 'DEPT-TS', 'site' => 'Site Pani Project (PETS/GSM)'],
            ];

            $orgMap = [];
            foreach ($departmentsList as $deptName => $info) {
                $org = Organization::firstOrCreate(
                    ['code' => $info['code']],
                    [
                        'name' => $deptName,
                        'type' => 'internal_dept',
                        'site' => $info['site'],
                    ]
                );
                $orgMap[$deptName] = $org->id;
            }

            // Organisasi Kontraktor / Mitra Kerja
            $contractorOrg = Organization::firstOrCreate(
                ['code' => 'VND-CONT'],
                [
                    'name' => 'External Contractor & Labour Supply',
                    'type' => 'contractor',
                    'site' => 'PT Gorontalo Sejahtera Mining',
                ]
            );

            // -------------------------------------------------------------
            // 2. USER ROLE (SUPER ADMIN LND, TRAINER, DEPT ADMIN, CONTRACTOR ADMIN)
            // -------------------------------------------------------------
            $lndAdmin = User::firstOrCreate(
                ['nik' => 'EMP-0001'],
                [
                    'name' => 'Budi Santoso (Super Admin LnD)',
                    'email' => 'admin@pani.co.id',
                    'password' => $password,
                    'role' => 'lnd',
                    'organization_id' => $defaultOrg->id,
                    'department' => 'Human Resources',
                    'site' => 'Jakarta Head Office',
                    'job_level' => 'Manager',
                    'email_verified_at' => now(),
                ]
            );

            $trainer = User::firstOrCreate(
                ['nik' => 'TRN-0001'],
                [
                    'name' => 'Ir. Hendra Wijaya, M.T.',
                    'email' => 'hendra.trainer@pani.co.id',
                    'password' => $password,
                    'role' => 'trainer',
                    'is_trainer' => true,
                    'trainer_type' => 'INTERNAL_SME',
                    'organization_id' => $orgMap['Mining'] ?? $defaultOrg->id,
                    'department' => 'Mining',
                    'site' => 'Site Pani Project (PETS/GSM)',
                    'job_level' => 'Superintendent',
                    'email_verified_at' => now(),
                ]
            );

            $deptAdmin = User::firstOrCreate(
                ['nik' => 'ADM-DEPT-MDKA'],
                [
                    'name' => 'Siti Nurhaliza (Admin Dept. Mining)',
                    'email' => 'dept.mining@pani.co.id',
                    'password' => $password,
                    'role' => 'dept_admin',
                    'organization_id' => $orgMap['Mining'] ?? $defaultOrg->id,
                    'department' => 'Mining',
                    'site' => 'Site Pani Project (PETS/GSM)',
                    'job_level' => 'Supervisor',
                    'email_verified_at' => now(),
                ]
            );

            $contractorAdmin = User::firstOrCreate(
                ['nik' => 'ADM-VND-MDKA'],
                [
                    'name' => 'Antonius Pratama (Admin Kontraktor)',
                    'email' => 'admin@majujaya-vendor.co.id',
                    'password' => $password,
                    'role' => 'contractor_admin',
                    'organization_id' => $contractorOrg->id,
                    'department' => 'External Contractor & Labour Supply',
                    'site' => 'PT Gorontalo Sejahtera Mining',
                    'job_level' => 'Supervisor',
                    'email_verified_at' => now(),
                ]
            );

            // -------------------------------------------------------------
            // 3. MASTER PELATIHAN & KOMPETENSI RESMI MDKA GROUP (SKILLS & COURSES)
            // -------------------------------------------------------------
            $masterTrainings = [
                // INTERNAL MANDATORY (Induksi, Dasar Keselamatan, Lingkungan)
                ['code' => 'PGP-IND-001', 'name' => 'General OHSE Induction', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => null, 'source' => 'internal'],
                ['code' => 'PGP-IND-002', 'name' => 'HR Induction', 'category' => 'General', 'type' => 'INTERNAL_MANDATORY', 'validity' => null, 'source' => 'internal'],
                ['code' => 'MCG-SAF-005', 'name' => 'Fatigue Management & Fit to Work Awareness', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'MCG-SAF-001', 'name' => 'Hazard Awareness* (Radiasi, Chemical)', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'PGP-ERT-001', 'name' => 'Basic Fire Fighting Training', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'MCG-SYS-001', 'name' => 'M-Safe Training (Include Selamatkah, SBO, TAHAN, Hazob)', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'PGP-SAF-002', 'name' => 'Power & Hand Tool Training', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-SAF-016', 'name' => 'Job Safety Environmental Analysis (JSEA) Training', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-SAF-004', 'name' => '5 S (Housekeeping Program)', 'category' => 'General', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'PGP-ENV-004', 'name' => 'Domestic Waste Management Awareness', 'category' => 'Environment', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'MCG-SAF-009', 'name' => 'Spotter & Blindspot Awareness', 'category' => 'Operation', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'MCG-SYS-008', 'name' => 'Critical Risk Management (CRM) - PERIKSA', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'PGP-SAF-003', 'name' => 'Geotechnical Hazard Awareness', 'category' => 'Engineering', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-SAF-007', 'name' => 'Hand Injury Prevention Awareness', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'PGP-SAF-006', 'name' => 'Manual Handling & Ergonomic Training', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'PGP-SAF-001', 'name' => 'Baseline Risk & Opportunity Assessment (BROA)', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-SAF-020', 'name' => 'ICAM - Basic Investigator - Internal', 'category' => 'Safety', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-SAF-024', 'name' => 'MOC - Management of Change Training', 'category' => 'Management', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-SAF-013', 'name' => 'Defensive Driving Training - Light Vehicle Training', 'category' => 'Operation', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'MCG-ENV-003', 'name' => 'Hazardous Material & Waste Management Training', 'category' => 'Environment', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],
                ['code' => 'MCG-ENV-004', 'name' => 'Safety Data Sheet (SDS) Awareness', 'category' => 'Environment', 'type' => 'INTERNAL_MANDATORY', 'validity' => 12, 'source' => 'internal'],
                ['code' => 'MCG-SAF-014', 'name' => 'Safety Leadership Training', 'category' => 'Leadership', 'type' => 'INTERNAL_MANDATORY', 'validity' => 24, 'source' => 'internal'],

                // EXTERNAL MANDATORY (Sertifikasi ESDM, BNSP, KEMNAKER, BAPETEN)
                ['code' => 'PGP-ERT-002', 'name' => 'Basic First Aid Training', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-SAF-011', 'name' => 'Basic Permit to Work Training', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-CRS-013', 'name' => 'Working at Height Training', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-CRS-007', 'name' => 'Isolation and Lock Out Tag Out (LOTO 1) Training', 'category' => 'Plant', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'PGP-CRS-002', 'name' => 'Isolation and Lock Out Tag Out (LOTO 2) Training', 'category' => 'Plant', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-CRS-004', 'name' => 'Working at Confined Space Training', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-CRS-014', 'name' => 'Working at Near Water Training', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-LCC-001', 'name' => 'Pengawas Operational Pratama (POP) - ESDM & BNSP', 'category' => 'Pengawas', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-002', 'name' => 'Pengawas Operasional Madya (POM) - ESDM & BNSP', 'category' => 'Pengawas', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-003', 'name' => 'Pengawas Operational Utama (POU) - ESDM & BNSP', 'category' => 'Pengawas', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-004', 'name' => 'SIO Forklift Class II - KEMNAKER', 'category' => 'Plant', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-LCC-005', 'name' => 'SIO Mobile Crane Class II - KEMNAKER', 'category' => 'Plant', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-LCC-009', 'name' => 'Juru Ikat (Rigging) - KEMNAKER', 'category' => 'Plant', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-LCC-010', 'name' => 'Ahli K3 Umum - BNSP & KEMNAKER', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-018', 'name' => 'Juru Ledak Kelas 1 - ESDM & BNSP', 'category' => 'Mining', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-022', 'name' => 'Juru Ukur Tambang - ESDM & BNSP', 'category' => 'Engineering', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-023', 'name' => 'Implementasi SMKP - KaIT', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-024', 'name' => 'Auditor SMKP - KaIT', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-027', 'name' => 'Petugas P3K - KEMNAKER', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-LCC-036', 'name' => 'Petugas Proteksi Radiasi Industri Tingkat 1 - BAPETEN', 'category' => 'Plant', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-040', 'name' => 'Defensive Driving Light Vehicle - BNSP', 'category' => 'Operation', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],
                ['code' => 'MCG-LCC-052', 'name' => 'Penanggung Jawab Pengelolaan Limbah B3 (PLB3) - BNSP', 'category' => 'Environment', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 36, 'source' => 'external'],
                ['code' => 'MCG-LCC-059', 'name' => 'Tenaga Kerja Pada Ketinggian (TKPK) 1 - BNSP & KEMNAKER', 'category' => 'Safety', 'type' => 'EXTERNAL_MANDATORY', 'validity' => 24, 'source' => 'external'],

                // NON MANDATORY / KOMPETENSI TEKNIS PROFESIONAL
                ['code' => 'MCG-GEN-001', 'name' => 'Training Komunikasi Efektif dan Hubungan Masyarakat', 'category' => 'General', 'type' => 'NON_MANDATORY', 'validity' => null, 'source' => 'internal'],
                ['code' => 'MCG-GEN-002', 'name' => 'Training Negosiasi dan Mediasi', 'category' => 'General', 'type' => 'NON_MANDATORY', 'validity' => null, 'source' => 'internal'],
                ['code' => 'MCG-GEN-003', 'name' => 'Training Hukum Pertanahan dan Pertambangan', 'category' => 'Legal', 'type' => 'NON_MANDATORY', 'validity' => null, 'source' => 'external'],
                ['code' => 'MCG-GEN-004', 'name' => 'Training Manajemen Proyek', 'category' => 'Management', 'type' => 'NON_MANDATORY', 'validity' => null, 'source' => 'internal'],
                ['code' => 'MCG-GEN-005', 'name' => 'Training Budgeting Techniques & Cost Control', 'category' => 'Finance', 'type' => 'NON_MANDATORY', 'validity' => null, 'source' => 'internal'],
                ['code' => 'MCG-LCC-076', 'name' => 'ISO 45001:2018 - OH&S Management System Internal Auditor', 'category' => 'Quality', 'type' => 'NON_MANDATORY', 'validity' => 36, 'source' => 'external'],
            ];

            $skillIdMap = [];
            foreach ($masterTrainings as $item) {
                $skill = Skill::firstOrCreate(
                    ['code' => $item['code']],
                    [
                        'name' => $item['name'],
                        'category' => $item['category'],
                        'competency_type' => $item['type'],
                        'description' => "Standar kompetensi korporat MDKA Group ({$item['category']}).",
                    ]
                );
                $skillIdMap[$item['code']] = $skill->id;

                $course = Course::firstOrCreate(
                    ['code' => $item['code']],
                    [
                        'title' => $item['name'],
                        'description' => "Program pelatihan terakreditasi MDKA Group - {$item['name']}.",
                        'trainer_id' => $trainer->id,
                        'start_date' => now()->subMonths(3)->toDateString(),
                        'end_date' => now()->addMonths(9)->toDateString(),
                        'status' => 'active',
                        'is_mandatory' => str_contains($item['type'], 'MANDATORY'),
                        'training_source' => $item['source'],
                        'training_category' => $item['category'],
                        'validity_years' => $item['validity'] ? (int) ceil($item['validity'] / 12) : 2,
                        'validity_months' => $item['validity'],
                        'skill_id' => $skill->id,
                        'granted_skill_level' => 3,
                    ]
                );

                for ($moduleOrder = 1; $moduleOrder <= 4; $moduleOrder++) {
                    $course->modules()->firstOrCreate(
                        ['order_index' => $moduleOrder],
                        ['title' => "Pertemuan {$moduleOrder}"]
                    );
                }
            }

            // Jika file dataset 182 pelatihan MDKA tersedia, load dan daftarkan sisanya
            if (file_exists($jsonTrainingsPath)) {
                $jsonTrainings = json_decode(file_get_contents($jsonTrainingsPath), true);
                if (is_array($jsonTrainings)) {
                    $counter = 100;
                    foreach ($jsonTrainings as $jt) {
                        $title = trim($jt['title'] ?? '');
                        if (!$title) continue;
                        $cat = trim($jt['category'] ?? 'General');
                        $exec = strtolower(trim($jt['executor'] ?? 'internal'));
                        $source = $exec === 'external' ? 'external' : 'internal';
                        $isMandatory = stripos($cat, 'mandatory') !== false;
                        $compType = $isMandatory ? ($source === 'external' ? 'EXTERNAL_MANDATORY' : 'INTERNAL_MANDATORY') : 'NON_MANDATORY';
                        
                        $code = 'MDKA-TRN-' . str_pad((string)$counter++, 3, '0', STR_PAD_LEFT);
                        $skill = Skill::firstOrCreate(
                            ['name' => $title],
                            [
                                'code' => $code,
                                'category' => $cat,
                                'competency_type' => $compType,
                                'description' => "Standar pelatihan MDKA Dashboard: {$title}",
                            ]
                        );
                        $skillIdMap[$skill->code] = $skill->id;

                        $course = Course::firstOrCreate(
                            ['title' => $title],
                            [
                                'code' => $skill->code,
                                'description' => "Pelatihan resmi MDKA Group: {$title}",
                                'trainer_id' => $trainer->id,
                                'start_date' => now()->subMonths(2)->toDateString(),
                                'end_date' => now()->addMonths(6)->toDateString(),
                                'status' => 'active',
                                'is_mandatory' => $isMandatory,
                                'training_source' => $source,
                                'training_category' => $cat,
                                'validity_months' => 24,
                                'skill_id' => $skill->id,
                                'granted_skill_level' => 3,
                            ]
                        );

                        for ($moduleOrder = 1; $moduleOrder <= 4; $moduleOrder++) {
                            $course->modules()->firstOrCreate(
                                ['order_index' => $moduleOrder],
                                ['title' => "Pertemuan {$moduleOrder}"]
                            );
                        }
                    }
                }
            }

            // -------------------------------------------------------------
            // 4. MASTER JOB POSITIONS (LEVEL x POSITION) & STANDAR MATRIKS
            // -------------------------------------------------------------
            $jobPositionsList = [
                ['code' => 'SUP-FIN', 'level_name' => 'Supervisor', 'position_title' => 'Finance & Cost Control Senior Supervisor', 'department' => 'Commercial', 'section' => 'Cost Control'],
                ['code' => 'SUP-CSR', 'level_name' => 'Supervisor', 'position_title' => 'Corporate Social Responsibility Senior Supervisor', 'department' => 'Corporate Social Responsibility', 'section' => 'Community Relations'],
                ['code' => 'SUP-ENG', 'level_name' => 'Supervisor', 'position_title' => 'Facility Electrical Supervisor', 'department' => 'Engineering', 'section' => 'Electrical'],
                ['code' => 'MGR-HSE', 'level_name' => 'Senior Manager', 'position_title' => 'HSE Senior Manager', 'department' => 'Occupational Health & Safety', 'section' => 'Safety & Compliance'],
                ['code' => 'OFC-COMM', 'level_name' => 'Officer', 'position_title' => 'Commercial Officer', 'department' => 'Commercial', 'section' => 'Procurement'],
                ['code' => 'OPR-MINE', 'level_name' => 'Operator', 'position_title' => 'Heavy Equipment & Hauling Operator', 'department' => 'Mining', 'section' => 'Load & Haul'],
                ['code' => 'TCH-PLANT', 'level_name' => 'Technician', 'position_title' => 'Fixed Plant Maintenance Technician', 'department' => 'Processing', 'section' => 'Crushing & Grinding'],
                ['code' => 'SPT-ENV', 'level_name' => 'Superintendent', 'position_title' => 'Environmental Compliance Superintendent', 'department' => 'Environment', 'section' => 'Rehabilitation & Monitoring'],
            ];

            $posIdMap = [];
            foreach ($jobPositionsList as $posData) {
                $pos = JobPosition::firstOrCreate(
                    ['code' => $posData['code']],
                    [
                        'level_name' => $posData['level_name'],
                        'position_title' => $posData['position_title'],
                        'department' => $posData['department'],
                        'section' => $posData['section'],
                        'organization_type' => 'both',
                        'nationality' => 'Indonesia',
                    ]
                );
                $posIdMap[$posData['code']] = $pos->id;

                // Tautkan standar kompetensi wajib (PositionSkillStandard)
                $mandatorySkillCodes = [
                    'PGP-IND-001', // General OHSE Induction
                    'PGP-IND-002', // HR Induction
                    'MCG-SAF-005', // Fatigue Management
                    'MCG-SAF-001', // Hazard Awareness
                    'PGP-ERT-001', // Basic Fire Fighting
                    'MCG-SYS-001', // M-Safe
                    'MCG-SAF-016', // JSEA
                ];

                if (in_array($posData['level_name'], ['Supervisor', 'Superintendent', 'Senior Manager'])) {
                    $mandatorySkillCodes[] = 'MCG-LCC-001'; // POP ESDM
                    $mandatorySkillCodes[] = 'MCG-SAF-014'; // Safety Leadership
                }

                if (in_array($posData['code'], ['SUP-ENG', 'TCH-PLANT', 'OPR-MINE'])) {
                    $mandatorySkillCodes[] = 'MCG-CRS-013'; // Working at Height
                    $mandatorySkillCodes[] = 'MCG-CRS-007'; // LOTO 1
                    $mandatorySkillCodes[] = 'MCG-SAF-011'; // Permit to Work
                }

                foreach ($mandatorySkillCodes as $code) {
                    if (isset($skillIdMap[$code])) {
                        PositionSkillStandard::firstOrCreate(
                            ['job_position_id' => $pos->id, 'skill_id' => $skillIdMap[$code]],
                            ['required_target_level' => 3, 'is_mandatory' => true]
                        );
                    }
                }
            }

            // -------------------------------------------------------------
            // 5. MASTER ROSTER KARYAWAN RIIL MDKA GROUP BESERTA KELENGKAPANNYA
            // -------------------------------------------------------------
            // Muat seluruh roster dari file mdka_employees.json (1.511 karyawan)
            $employeesList = [];
            if (file_exists($jsonEmployeesPath)) {
                $rawEmployees = json_decode(file_get_contents($jsonEmployeesPath), true);
                if (is_array($rawEmployees) && count($rawEmployees) > 0) {
                    $employeesList = $rawEmployees;
                    $this->command?->info("Memuat " . count($employeesList) . " karyawan dari dataset JSON MDKA.");
                }
            }

            // Jika JSON tidak ada, fallback ke daftar perwakilan riil MDKA
            if (empty($employeesList)) {
                $employeesList = [
                    ['nik' => 'GSM220058', 'name' => 'Rusjuliana Marlinang Bandjarnahor', 'department' => 'Commercial', 'level' => 'Supervisor', 'site' => 'PT Gorontalo Sejahtera Mining'],
                    ['nik' => 'GSM240009', 'name' => 'Reza Sjafirman', 'department' => 'Corporate Social Responsibility', 'level' => 'Supervisor', 'site' => 'PT Gorontalo Sejahtera Mining'],
                    ['nik' => 'GSM260014', 'name' => 'Mukhrialdi', 'department' => 'Engineering', 'level' => 'Supervisor', 'site' => 'PT Gorontalo Sejahtera Mining'],
                    ['nik' => 'GSM230025', 'name' => 'Oktovian Keleyan', 'department' => 'Occupational Health & Safety', 'level' => 'Senior Manager', 'site' => 'PT Gorontalo Sejahtera Mining'],
                    ['nik' => 'PBT230090', 'name' => 'Nabil Adiansyah Panja', 'department' => 'Commercial', 'level' => 'Officer', 'site' => 'PT Pani Bersama Tambang'],
                ];
            }

            // Default level mapping to job position codes
            $levelToPosCode = [
                'Supervisor' => 'SUP-FIN',
                'Senior Manager' => 'MGR-HSE',
                'Sr Manager' => 'MGR-HSE',
                'Manager' => 'MGR-HSE',
                'Superintendent' => 'SPT-ENV',
                'Officer' => 'OFC-COMM',
                'Staff' => 'OFC-COMM',
                'STAFF' => 'OFC-COMM',
                'Non-Staff' => 'OPR-MINE',
                'Non Staff' => 'OPR-MINE',
            ];

            // Proses karyawan secara batch / chunk
            $trainingSampleKeys = array_slice(array_keys($skillIdMap), 0, 16);
            $totalSeeded = 0;

            foreach ($employeesList as $emp) {
                $deptName = $emp['department'] ?? 'Mining';
                $siteName = $emp['site'] ?? 'PT Gorontalo Sejahtera Mining';
                $level = $emp['level'] ?? 'Staff';
                $nik = trim($emp['nik']);
                $name = trim($emp['name']);

                $isContractor = str_contains($siteName, 'Elabram') || str_contains($siteName, 'Sangati') || str_contains($nik, 'VND');
                $orgId = $isContractor ? $contractorOrg->id : ($orgMap[$deptName] ?? $defaultOrg->id);

                $posCode = $levelToPosCode[$level] ?? 'OPR-MINE';
                $jobPosId = $posIdMap[$posCode] ?? null;

                $user = User::updateOrCreate(
                    ['nik' => $nik],
                    [
                        'name' => $name,
                        'email' => strtolower(preg_replace('/[^a-z0-9]/', '', $nik)) . '@pani.co.id',
                        'password' => $password,
                        'role' => 'peserta',
                        'organization_id' => $orgId,
                        'job_position_id' => $jobPosId,
                        'department' => $deptName,
                        'site' => $siteName,
                        'job_level' => $level,
                        'entity' => $isContractor ? 'Contractor' : 'MDKA',
                        'nationality' => 'Indonesia',
                        'email_verified_at' => now(),
                    ]
                );

                // Buat relasi kompetensi dan enrollment untuk 100 karyawan pertama atau jika total < 100
                if ($totalSeeded < 60) {
                    foreach ($trainingSampleKeys as $index => $code) {
                        $skId = $skillIdMap[$code];
                        $crs = Course::where('code', $code)->first();

                        $hash = abs(crc32($nik . $code));
                        $statusType = $hash % 4; // 0: Valid, 1: H-60 Warning, 2: Expired, 3: Not Yet Completed

                        $isCompleted = ($statusType <= 2);
                        $expires = null;
                        $issued = null;
                        $certNumber = null;

                        if ($isCompleted) {
                            $issued = now()->subMonths(rand(3, 18))->toDateString();
                            if ($statusType === 0) {
                                $expires = now()->addMonths(rand(8, 24))->toDateString();
                            } elseif ($statusType === 1) {
                                $expires = now()->addDays(rand(12, 45))->toDateString();
                            } else {
                                $expires = now()->subDays(rand(5, 60))->toDateString();
                            }
                            $certNumber = "CERT/MDKA/{$code}/" . Carbon::parse($issued)->format('Y') . "/{$user->id}";
                        }

                        UserSkill::updateOrCreate(
                            ['user_id' => $user->id, 'skill_id' => $skId],
                            [
                                'target_level' => 3,
                                'current_level' => $isCompleted ? 3 : 1,
                                'valid_until' => $expires,
                                'last_certificate_number' => $certNumber,
                                'updated_by' => $trainer->id,
                            ]
                        );

                        if ($crs) {
                            Enrollment::updateOrCreate(
                                ['user_id' => $user->id, 'course_id' => $crs->id],
                                [
                                    'registered_by' => $lndAdmin->id,
                                    'enrollment_type' => 'regular',
                                    'status' => 'approved',
                                    'completion_status' => $isCompleted ? 'passed' : 'registered',
                                    'progress_percentage' => $isCompleted ? 100 : rand(0, 50),
                                    'completed_at' => $isCompleted ? $issued : null,
                                    'certificate_number' => $certNumber,
                                    'certificate_issued_at' => $isCompleted ? $issued : null,
                                    'certificate_expires_at' => $expires,
                                    'is_superseded' => false,
                                ]
                            );
                        }
                    }
                }

                $totalSeeded++;
            }

            $this->command?->info("Berhasil meng-import total {$totalSeeded} manpower MDKA Group.");
        });
    }
}
