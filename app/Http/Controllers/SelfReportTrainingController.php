<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\SelfReportedTraining;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SelfReportTrainingController extends Controller
{
    /**
     * 4A. Halaman Portal Peserta: Input Pelatihan Mandiri + Upload File Sertifikat (PDF/JPG/PNG).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $submissions = SelfReportedTraining::query()
            ->where('user_id', $user->id)
            ->with(['reviewer:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SelfReportedTraining $sr) => [
                'id' => $sr->id,
                'course_name' => $sr->course_name,
                'organizer' => $sr->organizer,
                'certificate_number' => $sr->certificate_number,
                'issued_at' => $sr->issued_at?->format('d M Y'),
                'expires_at' => $sr->expires_at?->format('d M Y') ?? 'Seumur Hidup',
                'document_path' => $sr->document_path,
                'status' => $sr->status,
                'reviewer_name' => $sr->reviewer?->name,
                'review_notes' => $sr->review_notes,
                'created_at' => $sr->created_at?->format('d M Y H:i'),
            ]);

        return Inertia::render('Participant/SelfReportTraining', [
            'submissions' => $submissions,
        ]);
    }

    /**
     * 4B. Simpan Pengajuan Sertifikat Mandiri oleh Peserta (Status Default: `pending`).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'course_name' => ['required', 'string', 'max:200'],
            'organizer' => ['required', 'string', 'max:150'],
            'certificate_number' => ['required', 'string', 'max:100'],
            'issued_at' => ['required', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:issued_at'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'document_path' => ['nullable', 'string', 'max:255'],
        ]);

        $documentPath = $validated['document_path'] ?? 'certificates/self-reported/default-cert.pdf';
        if ($request->hasFile('certificate_file')) {
            $documentPath = $request->file('certificate_file')->store('certificates/self-reported', 'public');
        }

        SelfReportedTraining::create([
            'user_id' => $user->id,
            'course_name' => $validated['course_name'],
            'organizer' => $validated['organizer'],
            'certificate_number' => strtoupper(trim($validated['certificate_number'])),
            'issued_at' => $validated['issued_at'],
            'expires_at' => $validated['expires_at'] ?? null,
            'document_path' => $documentPath,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Laporan pelatihan mandiri berhasil dikirim dan menunggu verifikasi Tim LnD.');
    }

    /**
     * 4C. Verifikasi oleh Admin / Tim LnD: Setujui (`approved`) atau Tolak (`rejected`) dengan catatan.
     * Jika disetujui (`approved`) dan dipetakan ke `course_id` (atau dibuatkan pencatatan resmi),
     * otomatis masuk ke profil riwayat resmi pekerja (`enrollments`).
     */
    public function review(Request $request, SelfReportedTraining $selfReport): RedirectResponse
    {
        $actor = $request->user();
        abort_if(
            !in_array($actor->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang memverifikasi sertifikat mandiri pekerja.'
        );

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:500'],
            'course_id' => ['required_if:status,approved', 'nullable', 'integer', 'exists:courses,id'],
        ]);

        DB::transaction(function () use ($selfReport, $validated, $actor) {
            $selfReport->update([
                'status' => $validated['status'],
                'reviewed_by' => $actor->id,
                'review_notes' => $validated['review_notes'] ?? null,
            ]);

            // Jika disetujui (`approved`), sinkronkan ke tabel `enrollments` agar masuk ke profil resmi & Radar H-60
            if ($validated['status'] === 'approved') {
                $courseId = $validated['course_id'];

                $enrollment = Enrollment::updateOrCreate(
                    [
                        'user_id' => $selfReport->user_id,
                        'course_id' => (int) $courseId,
                    ],
                    [
                        'registered_by' => $actor->id,
                        'status' => 'approved',
                        'progress_percentage' => 100,
                        'certificate_number' => $selfReport->certificate_number,
                        'certificate_issued_at' => $selfReport->issued_at?->toDateString(),
                        'certificate_expires_at' => $selfReport->expires_at?->toDateString(),
                    ]
                );

                $enrollment->graduateAndIssueCertificate(
                    $actor->id,
                    $selfReport->issued_at?->toDateString(),
                    $selfReport->certificate_number,
                    $selfReport->expires_at?->toDateString()
                );
            }
        });

        $statusLabel = $validated['status'] === 'approved' ? 'DISETUJUI & masuk ke profil resmi' : 'DITOLAK';

        return back()->with('success', "Sertifikat mandiri milik pekerja berhasil diverifikasi ({$statusLabel}).");
    }
}
