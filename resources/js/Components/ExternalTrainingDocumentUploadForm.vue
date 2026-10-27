<script setup>
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    course: {
        type: Object,
        required: true,
    },
});

const form = useForm({ documents: [] });

const setDocumentFile = (documentName, event) => {
    const file = event.target.files?.[0] || null;
    const index = form.documents.findIndex((document) => document.name === documentName);

    if (!file && index !== -1) {
        form.documents.splice(index, 1);
    } else if (file && index !== -1) {
        form.documents[index].file = file;
    } else if (file) {
        form.documents.push({ name: documentName, file });
    }
};

const fileError = (documentName) => {
    const index = form.documents.findIndex((document) => document.name === documentName);
    return index === -1 ? null : form.errors[`documents.${index}.file`];
};

const submitDocuments = () => {
    if (form.processing || !form.documents.length) return;

    form.post(`/participant/enrollments/${props.course.enrollment_id}/documents`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <form class="rounded-lg border border-slate-200 p-3" @submit.prevent="submitDocuments">
        <div class="mb-3">
            <span class="text-[10px] font-bold uppercase tracking-wide text-indigo-700">
                {{ course.course_code }}
            </span>
            <h3 class="text-sm font-semibold text-slate-900">{{ course.course_title }}</h3>
        </div>

        <div class="space-y-3">
            <div
                v-for="(document, index) in course.required_documents_schema"
                :key="`${document.name}-${index}`"
                class="grid grid-cols-1 gap-1 sm:grid-cols-[minmax(140px,0.7fr)_minmax(220px,1.3fr)] sm:items-center"
            >
                <label :for="`document-${course.enrollment_id}-${index}`" class="text-sm font-medium text-slate-800">
                    {{ document.name }}
                    <span v-if="document.mandatory" class="text-rose-700">(wajib)</span>
                    <span v-else class="text-slate-500">(opsional)</span>
                </label>
                <div>
                    <input
                        :id="`document-${course.enrollment_id}-${index}`"
                        type="file"
                        accept=".pdf,.jpg,.jpeg,.png"
                        :required="document.mandatory && !course.submitted_document_names.includes(document.name)"
                        class="min-h-11 w-full rounded-lg border border-slate-300 p-2 text-sm text-slate-700 file:mr-3 file:rounded file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-indigo-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        @change="setDocumentFile(document.name, $event)"
                    />
                    <span
                        v-if="course.submitted_document_names.includes(document.name)"
                        class="mt-1 block text-xs font-medium text-emerald-700"
                    >
                        Berkas sudah dikirim; unggah ulang jika ingin menggantinya.
                    </span>
                    <p v-if="fileError(document.name)" class="mt-1 text-xs text-rose-700" role="alert">
                        {{ fileError(document.name) }}
                    </p>
                </div>
            </div>
        </div>

        <p v-if="form.errors.documents" class="mt-2 text-xs text-rose-700" role="alert">
            {{ form.errors.documents }}
        </p>
        <button
            type="submit"
            :disabled="form.processing || !form.documents.length"
            class="mt-4 min-h-11 w-full rounded-lg bg-indigo-700 px-4 text-sm font-bold text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
        >
            {{ form.processing ? 'Mengunggah...' : 'Kirim berkas' }}
        </button>
    </form>
</template>
