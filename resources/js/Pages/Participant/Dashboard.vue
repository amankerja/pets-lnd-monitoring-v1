<script setup>
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import ParticipantLayout from '@/Layouts/ParticipantLayout.vue';
import ExternalTrainingDocumentUpload from '@/Components/ExternalTrainingDocumentUpload.vue';

defineProps({
    enrollments: {
        type: Array,
        default: () => [],
    },
    upcomingSchedules: {
        type: Array,
        default: () => [],
    },
    trainingHistory: {
        type: Array,
        default: () => [],
    },
    availableCourses: {
        type: Array,
        default: () => [],
    },
    recentAttendances: {
        type: Array,
        default: () => [],
    },
    externalDocumentCourses: {
        type: Array,
        default: () => [],
    },
    certificatePrintUrl: {
        type: String,
        default: '',
    },
    totalTrainingHours: {
        type: Number,
        default: 0,
    },
});

const page = usePage();
const expandedEnrollmentId = ref(null);

const toggleExpand = (id) => {
    expandedEnrollmentId.value = expandedEnrollmentId.value === id ? null : id;
};

const requestEnroll = (courseId) => {
    router.post('/participant/courses/enroll', { course_id: courseId }, { preserveScroll: true });
};
</script>

<template>
    <Head title="Portal Belajar Peserta" />

    <ParticipantLayout title="Beranda & Kelas Saya">
        <!-- Quick Action Banner ke Scanner QR (Calm Light Blue Theme) -->
        <div class="bg-blue-50 border border-blue-200 text-slate-800 rounded-xl p-4 mb-3 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-blue-700">Presensi Cepat</div>
                <div class="text-xs font-bold mt-0.5 text-slate-900">Sedang di ruang kelas pelatihan?</div>
                <div class="text-[11px] text-slate-600">Scan QR di proyektor untuk mencatat kehadiran.</div>
            </div>
            <Link
                href="/participant/attendance/scan"
                class="px-3.5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shrink-0 shadow-sm"
            >
                Scan QR &rarr;
            </Link>
        </div>

        <ExternalTrainingDocumentUpload :courses="externalDocumentCourses" />

        <!-- 3-Pill Summary Strip Jam Pelatihan Peserta -->
        <div class="grid grid-cols-3 gap-2.5 mb-5">
            <div class="bg-white border border-slate-200/90 rounded-xl p-3 text-center shadow-sm">
                <div class="text-[9px] font-black uppercase tracking-wider text-slate-500">Jam Pelatihan</div>
                <div class="text-lg font-black text-blue-600 mt-0.5">{{ totalTrainingHours }} <span class="text-[10px] font-bold text-slate-400">Jam</span></div>
            </div>
            <div class="bg-white border border-slate-200/90 rounded-xl p-3 text-center shadow-sm">
                <div class="text-[9px] font-black uppercase tracking-wider text-slate-500">Kelas Aktif</div>
                <div class="text-lg font-black text-slate-800 mt-0.5">{{ enrollments.length }}</div>
            </div>
            <div class="bg-white border border-slate-200/90 rounded-xl p-3 text-center shadow-sm">
                <div class="text-[9px] font-black uppercase tracking-wider text-slate-500">Sertifikat</div>
                <div class="text-lg font-black text-emerald-600 mt-0.5">{{ trainingHistory.length }}</div>
            </div>
        </div>

        <!-- 1. Jadwal Pelatihan Mendatang (Upcoming Schedule) -->
        <div class="mb-5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                Jadwal Pelatihan Mendatang ({{ upcomingSchedules.length }})
            </h2>
            <div v-if="upcomingSchedules.length" class="space-y-2">
                <div
                    v-for="sched in upcomingSchedules"
                    :key="sched.id"
                    class="bg-white border border-slate-200 rounded-md p-3.5 flex items-center justify-between gap-2"
                >
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span class="px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold">
                                {{ sched.course_code }}
                            </span>
                            <span class="text-[11px] font-semibold text-slate-600">{{ sched.session_date }}</span>
                            <span class="text-[10px] text-slate-400">({{ sched.duration_hours }} Jam)</span>
                        </div>
                        <div class="text-xs font-bold text-slate-900 mt-1">{{ sched.title }}</div>
                        <div class="text-[11px] text-slate-500">Instruktur: {{ sched.trainer_name }}</div>
                    </div>
                    <Link
                        v-if="sched.is_active"
                        href="/participant/attendance/scan"
                        class="px-2.5 py-1.5 rounded bg-emerald-600 text-white text-[10px] font-bold uppercase shrink-0"
                    >
                        Sesi Buka
                    </Link>
                    <span
                        v-else
                        class="px-2 py-1 rounded bg-slate-100 text-slate-600 text-[10px] font-bold uppercase shrink-0"
                    >
                        Terjadwal
                    </span>
                </div>
            </div>
            <div v-else class="bg-white border border-slate-200 rounded-md p-4 text-xs text-slate-400 text-center">
                Belum ada sesi pertemuan terjadwal dalam waktu dekat.
            </div>
        </div>

        <!-- 2. Daftar Kelas yang Diikuti & Progress Bar Modul -->
        <div class="mb-5">
            <div class="flex items-center justify-between mb-2.5">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                    Kelas Pelatihan Saya ({{ enrollments.length }})
                </h2>
                <Link href="/participant/profile-matrix" class="text-[11px] font-semibold text-blue-600">
                    Lihat Radar Skill &rarr;
                </Link>
            </div>

            <div v-if="enrollments.length" class="space-y-3">
                <div
                    v-for="item in enrollments"
                    :key="item.id"
                    class="bg-white border border-slate-200 rounded-md p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">
                                {{ item.course.code }}
                            </span>
                            <h3 class="text-xs font-bold text-slate-900 mt-1.5">{{ item.course.title }}</h3>
                            <p class="text-[11px] text-slate-500 mt-0.5">Trainer: {{ item.course.trainer_name }}</p>
                        </div>

                        <span
                            :class="[
                                'px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0',
                                item.status === 'approved'
                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                    : item.status === 'pending'
                                      ? 'bg-amber-50 text-amber-700 border border-amber-200'
                                      : 'bg-rose-50 text-rose-700 border border-rose-200',
                            ]"
                        >
                            {{ item.status }}
                        </span>
                    </div>

                    <!-- Progress Bar Penyelesaian Modul -->
                    <div class="mt-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between text-xs mb-1.5">
                            <span class="text-slate-600 font-medium">
                                Progres Modul: <strong class="text-slate-900">{{ item.completed_count }} / {{ item.total_modules }}</strong>
                            </span>
                            <span class="font-extrabold text-blue-600">{{ item.progress_percentage }}%</span>
                        </div>

                        <!-- Native SVG Progress Bar -->
                        <svg viewBox="0 0 200 8" class="w-full h-2 rounded overflow-hidden">
                            <rect x="0" y="0" width="200" height="8" fill="#e2e8f0" />
                            <rect
                                x="0"
                                y="0"
                                :width="Math.min(200, Math.round((item.progress_percentage / 100) * 200))"
                                height="8"
                                :fill="item.progress_percentage === 100 ? '#10b981' : '#2563eb'"
                            />
                        </svg>

                        <div class="mt-2.5 flex items-center justify-between gap-2">
                            <button
                                type="button"
                                @click="toggleExpand(item.id)"
                                class="text-[11px] font-semibold text-blue-600 hover:underline flex items-center gap-1"
                            >
                                <span>{{ expandedEnrollmentId === item.id ? 'Sembunyikan Rincian Modul' : 'Lihat Rincian Modul' }}</span>
                            </button>

                            <a
                                v-if="item.progress_percentage >= 100"
                                :href="certificatePrintUrl || `/participant/certificate/${page.props.auth?.user?.id}/print`"
                                target="_blank"
                                class="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-700 text-white text-[10px] font-bold"
                            >
                                Unduh Sertifikat Saya &rarr;
                            </a>
                        </div>

                        <!-- Expandable Module List -->
                        <div v-if="expandedEnrollmentId === item.id" class="mt-2.5 space-y-1.5 pt-2 border-t border-slate-100">
                            <div
                                v-for="mod in item.modules"
                                :key="mod.id"
                                class="flex items-center justify-between text-xs py-1 px-2 rounded bg-slate-50"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        :class="[
                                            'w-4 h-4 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0',
                                            mod.is_completed
                                                ? 'bg-emerald-600 text-white'
                                                : 'bg-slate-200 text-slate-500',
                                        ]"
                                    >
                                        {{ mod.is_completed ? '✓' : mod.order_index }}
                                    </span>
                                    <span :class="mod.is_completed ? 'text-slate-800 font-medium' : 'text-slate-500'">
                                        {{ mod.title }}
                                    </span>
                                </div>
                                <span v-if="mod.completed_at" class="text-[10px] text-emerald-700 font-medium shrink-0">
                                    {{ mod.completed_at }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white border border-slate-200 rounded-md p-6 text-center text-xs text-slate-400">
                Anda belum terdaftar di kelas pelatihan mana pun.
            </div>
        </div>

        <!-- 3. Riwayat Pelatihan & Sertifikat Pribadi (Training History) -->
        <div class="mb-5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                Riwayat Pelatihan &amp; Sertifikat ({{ trainingHistory.length }})
            </h2>
            <div v-if="trainingHistory.length" class="space-y-2.5">
                <div
                    v-for="hist in trainingHistory"
                    :key="hist.id"
                    class="bg-white border border-slate-200 rounded-md p-3.5"
                >
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="text-xs font-bold text-slate-900">{{ hist.course.title }}</div>
                            <div class="text-[11px] font-mono text-slate-500 mt-0.5">
                                {{ hist.certificate_number || 'CERT/PANI-LND/2026' }}
                            </div>
                            <div v-if="hist.certificate_expires_at" class="mt-1">
                                <span
                                    :class="[
                                        'px-1.5 py-0.5 rounded text-[10px] font-bold',
                                        hist.is_expiring_soon
                                            ? 'bg-amber-50 text-amber-800 border border-amber-300'
                                            : 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                                    ]"
                                >
                                    Berlaku s/d: {{ hist.certificate_expires_at }}
                                </span>
                            </div>
                        </div>

                        <a
                            :href="certificatePrintUrl || `/participant/certificate/${page.props.auth?.user?.id}/print`"
                            target="_blank"
                            class="px-3 py-1.5 rounded bg-slate-900 hover:bg-slate-800 text-white text-[11px] font-bold shrink-0"
                        >
                            Unduh Sertifikat Saya
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="bg-white border border-slate-200 rounded-md p-4 text-xs text-slate-400 text-center">
                Sertifikat akan tersedia setelah progres modul kelas mencapai 100%.
            </div>
        </div>

        <!-- 4. Katalog Kelas Aktif (Self-Enrollment) -->
        <div v-if="availableCourses.length" class="mb-5">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                Kelas Tersedia untuk Pendaftaran
            </h2>
            <div class="space-y-2.5">
                <div
                    v-for="c in availableCourses"
                    :key="c.id"
                    class="bg-white border border-slate-200 rounded-md p-3.5 flex items-center justify-between gap-3"
                >
                    <div>
                        <span class="text-[10px] font-bold text-slate-500">{{ c.code }}</span>
                        <h3 class="text-xs font-bold text-slate-900">{{ c.title }}</h3>
                        <p class="text-[11px] text-slate-500">{{ c.trainer_name }} &bull; {{ c.modules_count }} Modul</p>
                    </div>
                    <button
                        type="button"
                        @click="requestEnroll(c.id)"
                        class="px-3 py-1.5 rounded bg-slate-900 text-white text-xs font-semibold shrink-0 hover:bg-slate-800"
                    >
                        Daftar
                    </button>
                </div>
            </div>
        </div>

        <!-- 5. Riwayat Presensi Terakhir -->
        <div class="bg-white border border-slate-200 rounded-md p-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 mb-2.5">
                Riwayat Kehadiran Terakhir
            </h2>
            <div v-if="recentAttendances.length" class="divide-y divide-slate-100 text-xs">
                <div
                    v-for="att in recentAttendances"
                    :key="att.id"
                    class="py-2 flex items-center justify-between"
                >
                    <div>
                        <div class="font-bold text-slate-800">{{ att.session_title }}</div>
                        <div class="text-[10px] text-slate-400">{{ att.course_code }} &bull; {{ att.scanned_at }}</div>
                    </div>
                    <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold uppercase">
                        {{ att.status }}
                    </span>
                </div>
            </div>
            <p v-else class="text-xs text-slate-400">Belum ada riwayat presensi.</p>
        </div>
    </ParticipantLayout>
</template>
