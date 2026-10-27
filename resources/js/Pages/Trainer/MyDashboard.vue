<script setup>
import Dashboard from './Dashboard.vue';

defineProps({
    trainer: {
        type: Object,
        required: true,
    },
    todayRoster: {
        type: Object,
        default: () => ({}),
    },
    metrics: {
        type: Object,
        default: () => ({}),
    },
    todaySessions: {
        type: Array,
        default: () => [],
    },
    upcomingSessions: {
        type: Array,
        default: () => [],
    },
    completedHistory: {
        type: Array,
        default: () => [],
    },
    kpiRecords: {
        type: Array,
        default: () => [],
    },
    assignedCourses: {
        type: Array,
        default: () => [],
    },
    recentReviews: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <Dashboard
        :trainer="trainer"
        :today-roster="todayRoster"
        :metrics="metrics"
        :today-sessions="todaySessions"
        :upcoming-sessions="upcomingSessions"
        :completed-history="completedHistory"
        :kpi-records="kpiRecords"
        :assigned-courses="assignedCourses"
        :recent-reviews="recentReviews"
    />
</template>

