<?php

namespace App\Http\Controllers\Competency;

use App\Http\Controllers\Controller;
use App\Models\JobPosition;
use App\Models\PositionSkillStandard;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PositionSkillSettingController extends Controller
{
    /**
     * TUGAS 3 - Poin 1: Halaman "Setting Competency on Level x Position".
     * - Role `lnd` / `admin`: Full CRUD (Tambah/Edit Level x Position, Set Required Target Level 1-5, & Sync ke Pekerja).
     * - Role `dept_admin` & `contractor_admin`: Read-Only (Melihat standar matriks kompetensi jabatan).
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $canEdit = in_array($actor->role, ['lnd', 'admin'], true);

        $skills = Skill::query()
            ->select(['id', 'code', 'name', 'category', 'description'])
            ->orderBy('code')
            ->get();

        $positions = JobPosition::query()
            ->with([
                'skillStandards:id,job_position_id,skill_id,required_target_level,is_mandatory',
                'users' => fn ($q) => $q->accessibleBy($actor)->select(['id', 'name', 'nik', 'department', 'organization_id', 'job_position_id']),
            ])
            ->orderBy('level_name')
            ->orderBy('position_title')
            ->get()
            ->map(function (JobPosition $pos) {
                $standardsMap = [];
                foreach ($pos->skillStandards as $std) {
                    $standardsMap[$std->skill_id] = [
                        'skill_id' => (int) $std->skill_id,
                        'required_target_level' => (int) $std->required_target_level,
                        'is_mandatory' => (bool) $std->is_mandatory,
                    ];
                }

                return [
                    'id' => $pos->id,
                    'code' => $pos->code,
                    'level_name' => $pos->level_name,
                    'position_title' => $pos->position_title,
                    'full_label' => $pos->full_label,
                    'organization_type' => $pos->organization_type,
                    'standards_count' => $pos->skillStandards->count(),
                    'workers_count' => $pos->users->count(),
                    'workers' => $pos->users->map(fn (User $u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'nik' => $u->nik,
                        'department' => $u->department,
                    ])->values(),
                    'standards_map' => $standardsMap,
                ];
            });

        $selectedPositionId = $request->integer('position_id') ?: ($positions->first()['id'] ?? null);

        // Daftar pekerja yang dapat di-assign ke jabatan ini
        $availableWorkers = User::query()
            ->accessibleBy($actor)
            ->where('role', 'peserta')
            ->orderBy('name')
            ->get(['id', 'name', 'nik', 'department', 'job_position_id']);

        return Inertia::render('Competency/PositionSkillSetting', [
            'positions' => $positions,
            'skills' => $skills,
            'selectedPositionId' => $selectedPositionId,
            'availableWorkers' => $availableWorkers,
            'canEdit' => $canEdit,
            'activeTabParam' => $request->string('tab', 'standards')->toString(),
        ]);
    }

    /**
     * Tambah atau Update Master "Level x Position" (`job_positions`) - Khusus `lnd`.
     */
    public function storePosition(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(!in_array($actor->role, ['lnd', 'admin'], true), 403, 'Hanya Tim LnD yang dapat mengelola Master Level x Position.');

        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:job_positions,id'],
            'code' => ['required', 'string', 'max:40'],
            'level_name' => ['required', 'string', 'max:80'],
            'position_title' => ['required', 'string', 'max:120'],
            'organization_type' => ['required', 'in:internal,contractor,both'],
        ]);

        $pos = JobPosition::updateOrCreate(
            ['code' => strtoupper(trim($validated['code']))],
            [
                'level_name' => trim($validated['level_name']),
                'position_title' => trim($validated['position_title']),
                'organization_type' => $validated['organization_type'],
            ]
        );

        return redirect()
            ->route('competency.position-standards.index', ['position_id' => $pos->id])
            ->with('success', "Master Level x Position [{$pos->code}] {$pos->level_name} - {$pos->position_title} berhasil disimpan.");
    }

    /**
     * Simpan Standar Kompetensi (`position_skill_standards`) pada suatu `JobPosition` - Khusus `lnd`.
     */
    public function saveStandards(Request $request, JobPosition $jobPosition): RedirectResponse
    {
        $actor = $request->user();
        abort_if(!in_array($actor->role, ['lnd', 'admin'], true), 403, 'Hanya Tim LnD yang berwenang mengubah standar kompetensi jabatan.');

        $validated = $request->validate([
            'standards' => ['required', 'array'],
            'standards.*.skill_id' => ['required', 'integer', 'exists:skills,id'],
            'standards.*.enabled' => ['required', 'boolean'],
            'standards.*.required_target_level' => ['required', 'integer', 'min:1', 'max:5'],
            'standards.*.is_mandatory' => ['required', 'boolean'],
            'auto_sync_workers' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($jobPosition, $validated) {
            $enabledSkillIds = [];

            foreach ($validated['standards'] as $row) {
                if (!empty($row['enabled'])) {
                    $enabledSkillIds[] = (int) $row['skill_id'];

                    PositionSkillStandard::updateOrCreate(
                        [
                            'job_position_id' => $jobPosition->id,
                            'skill_id' => (int) $row['skill_id'],
                        ],
                        [
                            'required_target_level' => (int) $row['required_target_level'],
                            'is_mandatory' => (bool) $row['is_mandatory'],
                        ]
                    );
                }
            }

            // Hapus skill yang tidak lagi dicentang pada jabatan ini
            PositionSkillStandard::query()
                ->where('job_position_id', $jobPosition->id)
                ->whereNotIn('skill_id', $enabledSkillIds)
                ->delete();
        });

        $syncedCount = 0;
        if ($request->boolean('auto_sync_workers', true)) {
            $syncedCount = $jobPosition->syncStandardsToAssignedUsers($actor->id);
        }

        return back()->with(
            'success',
            "Standar kompetensi untuk [{$jobPosition->code}] berhasil disimpan" .
            ($syncedCount > 0 ? " dan disinkronkan ke {$syncedCount} pekerja." : '.')
        );
    }

    /**
     * Tombol 1-Klik: "Sync ke Semua Pekerja di Posisi Ini".
     * Menyinkronkan `target_level` di tabel `user_skills` seluruh pekerja pada jabatan tersebut.
     */
    public function syncToWorkers(Request $request, JobPosition $jobPosition): RedirectResponse
    {
        $actor = $request->user();
        abort_if(!in_array($actor->role, ['lnd', 'admin'], true), 403, 'Hanya Tim LnD yang dapat melakukan sinkronisasi massal.');

        $syncedCount = $jobPosition->syncStandardsToAssignedUsers($actor->id);

        return back()->with(
            'success',
            "Sinkronisasi selesai: Target Level kompetensi pada {$syncedCount} pekerja di posisi [{$jobPosition->code}] {$jobPosition->level_name} - {$jobPosition->position_title} telah diperbarui."
        );
    }

    /**
     * Assign pekerja (`peserta`) ke dalam suatu `JobPosition` dan otomatis sinkronkan `target_level` di `user_skills`.
     */
    public function assignWorkerPosition(Request $request, JobPosition $jobPosition): RedirectResponse
    {
        $actor = $request->user();
        abort_if(!in_array($actor->role, ['lnd', 'admin', 'dept_admin', 'contractor_admin'], true), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $worker = User::query()->accessibleBy($actor)->whereKey($validated['user_id'])->firstOrFail();

        // Menyimpan job_position_id otomatis memicu model event User::saved -> syncPositionSkillStandards()
        $worker->update([
            'job_position_id' => $jobPosition->id,
        ]);

        return back()->with(
            'success',
            "Pekerja {$worker->name} ({$worker->nik}) berhasil ditempatkan pada posisi [{$jobPosition->code}] dan Target Level kompetensinya telah disinkronkan otomatis."
        );
    }
}

