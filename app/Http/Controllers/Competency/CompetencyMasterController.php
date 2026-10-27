<?php

namespace App\Http\Controllers\Competency;

use App\Http\Controllers\Controller;
use App\Models\Skill;
use App\Traits\HasTrainingFilters;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyMasterController extends Controller
{
    use HasTrainingFilters;

    /**
     * TUGAS 2 - Poin 1: Daftar Master Data Kompetensi (Competency List).
     * Dilengkapi Server-Side Filtering (Search, Jenis Kompetensi, Bidang/Kategori),
     * Paginasi 25 baris per halaman, dan Statistik Ringkas per Kompetensi:
     * - Jumlah Posisi (`Level x Position`) yang mensyaratkan kompetensi ini
     * - Jumlah Pekerja Kompeten (`current_level >= target_level` & belum expired)
     * - Jumlah Pekerja Defisit Gap (`current_level < target_level` atau expired)
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $todayStr = now()->toDateString();

        $search = trim((string) $request->input('search', ''));
        $competencyType = trim((string) $request->input('competency_type', ''));
        $category = trim((string) ($request->input('category') ?: $request->input('training_category', '')));
        $mandatoryType = trim((string) $request->input('mandatory_type', ''));
        $trainingSource = trim((string) $request->input('training_source', ''));

        // Sinkronisasi jika user memfilter lewat atribut GlobalTableFilterBar (mandatory_type / training_source)
        if ($competencyType === '') {
            if ($mandatoryType === 'non_mandatory') {
                $competencyType = Skill::TYPE_NON_MANDATORY;
            } elseif ($mandatoryType === 'mandatory' && $trainingSource === 'external') {
                $competencyType = Skill::TYPE_EXTERNAL_MANDATORY;
            } elseif ($mandatoryType === 'mandatory' && $trainingSource === 'internal') {
                $competencyType = Skill::TYPE_INTERNAL_MANDATORY;
            }
        }

        $baseQuery = Skill::query()
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('courses', function ($cq) use ($search) {
                            $cq->where('code', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                });
            })
            ->when($competencyType !== '', fn ($q) => $q->where('competency_type', $competencyType))
            ->when($category !== '', fn ($q) => $q->where('category', $category));

        // Hitung 4 KPI Ringkasan Master Kompetensi
        $kpi = [
            'total' => (clone $baseQuery)->count(),
            'external_mandatory' => (clone $baseQuery)->where('competency_type', Skill::TYPE_EXTERNAL_MANDATORY)->count(),
            'internal_mandatory' => (clone $baseQuery)->where('competency_type', Skill::TYPE_INTERNAL_MANDATORY)->count(),
            'non_mandatory' => (clone $baseQuery)->where('competency_type', Skill::TYPE_NON_MANDATORY)->count(),
        ];

        // Query dengan perhitungan agregat statistik per kompetensi (Anti N+1 Query)
        $competencies = $baseQuery
            ->with([
                'courses:id,skill_id,code,title,validity_months',
                'jobPositions:id,code,level_name,position_title',
            ])
            ->withCount([
                'positionStandards as positions_count',
                'courses as courses_count',
                'userSkills as total_workers_count',
                'userSkills as competent_workers_count' => function ($q) use ($todayStr) {
                    $q->whereColumn('current_level', '>=', 'target_level')
                        ->where(function ($sub) use ($todayStr) {
                            $sub->whereNull('valid_until')
                                ->orWhereDate('valid_until', '>=', $todayStr);
                        });
                },
                'userSkills as gap_workers_count' => function ($q) use ($todayStr) {
                    $q->where(function ($sub) use ($todayStr) {
                        $sub->whereColumn('current_level', '<', 'target_level')
                            ->orWhere(function ($exp) use ($todayStr) {
                                $exp->whereNotNull('valid_until')
                                    ->whereDate('valid_until', '<', $todayStr);
                            });
                    });
                },
            ])
            ->orderBy('category')
            ->orderBy('code')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Skill $skill) {
                return [
                    'id' => $skill->id,
                    'code' => $skill->code,
                    'name' => $skill->name,
                    'category' => $skill->category,
                    'competency_type' => $skill->competency_type ?? Skill::TYPE_INTERNAL_MANDATORY,
                    'description' => $skill->description,
                    'positions_count' => (int) $skill->positions_count,
                    'courses_count' => (int) $skill->courses_count,
                    'total_workers_count' => (int) $skill->total_workers_count,
                    'competent_workers_count' => (int) $skill->competent_workers_count,
                    'gap_workers_count' => (int) $skill->gap_workers_count,
                    'linked_courses' => $skill->courses->map(fn ($c) => [
                        'id' => $c->id,
                        'code' => $c->code,
                        'title' => $c->title,
                    ])->values(),
                    'linked_positions' => $skill->jobPositions->map(fn ($p) => [
                        'id' => $p->id,
                        'code' => $p->code,
                        'title' => "{$p->level_name} - {$p->position_title}",
                    ])->values(),
                    'updated_at' => $skill->updated_at?->format('d M Y'),
                ];
            });

        return Inertia::render('Competency/CompetencyList', [
            'competencies' => $competencies,
            'kpi' => $kpi,
            'categories' => Skill::CATEGORIES,
            'competencyTypes' => [
                [
                    'value' => Skill::TYPE_EXTERNAL_MANDATORY,
                    'label' => 'EXTERNAL - MANDATORY (Sertifikasi Lembaga Luar & Wajib)',
                    'short_label' => 'EXTERNAL - MANDATORY',
                ],
                [
                    'value' => Skill::TYPE_INTERNAL_MANDATORY,
                    'label' => 'INTERNAL - MANDATORY (Pelatihan Internal Perusahaan & Wajib)',
                    'short_label' => 'INTERNAL - MANDATORY',
                ],
                [
                    'value' => Skill::TYPE_NON_MANDATORY,
                    'label' => 'NON-MANDATORY (Pelatihan Opsional / Pilihan)',
                    'short_label' => 'NON-MANDATORY',
                ],
            ],
            'filters' => [
                'search' => $search,
                'competency_type' => $competencyType,
                'category' => $category,
            ],
            'canManage' => in_array($actor->role, ['lnd', 'admin'], true),
        ]);
    }

    /**
     * TUGAS 2 - Poin 2: Tambah Master Kompetensi Baru (Khusus Tim LnD / Admin).
     */
    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Akses ditolak: Hanya Tim LnD yang berhak menambah Master Data Kompetensi.'
        );

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('skills', 'code')],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'competency_type' => ['required', Rule::in(Skill::COMPETENCY_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'code.unique' => 'Kode kompetensi tersebut sudah terdaftar di sistem.',
            'competency_type.in' => 'Jenis kompetensi harus bernilai EXTERNAL_MANDATORY, INTERNAL_MANDATORY, atau NON_MANDATORY.',
        ]);

        Skill::create($validated);

        return back()->with(
            'success',
            "Master Kompetensi [{$validated['code']}] {$validated['name']} berhasil ditambahkan."
        );
    }

    /**
     * TUGAS 2 - Poin 2: Update Master Kompetensi (Khusus Tim LnD / Admin).
     */
    public function update(Request $request, Skill $competency): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Akses ditolak: Hanya Tim LnD yang berhak mengubah Master Data Kompetensi.'
        );

        $request->merge([
            'code' => strtoupper(trim((string) $request->input('code', ''))),
        ]);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('skills', 'code')->ignore($competency->id)],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'competency_type' => ['required', Rule::in(Skill::COMPETENCY_TYPES)],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'code.unique' => 'Kode kompetensi tersebut sudah digunakan oleh kompetensi lain.',
            'competency_type.in' => 'Jenis kompetensi harus bernilai EXTERNAL_MANDATORY, INTERNAL_MANDATORY, atau NON_MANDATORY.',
        ]);

        $competency->update($validated);

        return back()->with(
            'success',
            "Master Kompetensi [{$competency->code}] {$competency->name} berhasil diperbarui."
        );
    }

    /**
     * TUGAS 2 - Poin 3: Hapus Master Kompetensi dengan Pengecekan Aman.
     * Mencegah penghapusan jika kompetensi masih terikat aktif di `position_skill_standards`,
     * `user_skills` (Skill Matrix), atau `courses`.
     */
    public function destroy(Request $request, Skill $competency): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Akses ditolak: Hanya Tim LnD yang berhak menghapus Master Data Kompetensi.'
        );

        $positionsCount = $competency->positionStandards()->count();
        $userSkillsCount = $competency->userSkills()->count();
        $coursesCount = $competency->courses()->count();

        if ($positionsCount > 0 || $userSkillsCount > 0 || $coursesCount > 0) {
            return back()->with(
                'error',
                "Kompetensi [{$competency->code}] tidak dapat dihapus karena sedang terikat aktif pada {$positionsCount} Standar Jabatan (Level x Position), {$userSkillsCount} Rekaman Skill Matrix Pekerja, dan {$coursesCount} Program Training."
            );
        }

        $code = $competency->code;
        $name = $competency->name;
        $competency->delete();

        return back()->with(
            'success',
            "Master Kompetensi [{$code}] {$name} berhasil dihapus dari sistem."
        );
    }
}

