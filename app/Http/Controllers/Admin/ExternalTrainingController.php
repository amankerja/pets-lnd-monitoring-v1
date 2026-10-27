<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\ExternalTrainingLogistic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExternalTrainingController extends Controller
{
    public function updateLogistics(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeLogisticsManagement($request);
        abort_unless($course->training_source === 'external', 422, 'Logistik hanya tersedia untuk training eksternal.');

        $validated = $request->validate([
            'external_type' => ['required', Rule::in(['inhouse', 'dinas_luar'])],
            'vendor_name' => ['required', 'string', 'max:150'],
            'pic_training' => ['required', 'string', 'max:100'],
            'training_mode' => ['required', Rule::in(['offline', 'online'])],
            'training_room' => ['nullable', 'string', 'max:100'],
            'training_duration_days' => ['required', 'integer', 'min:1', 'max:365'],
            'training_cost' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'required_documents_schema' => ['nullable', 'array', 'max:50'],
            'required_documents_schema.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'required_documents_schema.*.mandatory' => ['required', 'boolean'],
            'accommodation_checklist' => [
                Rule::requiredIf($request->input('external_type') === 'dinas_luar'),
                'nullable',
                'array',
                'max:20',
            ],
            'accommodation_checklist.*.item' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'accommodation_checklist.*.status' => ['required', Rule::in(['done', 'pending'])],
            'accommodation_checklist.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validated['external_type'] !== 'dinas_luar') {
            $validated['accommodation_checklist'] = null;
        }

        if ($validated['training_mode'] !== 'offline' || $validated['external_type'] !== 'inhouse') {
            $validated['training_room'] = null;
        }

        $course->externalTrainingLogistic()->updateOrCreate([], $validated);

        return back()->with('success', 'Logistik training eksternal berhasil disimpan.');
    }

    public function updateAccommodationStatus(Request $request, Course $course): RedirectResponse
    {
        $this->authorizeLogisticsManagement($request);
        abort_unless($course->training_source === 'external', 422, 'Logistik hanya tersedia untuk training eksternal.');

        $validated = $request->validate([
            'item' => ['required', 'string', 'max:100'],
            'status' => ['required', Rule::in(['done', 'pending'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($course, $validated) {
            $logistics = ExternalTrainingLogistic::query()
                ->where('course_id', $course->id)
                ->lockForUpdate()
                ->firstOrFail();
            abort_unless($logistics->external_type === 'dinas_luar', 422, 'Checklist hanya tersedia untuk training dinas luar.');

            $checklist = $logistics->accommodation_checklist ?? [];
            $index = collect($checklist)->search(
                fn (array $entry) => ($entry['item'] ?? null) === $validated['item']
            );
            abort_if($index === false, 404, 'Item akomodasi tidak ditemukan.');

            $checklist[$index]['status'] = $validated['status'];
            $checklist[$index]['notes'] = $validated['notes'] ?? ($checklist[$index]['notes'] ?? '');
            $logistics->accommodation_checklist = $checklist;
            $logistics->save();
        });

        return back()->with('success', 'Status akomodasi berhasil diperbarui.');
    }

    public function uploadParticipantDocuments(Request $request, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->user_id === $request->user()->id, 404);
        abort_unless($enrollment->status === 'approved', 403, 'Berkas hanya dapat dikirim untuk pendaftaran yang disetujui.');

        $enrollment->load('course.externalTrainingLogistic');
        abort_unless($enrollment->course?->training_source === 'external', 404);
        $schema = $enrollment->course?->externalTrainingLogistic?->required_documents_schema ?? [];
        abort_if($schema === [], 404, 'Kelas ini tidak memiliki persyaratan dokumen.');

        $validated = $request->validate([
            'documents' => ['required', 'array', 'min:1', 'max:50'],
            'documents.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'documents.*.file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $allowedNames = collect($schema)->pluck('name')->all();
        foreach ($validated['documents'] as $document) {
            abort_unless(in_array($document['name'], $allowedNames, true), 422, 'Jenis dokumen tidak sesuai persyaratan kelas.');
        }

        $submittedDocuments = $enrollment->submitted_documents ?? [];
        $requiredNames = collect($schema)
            ->filter(fn (array $document) => $document['mandatory'] ?? false)
            ->pluck('name');
        $missingNames = $requiredNames->reject(
            fn (string $name) => isset($submittedDocuments[$name])
                || collect($validated['documents'])->contains('name', $name)
        );

        if ($missingNames->isNotEmpty()) {
            return back()->withErrors([
                'documents' => 'Berkas wajib belum lengkap: '.$missingNames->implode(', ').'.',
            ]);
        }

        foreach ($validated['documents'] as $index => $document) {
            $submittedDocuments[$document['name']] = $request->file("documents.{$index}.file")
                ->store("external-training-documents/{$enrollment->id}", 'local');
        }

        $enrollment->submitted_documents = $submittedDocuments;
        $enrollment->save();

        return back()->with('success', 'Berkas persyaratan berhasil dikirim.');
    }

    public function downloadParticipantDocument(Request $request, Enrollment $enrollment): StreamedResponse
    {
        $name = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ])['name'];

        $enrollment = Enrollment::query()
            ->accessibleBy($request->user())
            ->with('course.externalTrainingLogistic')
            ->findOrFail($enrollment->id);

        $path = $enrollment->submitted_documents[$name] ?? null;
        abort_unless(
            is_string($path)
                && str_starts_with($path, "external-training-documents/{$enrollment->id}/")
                && Storage::disk('local')->exists($path),
            404,
            'Berkas peserta tidak ditemukan.'
        );

        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $path,
            str($name)->slug('_').'.'.$extension
        );
    }

    private function authorizeLogisticsManagement(Request $request): void
    {
        abort_unless(
            in_array($request->user()->role, ['lnd', 'admin'], true),
            403,
            'Hanya Tim LnD yang berwenang mengelola logistik training.'
        );
    }
}
