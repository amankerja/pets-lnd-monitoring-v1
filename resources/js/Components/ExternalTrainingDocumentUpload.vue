<script setup>
import ExternalTrainingDocumentUploadForm from '@/Components/ExternalTrainingDocumentUploadForm.vue';

const props = defineProps({
    courses: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <section
        v-if="props.courses.length"
        class="mb-4 rounded-lg border border-indigo-200 bg-white p-4 shadow-sm"
        aria-labelledby="participant-documents-heading"
    >
        <h2 id="participant-documents-heading" class="text-sm font-bold text-slate-900">
            Berkas Persyaratan Training Eksternal
        </h2>
        <p class="mb-4 mt-1 text-xs text-slate-600">
            Unggah berkas PDF, JPG, JPEG, atau PNG dengan ukuran maksimal 10 MB per berkas.
        </p>

        <div class="space-y-4">
            <ExternalTrainingDocumentUploadForm
                v-for="course in props.courses"
                :key="course.enrollment_id"
                :course="course"
            >
            </ExternalTrainingDocumentUploadForm>
        </div>
    </section>
</template>
