<?php

namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\SelfReportedTraining;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCertificateVerificationController extends Controller
{
    /**
     * Endpoint Publik untuk Verifikasi Keaslian Sertifikat & Rapor Pelatihan
     * Terbuka untuk umum tanpa login (diakses saat scan QR Code).
     */
    public function verify(Request $request, string $certificate_number): Response
    {
        $cleanCertNumber = trim(urldecode($certificate_number));

        // 1. Cari di tabel Riwayat Pelatihan Resmi (Enrollments)
        $enrollment = Enrollment::query()
            ->where('certificate_number', $cleanCertNumber)
            ->where('status', 'approved')
            ->with([
                'user:id,name,nik,department,organization_id',
                'user.organization:id,code,name,type',
                'course:id,code,title,validity_years',
            ])
            ->first();

        // 2. Jika tidak ada di enrollments, cari di Self-Reported Trainings yang sudah approved
        $selfReport = null;
        if (!$enrollment) {
            $selfReport = SelfReportedTraining::query()
                ->where('certificate_number', $cleanCertNumber)
                ->where('status', 'approved')
                ->with([
                    'user:id,name,nik,department,organization_id',
                    'user.organization:id,code,name,type',
                ])
                ->first();
        }

        if (!$enrollment && !$selfReport) {
            return Inertia::render('Public/VerifyCertificate', [
                'result' => [
                    'is_found' => false,
                    'is_valid' => false,
                    'is_expired' => false,
                    'status_label' => 'TIDAK DITEMUKAN',
                    'status_description' => 'Nomor sertifikat tidak terdaftar di pangkalan data resmi L&D PT Pani Bersama Jaya.',
                    'certificate_number' => $cleanCertNumber,
                ],
            ]);
        }

        $user = $enrollment ? $enrollment->user : $selfReport->user;
        $courseTitle = $enrollment ? $enrollment->course?->title : $selfReport->course_name;
        $courseCode = $enrollment ? $enrollment->course?->code : 'EXT-CERT';
        $issuedAt = $enrollment ? $enrollment->certificate_issued_at : $selfReport->issued_at;
        $expiresAt = $enrollment ? $enrollment->certificate_expires_at : $selfReport->expires_at;

        $expiresAtCarbon = $expiresAt ? Carbon::parse($expiresAt) : null;
        $isExpired = $expiresAtCarbon ? $expiresAtCarbon->isPast() : false;
        $isSuperseded = $enrollment ? (bool) $enrollment->is_superseded : false;

        // Sensor NIK untuk privasi (contoh: PANI-0101 -> PAN***01)
        $nik = $user?->nik ?? '-';
        $maskedNik = strlen($nik) > 5
            ? substr($nik, 0, 3) . '***' . substr($nik, -2)
            : '***';

        $orgName = $user?->organization?->name ?? ($user?->department ?? 'PT Pani Bersama Jaya');
        $orgType = $user?->organization?->type ?? 'internal_dept';

        // Digital Signature Hash
        $digitalFingerprint = strtoupper(substr(hash('sha256', "{$cleanCertNumber}:{$nik}:PANI-LND-AUTHENTIC"), 0, 16));

        $statusLabel = match (true) {
            $isSuperseded => 'SUPERSEDED / TELAH DIPERBARUI',
            $isExpired => 'EXPIRED / PERLU PEMBARUAN',
            default => 'VALID & AKTIF RESMI',
        };

        $statusDescription = match (true) {
            $isSuperseded => 'Sertifikat lama ini telah digantikan (diperbarui) oleh sertifikat Refreshment yang lebih baru.',
            $isExpired => 'Masa berlaku sertifikat telah habis. Pekerja disarankan mengikuti pelatihan penyegaran (Refreshment Training).',
            default => 'Dokumen ini sah, terdaftar, dan diterbitkan secara resmi oleh Divisi Human Capital & Learning Development.',
        };

        return Inertia::render('Public/VerifyCertificate', [
            'result' => [
                'is_found' => true,
                'is_valid' => !$isExpired && !$isSuperseded,
                'is_expired' => $isExpired,
                'is_superseded' => $isSuperseded,
                'status_label' => $statusLabel,
                'status_description' => $statusDescription,
                'certificate_number' => $cleanCertNumber,
                'digital_fingerprint' => $digitalFingerprint,
                'holder_name' => $user?->name ?? '-',
                'holder_nik_masked' => $maskedNik,
                'organization_name' => $orgName,
                'organization_type' => $orgType === 'contractor' ? 'Mitra Kontraktor' : 'Departemen Internal',
                'course_title' => $courseTitle,
                'course_code' => $courseCode,
                'issued_at' => $issuedAt ? Carbon::parse($issuedAt)->translatedFormat('d F Y') : '-',
                'expires_at' => $expiresAt ? Carbon::parse($expiresAt)->translatedFormat('d F Y') : 'Seumur Hidup / Tidak Terbatas',
                'verified_at' => now()->translatedFormat('d F Y H:i:s T'),
            ],
        ]);
    }
}

