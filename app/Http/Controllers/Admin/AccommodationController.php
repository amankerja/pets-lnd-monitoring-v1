<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExternalTrainingLogistic;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccommodationController extends Controller
{
    /**
     * Tampilkan Halaman Akomodasi & Process Training Eksternal.
     * Diproteksi ketat hanya untuk role: `lnd` dan `trainer`.
     */
    public function index(Request $request): Response
    {
        $courses = Course::query()
            ->where('training_source', 'external')
            ->with(['externalTrainingLogistic'])
            ->latest('start_date')
            ->get();

        return Inertia::render('UnderConstruction', [
            'title' => 'Akomodasi / Process Training',
            'subtitle' => 'Pusat Manajemen Logistik, Checklist Akomodasi, Tiket & Berkas Perjalanan Dinas Pelatihan Eksternal',
            'section' => 'Training Administration',
            'description' => 'Modul ini mengelola data vendor eksternal, PIC training, akomodasi hotel, penerbangan PP, serta persetujuan dokumen prasyarat peserta.',
            'data' => [
                'external_courses_count' => $courses->count(),
            ],
        ]);
    }
}

