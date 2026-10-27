<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Modul Sedang Dikembangkan',
    },
    subtitle: {
        type: String,
        default: 'Fitur ini sedang dalam tahap konfigurasi & integrasi enterprise.',
    },
    section: {
        type: String,
        default: 'L&D Enterprise System',
    },
    description: {
        type: String,
        default: 'Halaman ini telah terdaftar dalam arsitektur routing enterprise dan siap untuk deployment fungsionalitas penuh.',
    },
});

const page = usePage();
const user = computed(() => page.props.auth?.user || {});
</script>

<template>
    <Head :title="title" />

    <AdminLayout :title="title" :subtitle="subtitle">
        <div class="max-w-4xl mx-auto py-8">
            <!-- Breadcrumb Navigation -->
            <nav class="flex items-center gap-2 text-xs font-semibold text-slate-500 mb-6">
                <Link href="/dashboard" class="hover:text-blue-600 transition-colors">Dashboard</Link>
                <span>/</span>
                <span class="text-slate-400">{{ section }}</span>
                <span>/</span>
                <span class="text-slate-900 font-bold">{{ title }}</span>
            </nav>

            <!-- Under Construction Hero Card -->
            <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-8 sm:p-12 text-center relative overflow-hidden">
                <!-- Background decorative glow -->
                <div class="absolute -top-24 -right-24 w-72 h-72 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
                <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>

                <!-- Status Badge -->
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold uppercase tracking-wider mb-6">
                    <span class="w-2 h-2 rounded-full bg-blue-600 animate-pulse"></span>
                    Blueprint Ready &bull; Under Active Development
                </div>

                <!-- Icon Wrapper -->
                <div class="w-20 h-20 mx-auto rounded-3xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-sky-500 flex items-center justify-center text-white shadow-xl shadow-blue-500/20 mb-6 ring-4 ring-blue-50">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>

                <!-- Headline & Subtitle -->
                <h2 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight mb-3">
                    {{ title }}
                </h2>
                <p class="text-sm text-slate-600 max-w-lg mx-auto mb-6 leading-relaxed">
                    {{ subtitle }}
                </p>
                <p class="text-xs text-slate-400 max-w-md mx-auto mb-8 font-medium">
                    {{ description }}
                </p>

                <!-- Action Button Group -->
                <div class="flex flex-wrap items-center justify-center gap-3">
                    <Link
                        href="/dashboard"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md shadow-blue-500/20 transition-all duration-150"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        <span>Kembali ke Dashboard</span>
                    </Link>
                    <Link
                        href="/training/calendar"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-all duration-150"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        <span>Lihat Kalender Training</span>
                    </Link>
                </div>

                <!-- Metadata Footer Box -->
                <div class="mt-10 pt-6 border-t border-slate-100 flex flex-wrap items-center justify-between gap-4 text-left text-xs text-slate-500">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Route Active: <code class="font-mono text-slate-700 bg-slate-100 px-1.5 py-0.5 rounded">{{ $page.url }}</code></span>
                    </div>
                    <div>
                        Operator: <span class="font-semibold text-slate-800">{{ user.name }} ({{ user.role?.toUpperCase() }})</span>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

