<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        default: 'Dashboard L&D',
    },
    subtitle: {
        type: String,
        default: '',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});

// Normalisasi role 'admin' (legacy) menjadi 'lnd'
const userRole = computed(() => {
    const rawRole = user.value?.role || 'lnd';
    return rawRole === 'admin' ? 'lnd' : rawRole;
});

const isTrainerUser = computed(() => {
    return userRole.value === 'trainer' || Boolean(user.value?.is_trainer);
});

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

const toasts = ref([]);
let toastIdCounter = 0;

const removeToast = (id) => {
    toasts.value = toasts.value.filter((t) => t.id !== id);
};

const addToast = (type, message) => {
    if (!message) return;
    const id = ++toastIdCounter;
    toasts.value.push({ id, type, message });

    setTimeout(() => {
        removeToast(id);
    }, 5500);
};

watch(
    () => page.props.flash?.success,
    (val) => {
        if (val) addToast('success', val);
    },
    { immediate: true }
);

watch(
    () => page.props.flash?.error,
    (val) => {
        if (val) addToast('error', val);
    },
    { immediate: true }
);

watch(
    () => page.props.flash?.info,
    (val) => {
        if (val) addToast('info', val);
    },
    { immediate: true }
);

const isSidebarCollapsed = ref(false);
const isMobileDrawerOpen = ref(false);

watch(
    () => page.url,
    () => {
        isMobileDrawerOpen.value = false;
    }
);

/**
 * BLUEPRINT HIERARKI SIDEBAR TERBARU (7 GRUP UTAMA)
 * 1. DASHBOARD & OVERVIEW
 * 2. TRAINER
 * 3. TRAINING ADMINISTRATION
 * 4. TRAINING PROGRAM
 * 5. TRAINING COMPETENCY
 * 6. DOCUMENT MANAGEMENT SYSTEM
 * 7. REPORTING
 */
