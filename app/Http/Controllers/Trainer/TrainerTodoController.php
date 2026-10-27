<?php

namespace App\Http\Controllers\Trainer;

use App\Http\Controllers\Controller;
use App\Models\TrainerTaskAndKpi;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainerTodoController extends Controller
{
    /**
     * HALAMAN TRAINER TO-DO (TUGAS, PERINTAH & JADWAL INSTRUKTUR)
     *
     * Fitur Utama:
     * 1. Admin L&D: Memberikan tugas, perintah, checklist, atau jadwal ke instruktur.
     * 2. Trainer Role View: Instruktur melihat tugas yang diberikan, tanggal mulai & due date,
     *    status selesai / belum, serta menambahkan catatan (trainer notes).
     * 3. Filter & Metrik: Status (Pending, In Progress, Selesai, Terlambat), Prioritas, dan Tipe Tugas.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $userRole = in_array($user->role, ['lnd', 'admin'], true) ? 'lnd' : $user->role;
        $isTrainer = $userRole === 'trainer' || (bool) $user->is_trainer;
        $canManage = in_array($userRole, ['lnd', 'admin'], true);

        // Filter Inputs
        $search = trim((string) $request->input('search', ''));
        $statusFilter = $request->input('status', 'all');
        $typeFilter = $request->input('task_type', 'all');
        $priorityFilter = $request->input('priority', 'all');
        $trainerFilter = $request->input('trainer_id');

        // Jika login sebagai Trainer (bukan Admin), batasi hanya tugas miliknya
        // Kecuali Admin LnD sedang memfilter instruktur tertentu
        $effectiveTrainerId = null;
        if (!$canManage && $isTrainer) {
            $effectiveTrainerId = $user->id;
        } elseif ($canManage && !empty($trainerFilter)) {
            $effectiveTrainerId = (int) $trainerFilter;
        }

        $today = now()->toDateString();

        // Query Dasar To-Do
        $todosQuery = TrainerTaskAndKpi::query()
            ->with([
                'trainer:id,name,nik,department,trainer_type',
                'creator:id,name',
            ])
            ->when($effectiveTrainerId, fn ($q) => $q->where('trainer_id', $effectiveTrainerId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('trainer_notes', 'like', "%{$search}%")
                        ->orWhereHas('trainer', fn ($tq) => $tq->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($statusFilter !== 'all', function ($q) use ($statusFilter, $today) {
                if ($statusFilter === 'overdue') {
                    $q->where('status', '!=', 'completed')
                      ->whereNotNull('due_date')
                      ->whereDate('due_date', '<', $today);
                } else {
                    $q->where('status', $statusFilter);
                }
            })
            ->when($typeFilter !== 'all', fn ($q) => $q->where('task_type', $typeFilter))
            ->when($priorityFilter !== 'all', fn ($q) => $q->where('priority', $priorityFilter));

        $allTodos = (clone $todosQuery)
            ->orderByRaw("
                CASE 
                    WHEN status = 'in_progress' AND due_date < '{$today}' THEN 1
                    WHEN status = 'in_progress' THEN 2
                    WHEN status = 'pending' THEN 3
                    WHEN status = 'completed' THEN 4
                    ELSE 5
                END ASC
            ")
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->get();

        // Format Todos untuk Frontend
        $formattedTodos = $allTodos->map(function (TrainerTaskAndKpi $todo) use ($today) {
            $isCompleted = $todo->status === 'completed';
            $isOverdue = !$isCompleted && $todo->due_date && $todo->due_date->format('Y-m-d') < $today;

            $typeLabel = match ($todo->task_type) {
                'perintah' => 'Perintah / Instruksi',
                'jadwal' => 'Jadwal Mengajar',
                'checklist' => 'Checklist K3 / SOP',
                default => 'Tugas L&D',
            };

            $priorityBadge = match ($todo->priority) {
                'urgent' => ['label' => 'MENDESAK', 'class' => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/40 dark:text-red-400'],
                'high' => ['label' => 'TINGGI', 'class' => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400'],
                'low' => ['label' => 'RENDAH', 'class' => 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400'],
                default => ['label' => 'SEDANG', 'class' => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/40 dark:text-blue-400'],
            };

            return [
                'id' => $todo->id,
                'trainer_id' => $todo->trainer_id,
                'trainer_name' => $todo->trainer?->name ?? 'Instruktur',
                'trainer_nik' => $todo->trainer?->nik ?? '-',
                'trainer_dept' => $todo->trainer?->department ?? '-',
                'title' => $todo->title,
                'description' => $todo->description,
                'trainer_notes' => $todo->trainer_notes,
                'task_type' => $todo->task_type ?? 'task',
                'task_type_label' => $typeLabel,
                'priority' => $todo->priority ?? 'medium',
                'priority_badge' => $priorityBadge,
                'start_date' => $todo->start_date?->toDateString(),
                'start_date_formatted' => $todo->start_date?->translatedFormat('d M Y') ?? '-',
                'due_date' => $todo->due_date?->toDateString(),
                'due_date_formatted' => $todo->due_date?->translatedFormat('d M Y') ?? 'Tidak Ada Batas',
                'days_remaining' => $todo->due_date ? Carbon::parse($today)->diffInDays($todo->due_date, false) : null,
                'status' => $todo->status,
                'status_label' => match ($todo->status) {
                    'completed' => 'Selesai',
                    'in_progress' => 'Sedang Dikerjakan',
                    'pending' => 'Menunggu',
                    'cancelled' => 'Dibatalkan',
                    default => ucfirst($todo->status),
                },
                'is_completed' => $isCompleted,
                'is_overdue' => $isOverdue,
                'completed_at_formatted' => $todo->completed_at?->translatedFormat('d M Y H:i') ?? null,
                'creator_name' => $todo->creator?->name ?? 'Admin L&D',
                'created_at_formatted' => $todo->created_at?->translatedFormat('d M Y') ?? '-',
            ];
        });

        // Metrik Ringkasan
        $totalCount = $formattedTodos->count();
        $completedCount = $formattedTodos->where('is_completed', true)->count();
        $inProgressCount = $formattedTodos->where('status', 'in_progress')->count();
        $pendingCount = $formattedTodos->where('status', 'pending')->count();
        $overdueCount = $formattedTodos->where('is_overdue', true)->count();
        $urgentCount = $formattedTodos->whereIn('priority', ['urgent', 'high'])->where('is_completed', false)->count();

        $summary = [
            'total' => $totalCount,
            'completed' => $completedCount,
            'in_progress' => $inProgressCount,
            'pending' => $pendingCount,
            'overdue' => $overdueCount,
            'urgent' => $urgentCount,
            'completion_rate' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0,
        ];

        // Daftar Seluruh Trainer untuk Modal Penugasan Admin
        $trainers = User::query()
            ->trainers()
            ->select('id', 'name', 'nik', 'department', 'trainer_type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Trainer/Todos', [
            'todos' => $formattedTodos,
            'trainers' => $trainers,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'status' => $statusFilter,
                'task_type' => $typeFilter,
                'priority' => $priorityFilter,
                'trainer_id' => $trainerFilter ?? '',
            ],
            'canManage' => $canManage,
            'userRole' => $userRole,
            'currentTrainerId' => $user->id,
        ]);
    }

    /**
     * ADMIN L&D MEMBERIKAN PERINTAH / TUGAS / JADWAL BARU UNTUK TRAINER
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'trainer_id' => ['required', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['required', 'string', 'in:perintah,tugas,jadwal,checklist,task'],
            'priority' => ['required', 'string', 'in:low,medium,high,urgent'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'string', 'in:pending,in_progress,completed'],
        ]);

        $trainer = User::findOrFail($validated['trainer_id']);

        $startDate = !empty($validated['start_date'])
            ? Carbon::parse($validated['start_date'])->toDateString()
            : now()->toDateString();

        $dueDate = !empty($validated['due_date'])
            ? Carbon::parse($validated['due_date'])->toDateString()
            : null;

        $status = $validated['status'] ?? 'in_progress';
        $completedAt = $status === 'completed' ? now() : null;

        TrainerTaskAndKpi::create([
            'trainer_id' => $trainer->id,
            'title' => $validated['title'],
            'task_type' => $validated['task_type'],
            'priority' => $validated['priority'],
            'description' => $validated['description'] ?? null,
            'period' => 'monthly',
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'status' => $status,
            'completed_at' => $completedAt,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', "Tugas / Perintah baru berhasil ditugaskan kepada {$trainer->name}.");
    }

    /**
     * UPDATE STATUS, NOTE, TANGGAL & DUE DATE (DAPAT DIAKSES OLEH ADMIN L&D DAN TRAINER)
     */
    public function update(Request $request, TrainerTaskAndKpi $todo): RedirectResponse
    {
        $user = $request->user();
        $canManage = in_array($user->role, ['lnd', 'admin'], true);

        // Jika role Trainer, pastikan hanya bisa update tugas miliknya
        if (!$canManage && $todo->trainer_id !== $user->id) {
            abort(403, 'Anda tidak memiliki hak akses untuk mengubah tugas ini.');
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'task_type' => ['nullable', 'string', 'in:perintah,tugas,jadwal,checklist,task'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,urgent'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trainer_notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', 'in:pending,in_progress,completed,cancelled'],
        ]);

        // Catat tanggal selesai jika status berubah ke completed
        if ($validated['status'] === 'completed' && $todo->status !== 'completed') {
            $todo->completed_at = now();
        } elseif ($validated['status'] !== 'completed') {
            $todo->completed_at = null;
        }

        // Jika yang update adalah Admin, update semua field yang diizinkan
        if ($canManage) {
            $todo->fill($validated);
        } else {
            // Jika yang update Trainer, hanya boleh update status, trainer_notes, dan tanggal jika diizinkan
            $todo->status = $validated['status'];
            if (array_key_exists('trainer_notes', $validated)) {
                $todo->trainer_notes = $validated['trainer_notes'];
            }
            if (!empty($validated['due_date'])) {
                $todo->due_date = $validated['due_date'];
            }
        }

        $todo->save();

        return back()->with('success', 'Status dan catatan tugas berhasil diperbarui.');
    }

    /**
     * QUICK TOGGLE CHECKBOX STATUS (SELESAI / BELUM SELESAI)
     */
    public function toggleStatus(Request $request, TrainerTaskAndKpi $todo): RedirectResponse
    {
        $user = $request->user();
        $canManage = in_array($user->role, ['lnd', 'admin'], true);

        if (!$canManage && $todo->trainer_id !== $user->id) {
            abort(403);
        }

        if ($todo->status === 'completed') {
            $todo->status = 'in_progress';
            $todo->completed_at = null;
            $msg = "Tugas '{$todo->title}' dikembalikan ke status Sedang Dikerjakan.";
        } else {
            $todo->status = 'completed';
            $todo->completed_at = now();
            $msg = "Tugas '{$todo->title}' berhasil ditandai Selesai.";
        }

        if ($request->filled('trainer_notes')) {
            $todo->trainer_notes = $request->input('trainer_notes');
        }

        $todo->save();

        return back()->with('success', $msg);
    }

    /**
     * HAPUS PENUGASAN (HANYA ADMIN L&D)
     */
    public function destroy(Request $request, TrainerTaskAndKpi $todo): RedirectResponse
    {
        if (!in_array($request->user()?->role, ['lnd', 'admin'], true)) {
            abort(403, 'Hanya Admin L&D yang dapat menghapus penugasan.');
        }

        $todo->delete();

        return back()->with('success', 'Tugas berhasil dihapus dari sistem.');
    }
}

