<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SvgBarChart from '@/Components/Charts/SvgBarChart.vue';

defineProps({
    summary: {
        type: Object,
        default: () => ({
            teaching_hours: 0,
            planned_hours: 0,
            verified_sessions_count: 0,
            total_sessions_count: 0,
            assigned_courses_count: 0,
            competencies_count: 0,
        }),
    },
    sessions: {
        type: Array,
        default: () => [],
    },
    assignedCourses: {
        type: Array,
        default: () => [],
    },
    competencies: {
        type: Array,
        default: () => [],
    },
    hoursByCourseChart: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Head title="Jam Mengajar & Jadwal Trainer" />

    <AdminLayout
        title="Portal Instruktur: Jam Mengajar &amp; Kompetensi"
        subtitle="Akumulasi jam mengajar dihitung otomatis dari sesi absensi terverifikasi dengan minimal satu peserta hadir (Anti-Klaim Sesi Kosong)."
    >
        <div class="space-y-6">
            <!-- ============================================================= -->
            <!-- 1. KPI SUMMARY CARDS                                           -->
            <!-- ============================================================= -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                <!-- Card 1: Total Jam Mengajar Sah -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex h-full flex-col justify-between p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-blue-50 text-blue-700 border border-blue-200/80">
                                Jam Mengajar Sah
                            </span>
                            <div class="w-7 h-7 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-3xl font-black text-blue-600 tracking-tight flex items-baseline gap-1.5">
                                <span>{{ summary.teaching_hours }}</span>
                                <span class="text-xs font-bold text-slate-500">Jam Terverifikasi</span>
                            </div>
                            <p class="mt-1 text-[11px] font-medium text-slate-500">
                                Dari rencana total {{ summary.planned_hours }} jam sesi
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Sesi Absensi Terlaksana -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex h-full flex-col justify-between p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                Sesi Terlaksana
                            </span>
                            <div class="w-7 h-7 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-3xl font-black text-emerald-600 tracking-tight flex items-baseline gap-1.5">
                                <span>{{ summary.verified_sessions_count }} / {{ summary.total_sessions_count }}</span>
                                <span class="text-xs font-bold text-slate-500">Sesi Sah</span>
                            </div>
                            <p class="mt-1 text-[11px] font-medium text-slate-500">
                                Memiliki &ge; 1 peserta berstatus Hadir
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Kelas Ditugaskan -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex h-full flex-col justify-between p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                                Program Ditugaskan
                            </span>
                            <div class="w-7 h-7 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-3xl font-black text-slate-900 tracking-tight flex items-baseline gap-1.5">
                                <span>{{ summary.assigned_courses_count }}</span>
                                <span class="text-xs font-bold text-slate-500">Program</span>
                            </div>
                            <p class="mt-1 text-[11px] font-medium text-slate-500">
                                Scope akses terbatas pada kelas Anda
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Kompetensi Mengajar Aktif -->
                <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex h-full flex-col justify-between p-4">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider bg-indigo-50 text-indigo-700 border border-indigo-200/80">
                                Lisensi Kompetensi
                            </span>
                            <div class="w-7 h-7 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                                </svg>
                            </div>
                        </div>
                        <div class="mt-4">
                            <div class="text-3xl font-black text-indigo-600 tracking-tight flex items-baseline gap-1.5">
                                <span>{{ summary.competencies_count }}</span>
                                <span class="text-xs font-bold text-slate-500">Materi Aktif</span>
                            </div>
                            <p class="mt-1 text-[11px] font-medium text-slate-500">
                                Matriks otorisasi instruktur LnD
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 2. GRAFIK JAM MENGAJAR & TABEL KOMPETENSI                    -->
            <!-- ============================================================= -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <!-- Grafik Distribusi Jam Mengajar -->
                <div class="lg:col-span-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="h-full flex flex-col justify-between p-5 sm:p-6">
                        <div>
                            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                                <div>
                                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">Distribusi Jam Mengajar Sah</h2>
                                    <p class="text-[11px] text-slate-500 mt-0.5">Akumulasi duration_hours dari sesi absensi terverifikasi</p>
                                </div>
                                <Link
                                    href="/admin/attendance-sessions"
                                    class="group inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 text-blue-700 hover:bg-blue-600 hover:text-white border border-blue-200 text-xs font-bold transition-all shadow-sm"
                                >
                                    <span>Layar QR</span>
                                    <span class="group-hover:translate-x-0.5 transition-transform">&rarr;</span>
                                </Link>
                            </div>

                            <SvgBarChart
                                v-if="hoursByCourseChart.length"
                                :items="hoursByCourseChart"
                                unit=" Jam"
                                bar-color="#2563eb"
                            />
                            <div v-else class="py-16 text-center text-xs text-slate-400">
                                Belum ada sesi mengajar yang memiliki kehadiran peserta.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabel Kompetensi Mengajar Instruktur -->
                <div class="lg:col-span-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="h-full p-5 sm:p-6">
                        <div class="pb-3 border-b border-slate-100 mb-4">
                            <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">Kompetensi Mengajar Instruktur</h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">Skill dan materi sertifikasi resmi yang berhak Anda ajarkan</p>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="border-b border-slate-200/80 bg-slate-50/80 text-slate-500 uppercase text-[10px] font-black tracking-wider">
                                        <th class="py-2.5 px-3">Kode Skill</th>
                                        <th class="py-2.5 px-3">Materi / Kompetensi</th>
                                        <th class="py-2.5 px-3">No. Sertifikasi</th>
                                        <th class="py-2.5 px-3 text-right">Masa Berlaku</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="comp in competencies" :key="comp.id" class="hover:bg-slate-50/80 transition-colors">
                                        <td class="py-3 px-3 font-mono font-black text-blue-700 bg-blue-50/50 rounded inline-block my-1 border border-blue-200">{{ comp.skill_code }}</td>
                                        <td class="py-3 px-3">
                                            <div class="font-bold text-slate-900">{{ comp.skill_name }}</div>
                                            <div class="text-[10px] text-slate-400 font-medium">{{ comp.skill_category }}</div>
                                        </td>
                                        <td class="py-3 px-3 font-mono text-slate-600 font-semibold">{{ comp.certification_no }}</td>
                                        <td class="py-3 px-3 text-right">
                                            <span
                                                :class="[
                                                    'px-2.5 py-0.5 rounded-full text-[10px] font-black border',
                                                    comp.is_expiring_soon
                                                        ? 'bg-amber-50 text-amber-800 border-amber-300'
                                                        : 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                ]"
                                            >
                                                {{ comp.valid_until }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr v-if="!competencies.length">
                                        <td colspan="4" class="py-12 text-center text-slate-400">
                                            Belum ada data lisensi kompetensi mengajar yang terdaftar.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ============================================================= -->
            <!-- 3. LEDGER SESI ABSENSI & TEACHING HOURS                       -->
            <!-- ============================================================= -->
            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="pb-3 border-b border-slate-100 mb-4">
                        <h2 class="text-xs font-black uppercase tracking-wider text-slate-900">Ledger Sesi Mengajar &amp; Validasi Teaching Hours</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Sesi tanpa peserta hadir (0 Hadir) tidak dihitung ke dalam klaim jam mengajar instruktur.
                        </p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="border-b border-slate-200/80 bg-slate-50/80 text-slate-500 uppercase text-[10px] font-black tracking-wider">
                                    <th class="py-3 px-3">Tanggal Sesi</th>
                                    <th class="py-3 px-3">Kelas Pelatihan</th>
                                    <th class="py-3 px-3">Topik Sesi Pertemuan</th>
                                    <th class="py-3 px-3 text-center">Durasi Sesi</th>
                                    <th class="py-3 px-3 text-center">Peserta Hadir</th>
                                    <th class="py-3 px-3 text-right">Jam Diakui (Ledger)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="s in sessions" :key="s.id" class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-3 font-mono font-bold text-slate-700 whitespace-nowrap">{{ s.session_date }}</td>
                                    <td class="py-3 px-3">
                                        <span class="px-2 py-0.5 rounded-md bg-blue-50 text-blue-700 border border-blue-200 font-mono text-[10px] font-bold">
                                            {{ s.course_code }}
                                        </span>
                                        <span class="ml-2 font-bold text-slate-900">{{ s.course_title }}</span>
                                    </td>
                                    <td class="py-3 px-3 font-medium text-slate-800">{{ s.title }}</td>
                                    <td class="py-3 px-3 text-center font-mono font-bold text-slate-900">{{ s.duration_hours }} Jam</td>
                                    <td class="py-3 px-3 text-center">
                                        <span
                                            :class="[
                                                'px-2.5 py-0.5 rounded-full text-[10px] font-black border',
                                                s.present_count > 0
                                                    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                    : 'bg-slate-100 text-slate-500 border-slate-200',
                                            ]"
                                        >
                                            {{ s.present_count }} Peserta Hadir
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <span
                                            v-if="s.is_verified_hours"
                                            class="inline-flex items-center px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-black text-[11px] border border-emerald-200"
                                        >
                                            +{{ s.credited_hours }} Jam (SAH)
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 font-semibold text-[10px]"
                                        >
                                            0.0 Jam (Belum Ada Hadir)
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="!sessions.length">
                                    <td colspan="6" class="py-12 text-center text-slate-400">
                                        Belum ada sesi absensi yang tercatat untuk kelas Anda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