const sidebarGroupsConfig = computed(() => [
    {
        id: 'dashboard_overview',
        label: 'DASHBOARD & OVERVIEW',
        items: [
            {
                name: 'Training Program Dashboard (Internal, Induksi, Refresh)',
                href: '/dashboard/training-program',
                match: '/dashboard/training-program',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: 'OVERVIEW',
                icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            },
            {
                name: 'Simulator Dashboard (Refresh, New-Hire)',
                href: '/dashboard/simulator',
                match: '/dashboard/simulator',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: 'SIM',
                icon: 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            },
            {
                name: 'Training Competency Dashboard (Gap, Dept Competency, Dept Progress)',
                href: '/dashboard/competency',
                match: '/dashboard/competency',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: 'GAP',
                icon: 'M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z',
            },
        ],
    },
    {
        id: 'trainer',
        label: 'TRAINER',
        items: [
            {
                name: 'Kelola Semua Trainer',
                href: '/admin/trainers',
                match: '/admin/trainers',
                roles: ['lnd'],
                badge: '360°',
                icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
            },
            {
                name: 'Training Hours',
                href: '/trainer/hours',
                match: '/trainer/hours',
                roles: ['trainer', 'lnd'],
                badge: 'HOURS',
                icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
            },
            {
                name: 'Trainer Schedule',
                href: '/trainer/schedule',
                match: '/trainer/schedule',
                roles: ['trainer', 'lnd'],
                badge: null,
                icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            },
            {
                name: 'Trainer Programs',
                href: '/trainer/programs',
                match: '/trainer/programs',
                roles: ['trainer', 'lnd'],
                badge: null,
                icon: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253',
            },
            {
                name: "Trainer To'do",
                href: '/trainer/todos',
                match: '/trainer/todos',
                roles: ['trainer', 'lnd'],
                badge: null,
                icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
            },
        ],
    },
    {
        id: 'training_administration',
        label: 'TRAINING ADMINISTRATION',
        items: [
            {
                name: 'Pendaftaran Training',
                href: '/training-administration/registration',
                match: '/training-administration/registration',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',
            },
            {
                name: 'Akomodasi / Process Training',
                href: '/training-administration/accommodation',
                match: '/training-administration/accommodation',
                roles: ['lnd', 'trainer'],
                badge: 'RESTRICTED',
                icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
            },
        ],
    },
    {
        id: 'training_program',
        label: 'TRAINING PROGRAM',
        items: [
            {
                name: 'Topik & Program Pelatihan (Training Topics)',
                href: '/training-program/courses',
                match: '/training-program/courses',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: 'TOPICS',
                icon: 'M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10',
            },
            {
                name: 'Jadwal Training (Kalender)',
                href: '/training-program/schedules',
                match: '/training-program/schedules',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: 'CALENDAR',
                icon: 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            },
        ],
    },
    {
        id: 'training_competency',
        label: 'TRAINING COMPETENCY',
        items: [
            {
                name: 'Training Matrix',
                href: '/competency/training-matrix',
                match: '/competency/training-matrix',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z',
            },
            {
                name: 'Competency Matrix',
                href: '/competency/matrix',
                match: '/competency/matrix',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2',
            },
            {
                name: 'TNA & Individu Checkup Competency',
                href: '/competency/individual-view',
                match: '/competency/individual-view',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: 'TNA',
                icon: 'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2',
            },
            {
                name: 'Position x Competency',
                href: '/competency/position-standards',
                match: '/competency/position-standards',
                roles: ['lnd', 'dept_admin', 'contractor_admin'],
                badge: userRole.value === 'lnd' ? 'CRUD' : 'RO',
                icon: 'M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4',
            },
        ],
    },
    {
        id: 'document_management',
        label: 'DOCUMENT MANAGEMENT SYSTEM',
        items: [
            {
                name: 'Arsip',
                href: '/document-management/archive',
                match: '/document-management/archive',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4',
            },
        ],
    },
    {
        id: 'reporting',
        label: 'REPORTING',
        items: [
            {
                name: 'Training Report',
                href: '/reporting/training',
                match: '/reporting/training',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            },
            {
                name: 'Simulator Report',
                href: '/reporting/simulator',
                match: '/reporting/simulator',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            },
            {
                name: 'Competency Report',
                href: '/reporting/competency',
                match: '/reporting/competency',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
            },
            {
                name: 'Trainers Report',
                href: '/reporting/trainers',
                match: '/reporting/trainers',
                roles: ['lnd', 'trainer'],
                badge: null,
                icon: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z',
            },
            {
                name: 'Document Report',
                href: '/reporting/documents',
                match: '/reporting/documents',
                roles: ['lnd', 'trainer', 'dept_admin', 'contractor_admin'],
                badge: null,
                icon: 'M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h3.172a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20',
            },
        ],
    },
]);

// Filter grup dan item sesuai role user (atau jika user memiliki flag is_trainer)
const visibleSidebarGroups = computed(() => {
    return sidebarGroupsConfig.value
        .map((group) => ({
            ...group,
            items: group.items.filter((item) => {
                if (item.roles.includes(userRole.value)) return true;
                if (isTrainerUser.value && item.roles.includes('trainer')) return true;
                return false;
            }),
        }))
        .filter((group) => group.items.length > 0);
});

const openGroups = reactive({
    dashboard_overview: true,
    trainer: true,
    training_administration: true,
    training_program: true,
    training_competency: true,
    document_management: true,
    reporting: true,
});

const toggleGroup = (groupId) => {
    openGroups[groupId] = !openGroups[groupId];
};

const isActive = (matchUrl) => {
    const fullUrl = page.url || '';
    const currentPath = fullUrl.split('?')[0];

    // Alias mapping untuk route multi-endpoint
    if (matchUrl === '/dashboard/training-program' && (currentPath === '/dashboard' || currentPath === '/dashboard/training-program')) {
        return true;
    }
    if (matchUrl === '/training-program/schedules' && (currentPath === '/training-program/schedules' || currentPath === '/training/calendar')) {
        return true;
    }
    if (matchUrl === '/training-administration/registration' && (currentPath === '/training-administration/registration' || currentPath === '/training/administration' || currentPath === '/admin/enrollments')) {
        return true;
    }
    if (matchUrl === '/competency/training-matrix' && (currentPath === '/competency/training-matrix' || currentPath === '/admin/skill-matrix')) {
        return true;
    }

    if (matchUrl.includes('?')) {
        return fullUrl === matchUrl;
    }
    return currentPath === matchUrl || (matchUrl !== '/' && currentPath.startsWith(matchUrl + '/'));
};

