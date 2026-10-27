<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PwaInstallBanner from '@/Components/PwaInstallBanner.vue';

defineProps({
    title: {
        type: String,
        default: 'Portal Peserta',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);
const isMobileDrawerOpen = ref(false);

const bottomNav = [
    {
        name: 'Home & Jadwal',
        href: '/participant/dashboard',
        matches: ['/participant/dashboard', '/m/home'],
        icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
    },
    {
        name: 'Self-Report',
        href: '/participant/self-reports',
        matches: ['/participant/self-reports'],
        icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    },
    {
        name: 'Scan QR',
        href: '/participant/attendance/scan',
        matches: ['/participant/attendance/scan', '/m/scan-qr'],
        icon: 'M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z',
    },
    {
        name: 'Profil Skill',
        href: '/participant/profile-matrix',
        matches: ['/participant/profile-matrix', '/m/skill-profile'],
        icon: 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
    },
];

// Struktur Accordion Menu Khusus Role `peserta` (Sesuai 5 Kategori Utama)
const participantGroups = [
    {
        label: '1. DASHBOARD',
        items: [
            { name: 'Training Hours & Jadwal Pelatihan', href: '/participant/dashboard', badge: null },
        ],
    },
    {
        label: '2. TRAINING',
        items: [
            { name: 'Riwayat Training & Self-Report Sertifikat', href: '/participant/self-reports', badge: null },
        ],
    },
    {
        label: '3. COMPETENCY',
        items: [
            { name: 'Profil Radar Kompetensi & Gap Jabatan', href: '/participant/profile-matrix', badge: null },
        ],
    },
    {
        label: '5. TRAINING TODAY (HARI INI)',
        items: [
            { name: 'Scan Barcode / QR Absen Kamera HP', href: '/participant/attendance/scan', badge: 'LIVE' },
            { name: 'Pre-Test & Post-Test Hari Ini', href: '/training-today?tab=pre_post_test', badge: 'LIVE' },
        ],
    },
];

const isActive = (matches) => {
    return matches.some((m) => page.url.startsWith(m));
};
</script>

<template>
    <div class="bg-slate-200 min-h-screen">
        <!-- Mobile Viewport Container -->
        <div class="max-w-md mx-auto min-h-screen bg-slate-50 border-x border-slate-200 flex flex-col relative pb-20">
            <!-- Banner Otomatis Install PWA (Android & iOS) -->
            <PwaInstallBanner />

            <!-- Compact Sticky Header -->
            <header class="sticky top-0 z-20 bg-white border-b border-slate-200 px-4 py-3 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        @click="isMobileDrawerOpen = !isMobileDrawerOpen"
                        class="p-1.5 rounded border border-slate-200 text-slate-700 hover:bg-slate-100"
                        title="Menu Navigasi Peserta"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-wider text-blue-600">L&amp;D Mobile Portal</div>
                        <h1 class="text-sm font-bold text-slate-900 truncate">{{ title }}</h1>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-xs font-semibold text-slate-800 truncate max-w-[130px]">{{ user.name || 'Peserta' }}</div>
                    <div class="text-[10px] text-slate-500">{{ user.nik || 'PANI-0101' }} &bull; {{ user.department || 'Operations' }}</div>
                </div>
            </header>

            <!-- Collapsible Accordion Drawer (Clean Modern Light Theme - Tidak Hitam) -->
            <div v-if="isMobileDrawerOpen" class="bg-white px-4 py-3 border-b border-slate-200 shadow-md space-y-2.5 text-xs">
                <div v-for="grp in participantGroups" :key="grp.label" class="space-y-1">
                    <div class="text-[10px] font-black uppercase tracking-wider text-blue-600">{{ grp.label }}</div>
                    <Link
                        v-for="it in grp.items"
                        :key="it.name"
                        :href="it.href"
                        @click="isMobileDrawerOpen = false"
                        class="flex items-center justify-between py-2 px-2.5 rounded-lg bg-slate-50 hover:bg-blue-50 text-slate-700 hover:text-blue-700 border border-slate-200/80 font-semibold transition-colors"
                    >
                        <span>{{ it.name }}</span>
                        <span
                            v-if="it.badge"
                            class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-300 text-[9px] font-extrabold uppercase"
                        >
                            {{ it.badge }}
                        </span>
                    </Link>
                </div>
            </div>

            <!-- Flash Messages -->
            <div v-if="flashSuccess || flashError" class="px-4 pt-3">
                <div
                    v-if="flashSuccess"
                    class="p-2.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-medium"
                >
                    {{ flashSuccess }}
                </div>
                <div
                    v-if="flashError"
                    class="p-2.5 rounded bg-rose-50 border border-rose-200 text-rose-800 text-xs font-medium"
                >
                    {{ flashError }}
                </div>
            </div>

            <!-- Main Mobile Content -->
            <main class="flex-1 p-4">
                <slot />
            </main>

            <!-- Fixed Bottom Navigation Bar -->
            <nav class="fixed bottom-0 left-1/2 -translate-x-1/2 w-full max-w-md bg-white border-t border-slate-200 z-30">
                <div class="grid grid-cols-4 h-16">
                    <Link
                        v-for="item in bottomNav"
                        :key="item.name"
                        :href="item.href"
                        :class="[
                            'flex flex-col items-center justify-center gap-1 text-[11px] font-medium transition-colors',
                            isActive(item.matches)
                                ? 'text-blue-600 font-semibold bg-blue-50/40'
                                : 'text-slate-500 hover:text-slate-900',
                        ]"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                        </svg>
                        <span>{{ item.name }}</span>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
