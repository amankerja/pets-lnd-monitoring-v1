<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import ExpiringCertificatesAlert from '@/Components/ExpiringCertificatesAlert.vue';

defineProps({
    expiringList: {
        type: Array,
        default: () => [],
    },
    summary: {
        type: Object,
        default: () => ({
            expired_count: 0,
            critical_count: 0,
            warning_count: 0,
        }),
    },
});
</script>

<template>
    <Head title="Radar Masa Berlaku Sertifikat (H-60 & Expired)" />

    <AdminLayout
        title="Radar Masa Berlaku Sertifikat (H-60 & Expired)"
        subtitle="Subquery Anti-False-Alarm (whereNotExists) & Proteksi Anti-Double-Request Aktif"
    >
        <div class="mb-4 flex items-center justify-between">
            <Link
                href="/organization/portal"
                class="text-xs font-bold text-blue-600 hover:underline"
            >
                &larr; Kembali ke Portal Terpadu Departemen / Kontraktor
            </Link>
        </div>

        <ExpiringCertificatesAlert
            :expiring-list="expiringList"
            :summary="summary"
        />
    </AdminLayout>
</template>