const getBadgeStyle = (badge, active) => {
    if (active) {
        return 'bg-white/20 text-white border-white/30';
    }
    switch (badge) {
        case 'LIVE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200/90 ring-1 ring-emerald-500/20';
        case 'RESTRICTED':
            return 'bg-rose-50 text-rose-700 border-rose-200/90 ring-1 ring-rose-500/20';
        case 'OVERVIEW':
        case 'MASTER':
        case '360°':
            return 'bg-blue-50 text-blue-700 border-blue-200/90 ring-1 ring-blue-500/20';
        case 'HOURS':
        case 'SIM':
            return 'bg-indigo-50 text-indigo-700 border-indigo-200/90 ring-1 ring-indigo-500/20';
        case 'GAP':
        case 'TNA':
            return 'bg-amber-50 text-amber-700 border-amber-200/90 ring-1 ring-amber-500/20';
        case 'CALENDAR':
            return 'bg-sky-50 text-sky-700 border-sky-200/90 ring-1 ring-sky-500/20';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200/80 font-mono';
    }
};

const roleBadgeMeta = computed(() => {
    switch (userRole.value) {
        case 'lnd':
            return {
                label: 'SUPER ADMIN L&D (GLOBAL)',
                classes: 'bg-blue-50 text-blue-700 border-blue-200',
            };
        case 'trainer':
            return {
                label: 'TRAINER / INSTRUKTUR',
                classes: 'bg-indigo-50 text-indigo-700 border-indigo-200',
            };
        case 'dept_admin':
            return {
                label: 'ADMIN DEPARTEMEN INTERNAL',
                classes: 'bg-emerald-50 text-emerald-700 border-emerald-200',
            };
        case 'contractor_admin':
            return {
                label: 'ADMIN PT KONTRAKTOR',
                classes: 'bg-amber-50 text-amber-800 border-amber-300',
            };
        default:
            return {
                label: userRole.value.toUpperCase(),
                classes: 'bg-slate-100 text-slate-700 border-slate-200',
            };
    }
});
</script>

