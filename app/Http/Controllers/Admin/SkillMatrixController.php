<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Organization;
use App\Models\PositionSkillStandard;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use App\Traits\HasTrainingFilters;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SkillMatrixController extends Controller
{
    use HasTrainingFilters;

    /**
     * Heatmap Skill Matrix & Gap Analysis yang terintegrasi penuh dengan Masa Berlaku Sertifikat (`valid_until`).
     * Menampilkan 4 Status Visual Sel:
     * 1. `valid`       -> Hijau (Current >= Target & Sertifikat Aktif > H-60 / Permanent)
     * 2. `warning_h60` -> Kuning/Amber Berdenyut (Current >= Target namun Sertifikat <= H-60)
     * 3. `expired`     -> Merah Tua / Coret (Masa berlaku sertifikat di `valid_until` telah habis < Today)
     * 4. `gap`         -> Merah Muda / Abu (Current < Target / Belum Kompeten)
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $today = now()->startOfDay();
        $warningThreshold = now()->addDays(60)->endOfDay();
        $filters = $this->extractTrainingFilters($request);

        // 1. Ambil Master Skills untuk kolom header Matrix
        $skills = Skill::query()
            ->select(['id', 'code', 'name', 'category'])
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // 2. Query Pekerja (Terisolasi Multi-Tenant RBAC + Global Multi-Attribute Filter)
        $participantsQuery = User::query()
            ->role('peserta')
            ->accessibleBy($actor)
            ->with([
                'organization:id,code,name,type',
                'jobPosition:id,code,level_name,position_title',
                'jobPosition.skillStandards',
                'skills',
                'userSkills' => fn ($q) => $q->select([
                    'id',
                    'user_id',
                    'skill_id',
                    'target_level',
                    'current_level',
                    'valid_until',
                    'last_certificate_number',
                    'updated_by',
                ]),
            ]);

        $this->applyUserFilters($participantsQuery, $filters, $actor);

        // 3. Transformasi Data Matriks per Pekerja & Evaluasi Standar Jabatan (M/NM/NR) dan Tanggal (`valid_until`)
        $participants = $participantsQuery
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(function (User $user) use ($skills, $today, $warningThreshold) {
                $userSkillsKeyed = $user->userSkills->keyBy('skill_id');
                $posStandards = $user->jobPosition?->skillStandards?->keyBy('skill_id') ?? collect();
                $hasPosition = !empty($user->job_position_id);
                $matchStatus = $hasPosition ? 'Matched Position' : 'No Match';

                $skillMap = [];

                foreach ($skills as $skill) {
                    $us = $userSkillsKeyed->get($skill->id);
                    $current = $us ? (int) $us->current_level : 0;
                    $std = $posStandards->get($skill->id);

                    $validUntil = ($us && $us->valid_until) ? Carbon::parse($us->valid_until)->startOfDay() : null;
                    $daysLeft = $validUntil ? (int) $today->diffInDays($validUntil, false) : null;

                    // Evaluasi Standar Jabatan (M, NM, NR / N/A)
                    $isNR = !$hasPosition || !$std || $std->requirement_type === 'NR';

                    if ($isNR) {
                        // Tidak termasuk standar jabatan karyawan ini (NR - Not Required / N/A) -> Jangan dihitung sebagai defisit
                        $stdType = 'NR';
                        $target = 0;
                        $cellStatus = 'not_required';
                        $isMandatory = false;
                    } else {
                        $stdType = $std->requirement_type ?: ($std->is_mandatory ? 'M' : 'NM');
                        $isMandatory = ($stdType === 'M');
                        $target = (int) $std->required_target_level;

                        // Logika 4 Status Visual Sel Skill Matrix:
                        // 1. EXPIRED (Merah Tua): Sertifikat sudah lewat tanggal kadaluarsa
                        // 2. GAP (Merah Muda / Abu): Current Level masih di bawah Target Level
                        // 3. WARNING_H60 (Kuning/Amber): Level tercapai, tapi sertifikat akan mati dalam <= 60 hari
                        // 4. VALID (Hijau): Level tercapai & sertifikat masih aktif panjang (> 60 hari atau Permanent)
                        if ($validUntil && $validUntil->lt($today)) {
                            $cellStatus = 'expired';
                        } elseif ($current < $target) {
                            $cellStatus = 'gap';
                        } elseif ($validUntil && $validUntil->lte($warningThreshold)) {
                            $cellStatus = 'warning_h60';
                        } else {
                            $cellStatus = 'valid';
                        }
                    }

                    $skillMap[$skill->id] = [
                        'id' => $us?->id,
                        'skill_id' => $skill->id,
                        'current' => $current,
                        'target' => $target,
                        'current_level' => $current,
                        'target_level' => $target,
                        'gap' => $isNR ? 0 : $current - $target,
                        'status' => $cellStatus,
                        'standard_type' => $stdType,
                        'is_mandatory' => $isMandatory,
                        'valid_until' => $validUntil ? $validUntil->format('d M Y') : null,
                        'days_left' => $daysLeft,
                        'cert_no' => $us?->last_certificate_number ?? null,
                    ];
                }

                $orgName = $user->organization?->name ?? ($user->department ?? '-');
                $orgType = $user->organization?->type ?? 'internal_dept';

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'nik' => $user->nik,
                    'org_name' => $orgName,
                    'department' => $orgName,
                    'org_type' => $orgType,
                    'organization_type' => $orgType,
                    'position' => $user->jobPosition?->position_title ?? ($hasPosition ? $user->jobPosition?->full_label : 'Unassigned Position'),
                    'match_status' => $matchStatus,
                    'has_position' => $hasPosition,
                    'matrix' => $skillMap,
                    'skills_map' => $skillMap,
                ];
            });

        return Inertia::render('Admin/SkillMatrix', [
            'skills' => $skills,
            'participants' => $participants,
            'filters' => $filters,
            'departments' => Organization::where('type', 'internal_dept')->orderBy('name')->get(['id', 'name', 'code']),
            'companies' => Organization::where('type', 'contractor')->orderBy('name')->get(['id', 'name', 'code']),
            'courseCodes' => Course::orderBy('code')->get(['id', 'code', 'title']),
            'courses' => Course::orderBy('code')->get(['id', 'code', 'title', 'is_mandatory', 'training_source', 'training_category']),
            'filterOptions' => $this->buildTrainingFilterOptions($actor),
        ]);
    }

    /**
     * Update manual nilai Current Level / Target Level pada sel Skill Matrix (Khusus LnD & Trainer).
     */
    public function updateCell(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin', 'trainer'], true),
            403,
            'Akses ditolak: Hanya Tim LnD dan Trainer yang berhak menginput/mengubah nilai kompetensi Matrix.'
        );

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'skill_id' => ['required', 'integer', 'exists:skills,id'],
            'current_level' => ['required', 'integer', 'min:0', 'max:5'],
            'target_level' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $targetParticipant = User::query()->accessibleBy($actor)->whereKey($validated['user_id'])->first();
        abort_if(!$targetParticipant, 403, 'Akses ditolak: Peserta tidak berada di dalam kelas/organisasi Anda.');

        $hasRequiredStandard = $targetParticipant->job_position_id !== null
            && PositionSkillStandard::query()
                ->where('job_position_id', $targetParticipant->job_position_id)
                ->where('skill_id', $validated['skill_id'])
                ->whereIn('requirement_type', ['M', 'NM'])
                ->exists();

        abort_if(!$hasRequiredStandard, 422, 'Skill ini berstatus NR untuk jabatan karyawan dan tidak dapat dicatat sebagai gap.');

        UserSkill::updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'skill_id' => $validated['skill_id'],
            ],
            [
                'current_level' => $validated['current_level'],
                'target_level' => $validated['target_level'],
                'updated_by' => $actor->id,
            ]
        );

        return back()->with('success', 'Level kompetensi peserta berhasil diperbarui.');
    }
}
