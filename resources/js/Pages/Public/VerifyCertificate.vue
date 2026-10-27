<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    result: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head :title="`Verifikasi Sertifikat - ${result.certificate_number}`" />

    <div class="min-h-screen bg-slate-900 text-slate-100 flex flex-col justify-between py-8 px-4 sm:px-6 lg:px-8">
        <!-- Header Resmi Korporat -->
        <div class="max-w-2xl mx-auto w-full text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded bg-blue-600 font-black text-white text-xl shadow-lg">
                PBJ
            </div>
            <h1 class="mt-3 text-lg sm:text-xl font-black uppercase tracking-tight text-white">
                PT PANI BERSAMA JAYA
            </h1>
            <p class="text-xs text-blue-400 font-bold uppercase tracking-widest mt-0.5">
                Portal Resmi Verifikasi Keaslian Sertifikat &amp; Dokumen Pelatihan (L&amp;D)
            </p>
        </div>

        <!-- Main Card Konten Verifikasi -->
        <div class="max-w-2xl mx-auto w-full mt-6">
            <div class="bg-white text-slate-900 rounded-xl shadow-2xl border border-slate-200 overflow-hidden">
                <!-- Status Banner Atas -->
                <div
                    :class="[
                        'p-6 text-center border-b',
                        !result.is_found
                            ? 'bg-slate-100 border-slate-300 text-slate-700'
                            : result.is_expired
                            ? 'bg-rose-50 border-rose-200 text-rose-900'
                            : 'bg-emerald-50 border-emerald-200 text-emerald-950',
                    ]"
                >
                    <div
                        :class="[
                            'w-16 h-16 rounded-full mx-auto flex items-center justify-center text-3xl font-black mb-3 shadow-inner',
                            !result.is_found
                                ? 'bg-slate-200 text-slate-500'
                                : result.is_expired
                                ? 'bg-rose-100 text-rose-600'
                                : 'bg-emerald-100 text-emerald-600',
                        ]"
                    >
                        <span v-if="!result.is_found">?</span>
                        <span v-else-if="result.is_expired">!</span>
                        <span v-else>✓</span>
                    </div>

                    <div
                        :class="[
                            'inline-block px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider',
                            !result.is_found
                                ? 'bg-slate-200 text-slate-800'
                                : result.is_expired
                                ? 'bg-rose-200 text-rose-900'
                                : 'bg-emerald-200 text-emerald-900',
                        ]"
                    >
                        {{ result.status_label }}
                    </div>

                    <h2 class="mt-2 text-base sm:text-lg font-black tracking-tight">
                        {{ result.is_found ? result.course_title : 'Sertifikat Tidak Terdaftar' }}
                    </h2>
                    <p class="text-xs text-slate-600 mt-1 max-w-lg mx-auto leading-relaxed">
                        {{ result.status_description }}
                    </p>
                </div>

                <!-- Detail Informasi Sertifikat (Hanya jika ditemukan) -->
                <div v-if="result.is_found" class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-4 border-b border-slate-200">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Nomor Dokumen Sertifikat
                            </div>
                            <div class="text-sm font-mono font-black text-blue-700 mt-0.5">
                                {{ result.certificate_number }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Digital Security Fingerprint (Hash)
                            </div>
                            <div class="text-xs font-mono font-bold text-slate-700 mt-0.5">
                                {{ result.digital_fingerprint }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Nama Lengkap Pemegang
                            </div>
                            <div class="text-sm font-black text-slate-900 mt-0.5">
                                {{ result.holder_name }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Nomor Induk Karyawan (NIK)
                            </div>
                            <div class="text-sm font-mono font-bold text-slate-700 mt-0.5">
                                {{ result.holder_nik_masked }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Unit / Perusahaan Pemilik
                            </div>
                            <div class="font-bold text-slate-900 mt-0.5">
                                {{ result.organization_name }}
                            </div>
                            <div class="text-[10px] text-slate-500">{{ result.organization_type }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Kode Program Kursus
                            </div>
                            <div class="font-mono font-bold text-slate-800 mt-0.5">
                                {{ result.course_code }}
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Tanggal Diterbitkan
                            </div>
                            <div class="font-semibold text-slate-800 mt-0.5">
                                {{ result.issued_at }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                Masa Berlaku Hingga
                            </div>
                            <div
                                :class="result.is_expired ? 'text-rose-600 font-black' : 'text-emerald-700 font-bold'"
                                class="mt-0.5"
                            >
                                {{ result.expires_at }}
                            </div>
                        </div>
                    </div>

                    <!-- Footer Waktu Pengecekan -->
                    <div class="mt-4 pt-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-400 gap-2">
                        <div>Penerbit: <strong>HC &amp; Learning Development PBJ</strong></div>
                        <div>Diverifikasi pada: {{ result.verified_at }}</div>
                    </div>
                </div>

                <!-- Tampilan Jika Nomor Tidak Ditemukan -->
                <div v-else class="p-6 text-center text-xs space-y-3">
                    <p class="text-slate-600">
                        Nomor sertifikat <strong class="font-mono text-slate-900">{{ result.certificate_number }}</strong> tidak ditemukan dalam basis data sistem.
                    </p>
                    <p class="text-[11px] text-slate-400">
                        Pastikan URL atau kode QR yang Anda pindai tidak rusak, atau hubungi Learning &amp; Development Department untuk konfirmasi manual.
                    </p>
                </div>
            </div>

            <!-- Jaminan Keamanan Sistem -->
            <div class="mt-4 p-3 rounded-lg bg-slate-800/80 border border-slate-700 text-center text-[11px] text-slate-400">
                🔒 Sistem verifikasi digital ini terhubung langsung secara real-time ke Enterprise Competency Database L&amp;D PT Pani Bersama Jaya.
            </div>

            <!-- Tombol Kembali / Login -->
            <div class="mt-6 text-center">
                <Link
                    href="/login"
                    class="inline-flex items-center gap-2 text-xs font-bold text-blue-400 hover:text-blue-300"
                >
                    &larr; Masuk ke Sistem L&amp;D Management
                </Link>
            </div>
        </div>

        <!-- Footer Hak Cipta -->
        <div class="max-w-2xl mx-auto w-full text-center text-[10px] text-slate-500 pt-8">
            &copy; {{ new Date().getFullYear() }} Learning &amp; Development Department &bull; PT Pani Bersama Jaya. All rights reserved.
        </div>
    </div>
</template>

