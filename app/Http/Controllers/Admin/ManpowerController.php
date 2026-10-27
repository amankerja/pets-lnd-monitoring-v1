<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManpowerRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Organization;
use App\Models\SelfReportedTraining;
use App\Models\User;
use App\Traits\HasTrainingFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManpowerController extends Controller
{
    use HasTrainingFilters;

    /**
     * Halaman Terpadu Modul Input Data & Pendaftaran:
     * 1. Manpower CRUD (Internal Group & External Contractor Group) + Batch CSV Import
     * 2. Pendaftaran Training (Individu & Batch Checkbox)
     * 3. Input Riwayat Training Masa Lalu (Historical Migration Mode A & Mode B)
     * 4. Verifikasi Self-Report Sertifikat Mandiri Peserta
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $filters = $this->extractTrainingFilters($request);

        // Daftar Organisasi sesuai scope akses
        $organizations = Organization::query()
            ->when(
                in_array($actor->role, ['dept_admin', 'contractor_admin'], true),
                fn ($q) => $q->whereKey($actor->organization_id)
            )
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'type']);

        // Daftar Pekerja (Manpower) terisolasi dengan scopeAccessibleBy + Global Multi-Attribute Filter
        $manpowersQuery = User::query()
            ->accessibleBy($actor)
            ->with([
                'organization:id,code,name,type',
                'enrollments:id,user_id,course_id,status,progress_percentage,certificate_number,certificate_issued_at,certificate_expires_at,created_at',
                'enrollments.course:id,code,title,is_mandatory,training_source,training_category',
            ]);

        $this->applyUserFilters($manpowersQuery, $filters, $actor, 'users.created_at');

        $manpowersPaginated = $manpowersQuery
            ->orderBy('organization_id')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'nik' => $u->nik,
                'email' => $u->email,
                'role' => $u->role,
                'organization_id' => $u->organization_id,
                'organization_code' => $u->organization?->code ?? 'INT',
                'organization_name' => $u->organization?->name ?? ($u->department ?: '-'),
                'organization_type' => $u->organization?->type ?? 'internal_dept',
                'enrollments_count' => $u->enrollments->count(),
                'certificates_count' => $u->enrollments->whereNotNull('certificate_number')->count(),
            ]);

        // Katalog Kelas untuk Batch Enrollment & Historical Migration
        $courses = Course::query()
            ->select([
                'id',
                'code',
                'title',
                'status',
                'validity_years',
                'start_date',
                'end_date',
                'is_mandatory',
                'training_source',
                'training_category',
            ])
            ->orderBy('code')
            ->get();

        // Daftar Self-Reported Trainings yang menunggu verifikasi LnD / Admin
        $selfReportsQuery = SelfReportedTraining::query()
            ->accessibleBy($actor)
            ->with([
                'user:id,name,nik,department,organization_id',
                'user.organization:id,code,name,type',
                'reviewer:id,name',
            ]);

        $this->applySelfReportFilters($selfReportsQuery, $filters, $actor, 'self_reported_trainings.issued_at');

        $selfReportsPaginated = $selfReportsQuery
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'sr_page')
            ->withQueryString()
            ->through(fn (SelfReportedTraining $sr) => [
                'id' => $sr->id,
                'user_id' => $sr->user_id,
                'user_name' => $sr->user?->name ?? '-',
                'user_nik' => $sr->user?->nik ?? '-',
                'organization_name' => $sr->user?->organization?->name ?? ($sr->user?->department ?? '-'),
                'organization_type' => $sr->user?->organization?->type ?? 'internal_dept',
                'course_name' => $sr->course_name,
                'organizer' => $sr->organizer,
                'certificate_number' => $sr->certificate_number,
                'issued_at' => $sr->issued_at?->format('Y-m-d'),
                'issued_at_formatted' => $sr->issued_at?->format('d M Y'),
                'expires_at' => $sr->expires_at?->format('Y-m-d'),
                'expires_at_formatted' => $sr->expires_at?->format('d M Y') ?? 'Seumur Hidup',
                'document_path' => $sr->document_path,
                'status' => $sr->status,
                'reviewer_name' => $sr->reviewer?->name,
                'review_notes' => $sr->review_notes,
                'created_at' => $sr->created_at?->format('d M Y H:i'),
            ]);

        // Audit Data Anomali (Karyawan tanpa job_position_id / 'No Match' atau tanpa organization_id / 'Dept Tidak Terdaftar')
        $anomalies = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->where(function ($q) {
                $q->whereNull('job_position_id')
                  ->orWhereNull('organization_id');
            })
            ->with([
                'organization:id,code,name,type',
                'jobPosition:id,code,level_name,position_title',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'nik' => $u->nik,
                'department' => $u->department ?? 'Tidak Terdaftar',
                'site' => $u->site ?? '-',
                'organization_id' => $u->organization_id,
                'organization_name' => $u->organization?->name,
                'job_position_id' => $u->job_position_id,
                'job_position_label' => $u->jobPosition?->full_label,
                'issue_type' => !$u->job_position_id && !$u->organization_id
                    ? 'Belum Ada Posisi & Dept'
                    : (!$u->job_position_id ? 'Belum Ada Jabatan (No Match)' : 'Dept Belum Terdaftar'),
            ]);

        $jobPositions = \App\Models\JobPosition::query()
            ->orderBy('code')
            ->get(['id', 'code', 'level_name', 'position_title']);

        return Inertia::render('Admin/ManpowerAndMigration', [
            'manpowers' => $manpowersPaginated->items(),
            'manpowersPagination' => $manpowersPaginated,
            'organizations' => $organizations,
            'jobPositions' => $jobPositions,
            'anomalies' => $anomalies,
            'courses' => $courses,
            'selfReports' => $selfReportsPaginated->items(),
            'selfReportsPagination' => $selfReportsPaginated,
            'tab' => $request->string('tab', 'manpower')->toString(),
            'filters' => $filters,
            'filterOptions' => $this->buildTrainingFilterOptions($actor),
        ]);
    }

    /**
     * Petakan cepat karyawan anomali ke JobPosition dan Organization yang benar.
     */
    public function mapWorkerPositionOrg(Request $request, User $manpower): RedirectResponse
    {
        $actor = $request->user();
        abort_if(!in_array($actor->role, ['lnd', 'admin'], true), 403, 'Akses ditolak.');

        $validated = $request->validate([
            'job_position_id' => ['nullable', 'integer', 'exists:job_positions,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $org = !empty($validated['organization_id']) ? Organization::find($validated['organization_id']) : null;

        $manpower->update([
            'job_position_id' => $validated['job_position_id'] ?? $manpower->job_position_id,
            'organization_id' => $validated['organization_id'] ?? $manpower->organization_id,
            'department' => $org?->name ?? $manpower->department,
        ]);

        if (!empty($validated['job_position_id'])) {
            $manpower->syncPositionSkillStandards($actor->id);
        }

        return back()->with('success', "Data posisi dan organisasi {$manpower->name} ({$manpower->nik}) berhasil dipetakan & disinkronkan ke Skill Matrix.");
    }

    /**
     * 1A. Simpan Data Pekerja Baru (Individual Manpower CRUD).
     */
    public function store(ManpowerRequest $request): RedirectResponse
    {
        $actor = $request->user();
        $validated = $request->validated();

        // Kunci organization_id & role jika yang menginput adalah Admin Dept / Admin Kontraktor
        $organizationId = in_array($actor->role, ['dept_admin', 'contractor_admin'], true)
            ? $actor->organization_id
            : (int) $validated['organization_id'];

        $role = in_array($actor->role, ['lnd', 'admin'], true)
            ? ($validated['role'] ?? 'peserta')
            : 'peserta';

        $org = Organization::findOrFail($organizationId);

        User::create([
            'name' => $validated['name'],
            'nik' => strtoupper(trim($validated['nik'])),
            'email' => strtolower(trim($validated['email'])),
            'role' => $role,
            'organization_id' => $org->id,
            'job_position_id' => $validated['job_position_id'] ?? null,
            'department' => $org->name,
            'password' => Hash::make($validated['password'] ?: 'password123'),
            'email_verified_at' => now(),
        ]);

        return back()->with('success', "Manpower {$validated['name']} ({$validated['nik']}) berhasil ditambahkan ke {$org->name}.");
    }

    /**
     * 1B. Update Data Pekerja (Individual Manpower CRUD dengan proteksi Anti-IDOR).
     */
    public function update(ManpowerRequest $request, User $manpower): RedirectResponse
    {
        $actor = $request->user();

        // Proteksi Anti-IDOR: Pastikan pekerja berada dalam scope organisasi aktor
        $target = User::query()->accessibleBy($actor)->whereKey($manpower->id)->first();
        abort_if(!$target, 403, 'Akses ditolak: Anda tidak berwenang mengubah data pekerja dari organisasi lain.');

        $validated = $request->validated();

        $organizationId = in_array($actor->role, ['dept_admin', 'contractor_admin'], true)
            ? $actor->organization_id
            : (int) $validated['organization_id'];

        $org = Organization::findOrFail($organizationId);

        $updates = [
            'name' => $validated['name'],
            'nik' => strtoupper(trim($validated['nik'])),
            'email' => strtolower(trim($validated['email'])),
            'organization_id' => $org->id,
            'job_position_id' => $validated['job_position_id'] ?? $target->job_position_id,
            'department' => $org->name,
        ];

        if (in_array($actor->role, ['lnd', 'admin'], true) && !empty($validated['role'])) {
            $updates['role'] = $validated['role'];
        }

        if (!empty($validated['password'])) {
            $updates['password'] = Hash::make($validated['password']);
        }

        $target->update($updates);

        return back()->with('success', "Data manpower {$target->name} berhasil diperbarui.");
    }

    /**
     * 1C. Hapus Data Pekerja.
     */
    public function destroy(Request $request, User $manpower): RedirectResponse
    {
        $actor = $request->user();
        $target = User::query()->accessibleBy($actor)->whereKey($manpower->id)->first();
        abort_if(!$target, 403, 'Akses ditolak: Pekerja ini berada di luar organisasi Anda.');

        $target->delete();

        return back()->with('success', 'Data manpower berhasil dihapus.');
    }

    /**
     * 1D. Upload Masal (Batch Import Manpower via CSV) dengan Validasi NIK Unik & Isolasi Organisasi.
     * Format Kolom CSV: NIK, Nama, Email, Kode_Organisasi
     */
    public function importCsv(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'default_organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'csv_file' => 'File CSV tidak dapat dibaca.',
            ]);
        }

        $orgMapByCode = Organization::query()
            ->get(['id', 'code', 'name'])
            ->keyBy(fn (Organization $o) => strtoupper(trim($o->code)));

        $defaultOrgId = in_array($actor->role, ['dept_admin', 'contractor_admin'], true)
            ? $actor->organization_id
            : ($request->integer('default_organization_id') ?: Organization::query()->value('id'));

        $defaultOrg = Organization::find($defaultOrgId);

        $importedCount = 0;
        $rowNumber = 0;

        DB::transaction(function () use ($handle, $actor, $orgMapByCode, $defaultOrg, &$importedCount, &$rowNumber) {
            while (($row = fgetcsv($handle, 2000, ',')) !== false) {
                $rowNumber++;

                // Bersihkan UTF-8 BOM pada baris pertama jika ada
                if ($rowNumber === 1 && isset($row[0])) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                }

                $col0 = strtoupper(trim((string) ($row[0] ?? '')));
                $col1 = trim((string) ($row[1] ?? ''));
                $col2 = strtolower(trim((string) ($row[2] ?? '')));
                $col3 = strtoupper(trim((string) ($row[3] ?? '')));

                // Lewati baris kosong atau baris header CSV
                if ($col0 === '' || in_array($col0, ['NIK', 'NO_INDUK', 'EMPLOYEE_ID'], true)) {
                    continue;
                }

                if ($col1 === '') {
                    continue;
                }

                // Tentukan organisasi (kunci paksa ke organisasi aktor jika bukan Super Admin LnD)
                if (in_array($actor->role, ['dept_admin', 'contractor_admin'], true)) {
                    $resolvedOrg = $actor->organization ?? $defaultOrg;
                } else {
                    $resolvedOrg = $orgMapByCode->get($col3) ?? $defaultOrg;
                }

                if (!$resolvedOrg) {
                    continue;
                }

                $email = $col2 !== '' && filter_var($col2, FILTER_VALIDATE_EMAIL)
                    ? $col2
                    : strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $col0)) . '@pani-worker.local';

                // Cegah tabrakan NIK milik organisasi lain saat di-import oleh Admin Kontraktor/Dept
                $existingUser = User::where('nik', $col0)->first();
                if ($existingUser && in_array($actor->role, ['dept_admin', 'contractor_admin'], true) && $existingUser->organization_id !== $actor->organization_id) {
                    throw ValidationException::withMessages([
                        'csv_file' => "Baris #{$rowNumber}: NIK {$col0} sudah terdaftar pada organisasi/perusahaan lain.",
                    ]);
                }

                User::updateOrCreate(
                    ['nik' => $col0],
                    [
                        'name' => $col1,
                        'email' => $email,
                        'role' => 'peserta',
                        'organization_id' => $resolvedOrg->id,
                        'department' => $resolvedOrg->name,
                        'password' => $existingUser?->password ?? Hash::make('password123'),
                        'email_verified_at' => now(),
                    ]
                );

                $importedCount++;
            }
        });

        fclose($handle);

        return back()->with('success', "Import CSV selesai: {$importedCount} data manpower berhasil disimpan/diperbarui.");
    }

    /**
     * Unduh Template CSV Import Manpower (dengan UTF-8 BOM untuk Excel).
     */
    public function downloadTemplate(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['NIK', 'Nama', 'Email', 'Kode_Organisasi']);
            fputcsv($handle, ['PANI-0201', 'Bambang Pamungkas', 'bambang.p@pani.co.id', 'DEPT-OPS']);
            fputcsv($handle, ['VND-MJU-088', 'Hendro Kartiko', 'hendro@majujaya-vendor.co.id', 'VND-PTMJU']);
            fclose($handle);
        }, 'Template_Import_Manpower_LND.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * 2. Pendaftaran Training (Individu & Batch Multi-Select Checkbox) dengan pencatatan Time-Series `registered_by`.
     */
    public function storeEnrollment(Request $request): RedirectResponse
    {
        $actor = $request->user();

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['required', 'integer', 'exists:users,id'],
        ]);

        // Validasi RBAC Scope: Semua pekerja yang dipilih wajib berada dalam cakupan organisasi aktor
        $allowedWorkerIds = User::query()
            ->accessibleBy($actor)
            ->whereIn('id', $validated['participant_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        abort_if(
            count($allowedWorkerIds) !== count(array_unique($validated['participant_ids'])),
            403,
            'Akses ditolak: Terdapat pekerja di luar cakupan departemen/perusahaan Anda.'
        );

        $status = in_array($actor->role, ['lnd', 'admin'], true) ? 'approved' : 'pending';

        DB::transaction(function () use ($allowedWorkerIds, $validated, $actor, $status) {
            foreach ($allowedWorkerIds as $workerId) {
                Enrollment::firstOrCreate(
                    [
                        'user_id' => $workerId,
                        'course_id' => (int) $validated['course_id'],
                    ],
                    [
                        'registered_by' => $actor->id,
                        'status' => $status,
                        'progress_percentage' => 0,
                    ]
                );
            }
        });

        return back()->with('success', count($allowedWorkerIds) . ' peserta berhasil didaftarkan ke kelas pelatihan (Tercatat secara time-series).');
    }
}