<template>
    <div class="min-h-screen w-full bg-slate-50/70 text-slate-800 flex">
        <!-- Backdrop Mobile Drawer -->
        <transition
            enter-active-class="transition-opacity duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="isMobileDrawerOpen"
                class="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
                @click="isMobileDrawerOpen = false"
            ></div>
        </transition>

        <!-- Responsive Mobile Left Drawer -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-50 w-72 bg-white text-slate-700 flex flex-col shadow-2xl transition-transform duration-200 lg:hidden select-none',
                isMobileDrawerOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <!-- Mobile Brand Header with Close Button -->
            <div class="h-16 px-4 flex items-center justify-between border-b border-slate-100 bg-white shrink-0">
                <div class="flex items-center gap-3 overflow-hidden">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-700 via-indigo-600 to-sky-500 flex items-center justify-center font-black text-white text-xs tracking-wider shadow-md shadow-indigo-500/25 ring-1 ring-white/20 shrink-0">
                        PBJ
                    </div>
                    <div class="truncate">
                        <div class="font-black text-sm tracking-tight text-slate-900 leading-none">PANI LEARNING</div>
                        <div class="text-[9px] font-extrabold text-blue-600 tracking-wider uppercase mt-1">Enterprise L&amp;D Portal</div>
                    </div>
                </div>
                <button
                    type="button"
                    @click="isMobileDrawerOpen = false"
                    class="p-2 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                    title="Tutup Menu"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Mobile Navigation Area -->
            <nav class="flex-1 py-4 px-3 space-y-4 overflow-y-auto">
                <div
                    v-for="group in visibleSidebarGroups"
                    :key="group.id"
                    class="space-y-1"
                >
                    <div class="px-2.5 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-slate-400">
                        {{ group.label }}
                    </div>
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            @click="isMobileDrawerOpen = false"
                            :class="[
                                'group flex items-center justify-between gap-2.5 px-3 py-2.5 rounded-xl text-xs transition-all duration-150',
                                isActive(item.match)
                                    ? 'bg-blue-600 text-white font-bold shadow-sm shadow-blue-500/25'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 font-medium',
                            ]"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <svg
                                    :class="[
                                        'w-4 h-4 shrink-0',
                                        isActive(item.match) ? 'text-white' : 'text-slate-400 group-hover:text-slate-700',
                                    ]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                </svg>
                                <span class="truncate">{{ item.name }}</span>
                            </div>
                            <span
                                v-if="item.badge"
                                :class="[
                                    'inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-extrabold uppercase shrink-0 border',
                                    getBadgeStyle(item.badge, isActive(item.match)),
                                ]"
                            >
                                {{ item.badge }}
                            </span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- Mobile User Profile Footer -->
            <div class="p-3 border-t border-slate-100 bg-slate-50/60 shrink-0">
                <div class="flex items-center justify-between gap-2 p-1.5 rounded-xl bg-white border border-slate-200/60">
                    <div class="flex items-center gap-2.5 truncate min-w-0">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-700 via-indigo-600 to-sky-500 text-white font-black text-xs flex items-center justify-center shrink-0 ring-2 ring-white">
                            {{ (user.name || 'Admin').charAt(0).toUpperCase() }}
                        </div>
                        <div class="truncate">
                            <div class="text-xs font-bold text-slate-900 truncate leading-tight">{{ user.name || 'Administrator' }}</div>
                            <div class="text-[10px] font-medium text-slate-500 truncate mt-0.5">
                                {{ user.nik || 'EMP-0001' }} &bull; {{ userRole.toUpperCase() }}
                            </div>
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors shrink-0"
                        title="Keluar"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Modern Desktop Left Sidebar -->
        <aside
            :class="[
                'hidden lg:flex bg-white text-slate-700 flex-col border-r border-slate-200/80 shadow-[1px_0_10px_rgba(15,23,42,0.02)] transition-all duration-200 shrink-0 select-none z-20',
                isSidebarCollapsed ? 'w-20' : 'w-72',
            ]"
        >
            <!-- Brand Header -->
            <div class="h-16 px-4 flex items-center justify-between border-b border-slate-100 bg-white shrink-0">
                <div v-if="!isSidebarCollapsed" class="flex items-center gap-3 overflow-hidden">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-700 via-indigo-600 to-sky-500 flex items-center justify-center font-black text-white text-xs tracking-wider shadow-md shadow-indigo-500/25 ring-1 ring-white/20 shrink-0">
                        PBJ
                    </div>
                    <div class="truncate">
                        <div class="font-black text-sm tracking-tight text-slate-900 leading-none">PANI LEARNING</div>
                        <div class="text-[9px] font-extrabold text-blue-600 tracking-wider uppercase mt-1">Enterprise L&amp;D Portal</div>
                    </div>
                </div>
                <div v-else class="mx-auto w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-700 via-indigo-600 to-sky-500 flex items-center justify-center font-black text-white text-xs tracking-wider shadow-md shadow-indigo-500/25 ring-1 ring-white/20">
                    PBJ
                </div>

                <button
                    type="button"
                    @click="isSidebarCollapsed = !isSidebarCollapsed"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                    title="Toggle Sidebar"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
                    </svg>
                </button>
            </div>

            <!-- Navigation Area (Clean Group Flow - No Box Clutter) -->
            <nav class="flex-1 py-4 px-3 space-y-5 overflow-y-auto">
                <div
                    v-for="(group, gIdx) in visibleSidebarGroups"
                    :key="group.id"
                    class="space-y-1"
                >
                    <!-- Category Eyebrow Header -->
                    <div
                        v-if="!isSidebarCollapsed"
                        class="px-2.5 py-1.5 flex items-center justify-between text-[10px] font-black uppercase tracking-[0.14em] text-slate-400"
                    >
                        <span>{{ group.label }}</span>
                        <button
                            type="button"
                            @click="toggleGroup(group.id)"
                            class="p-0.5 rounded text-slate-400 hover:text-slate-600 transition-colors"
                            title="Buka / Tutup grup"
                        >
                            <svg
                                :class="[
                                    'w-3 h-3 transition-transform duration-150',
                                    openGroups[group.id] ? '' : '-rotate-90',
                                ]"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>

                    <!-- Subtle divider when collapsed -->
                    <div
                        v-else-if="gIdx > 0"
                        class="my-2 border-t border-slate-100"
                    ></div>

                    <!-- Group Nav Items -->
                    <div v-show="openGroups[group.id] || isSidebarCollapsed" class="space-y-0.5">
                        <Link
                            v-for="item in group.items"
                            :key="item.href"
                            :href="item.href"
                            :title="item.name"
                            :aria-label="item.name"
                            :class="[
                                'group flex items-center justify-between gap-2.5 px-3 py-2 rounded-xl text-xs transition-all duration-150',
                                isActive(item.match)
                                    ? 'bg-blue-600 text-white font-bold shadow-sm shadow-blue-500/25 ring-1 ring-blue-500/30'
                                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-medium',
                                isSidebarCollapsed ? 'justify-center px-2 py-2.5' : '',
                            ]"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <svg
                                    :class="[
                                        'w-4 h-4 shrink-0 transition-colors',
                                        isActive(item.match) ? 'text-white' : 'text-slate-400 group-hover:text-slate-700',
                                    ]"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="item.icon" />
                                </svg>
                                <span v-if="!isSidebarCollapsed" class="truncate">{{ item.name }}</span>
                            </div>

                            <span
                                v-if="!isSidebarCollapsed && item.badge"
                                :class="[
                                    'inline-flex items-center px-1.5 py-0.5 rounded-md text-[9px] font-extrabold tracking-wide uppercase shrink-0 border',
                                    getBadgeStyle(item.badge, isActive(item.match)),
                                ]"
                            >
                                <span v-if="item.badge === 'LIVE' && !isActive(item.match)" class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse mr-1"></span>
                                {{ item.badge }}
                            </span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User Profile & Role Badge Footer -->
            <div class="p-3 border-t border-slate-100 bg-slate-50/60 shrink-0">
                <div v-if="!isSidebarCollapsed" class="flex items-center justify-between gap-2 p-1.5 rounded-xl hover:bg-white transition-colors border border-transparent hover:border-slate-200/60">
                    <div class="flex items-center gap-2.5 truncate min-w-0">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-blue-700 via-indigo-600 to-sky-500 text-white font-black text-xs flex items-center justify-center shadow-sm shrink-0 ring-2 ring-white">
                            {{ (user.name || 'Admin').charAt(0).toUpperCase() }}
                        </div>
                        <div class="truncate">
                            <div class="text-xs font-bold text-slate-900 truncate leading-tight">{{ user.name || 'Administrator' }}</div>
                            <div class="text-[10px] font-medium text-slate-500 truncate mt-0.5">
                                {{ user.nik || 'EMP-0001' }} &bull; {{ user.organization?.code || userRole.toUpperCase() }}
                            </div>
                        </div>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors shrink-0"
                        title="Keluar dari Sistem"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
                <div v-else class="text-center">
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors mx-auto block w-max"
                        title="Keluar"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 bg-white border-b border-slate-200/90 px-4 sm:px-6 flex items-center justify-between shrink-0 shadow-[0_1px_3px_rgba(0,0,0,0.02)]">
                <div class="flex items-center gap-3 min-w-0">
                    <button
                        type="button"
                        @click="isMobileDrawerOpen = true"
                        class="lg:hidden p-2 -ml-1 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors shrink-0"
                        title="Buka Navigasi"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>
                    <div class="min-w-0 truncate">
                        <h1 class="text-base sm:text-lg font-black text-slate-900 tracking-tight leading-tight truncate">{{ title }}</h1>
                        <p v-if="subtitle" class="text-xs text-slate-500 mt-0.5 truncate">{{ subtitle }}</p>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-slate-900">{{ user.name || 'Admin L&D' }}</div>
                        <div class="text-[11px] text-slate-500">
                            {{ user.organization?.name || user.department || 'Human Capital & LND' }}
                        </div>
                    </div>
                    <span
                        :class="[
                            'inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider border shadow-sm',
                            roleBadgeMeta.classes,
                        ]"
                    >
                        <span class="w-1.5 h-1.5 rounded-full mr-1.5 bg-current opacity-70"></span>
                        {{ roleBadgeMeta.label }}
                    </span>
                </div>
            </header>

            <!-- Flash Notification Banner (Inline Fallback) -->
            <div v-if="flashSuccess || flashError" class="px-6 pt-4">
                <div
                    v-if="flashSuccess"
                    class="px-4 py-2.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-sm"
                >
                    <span>{{ flashSuccess }}</span>
                </div>
                <div
                    v-if="flashError"
                    class="px-4 py-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs font-semibold flex items-center justify-between shadow-sm"
                >
                    <span>{{ flashError }}</span>
                </div>
            </div>

            <!-- Floating Enterprise Toast Container -->
            <teleport to="body">
                <div
                    class="fixed top-5 right-5 z-[9999] flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
                    aria-live="polite"
                >
                    <transition-group
                        enter-active-class="transform ease-out duration-300 transition"
                        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-4"
                        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
                        leave-active-class="transition ease-in duration-200"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-for="toast in toasts"
                            :key="toast.id"
                            class="pointer-events-auto rounded-2xl p-4 shadow-xl border backdrop-blur-md transition-all flex items-start gap-3.5 relative overflow-hidden"
                            :class="[
                                toast.type === 'success'
                                    ? 'bg-white/95 border-emerald-200 text-slate-800 shadow-emerald-500/10 ring-1 ring-emerald-500/20'
                                    : toast.type === 'error'
                                        ? 'bg-white/95 border-rose-200 text-slate-800 shadow-rose-500/10 ring-1 ring-rose-500/20'
                                        : 'bg-white/95 border-blue-200 text-slate-800 shadow-blue-500/10 ring-1 ring-blue-500/20',
                            ]"
                        >
                            <div
                                class="w-7 h-7 rounded-xl flex items-center justify-center shrink-0"
                                :class="[
                                    toast.type === 'success'
                                        ? 'bg-emerald-100 text-emerald-700'
                                        : toast.type === 'error'
                                            ? 'bg-rose-100 text-rose-700'
                                            : 'bg-blue-100 text-blue-700',
                                ]"
                            >
                                <svg v-if="toast.type === 'success'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                                <svg v-else-if="toast.type === 'error'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0 pr-2">
                                <p
                                    class="text-[10px] font-black uppercase tracking-wider"
                                    :class="[
                                        toast.type === 'success' ? 'text-emerald-700' : toast.type === 'error' ? 'text-rose-700' : 'text-blue-700',
                                    ]"
                                >
                                    {{ toast.type === 'success' ? 'Berhasil' : toast.type === 'error' ? 'Pemberitahuan Sistem' : 'Informasi' }}
                                </p>
                                <p class="text-xs font-medium text-slate-700 mt-0.5 leading-relaxed break-words">
                                    {{ toast.message }}
                                </p>
                            </div>

                            <button
                                type="button"
                                @click="removeToast(toast.id)"
                                class="text-slate-400 hover:text-slate-600 transition-colors p-1 -mr-1"
                                title="Tutup Notifikasi"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>

                            <div
                                class="absolute bottom-0 left-0 right-0 h-0.5 bg-gradient-to-r opacity-60"
                                :class="[
                                    toast.type === 'success' ? 'from-emerald-500 to-teal-400' : toast.type === 'error' ? 'from-rose-500 to-amber-500' : 'from-blue-500 to-indigo-400',
                                ]"
                            ></div>
                        </div>
                    </transition-group>
                </div>
            </teleport>

            <!-- Page Slot -->
            <main class="flex-1 p-6 overflow-x-hidden">
                <slot />
            </main>
        </div>
    </div>
</template>
