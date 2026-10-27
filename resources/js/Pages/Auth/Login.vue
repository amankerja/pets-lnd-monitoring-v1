<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
        default: null,
    },
    demoAccounts: {
        type: Array,
        default: () => [],
    },
    isDemoMode: {
        type: Boolean,
        default: false,
    },
});

const isDev = Boolean(import.meta.env?.DEV);
const showDemoAccounts = computed(() => {
    return (props.isDemoMode || isDev) && props.demoAccounts.length > 0;
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

const quickFill = (account) => {
    form.email = account.email;
    form.password = 'password';
};
</script>

<template>
    <Head title="Masuk ke Sistem" />

    <div class="min-h-screen bg-gradient-to-br from-slate-100 via-blue-50/60 to-indigo-100/50 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 via-indigo-600 to-blue-500 font-black text-white text-base shadow-lg shadow-blue-500/30">
                PBJ
            </div>
            <h2 class="mt-4 text-2xl font-black text-slate-900 tracking-tight">
                PANI LEARNING SYSTEM
            </h2>
            <p class="mt-1 text-xs font-semibold text-slate-500">
                Enterprise Training Management &amp; Competency Matrix
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white py-8 px-6 sm:px-8 shadow-xl shadow-slate-200/70 rounded-2xl border border-slate-200/90">
                <!-- Status Flash Message -->
                <div v-if="status" class="mb-4 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 p-3 rounded">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700">Email Pegawai / Akun</label>
                        <div class="mt-1">
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                required
                                class="w-full rounded border-slate-300 text-xs py-2 px-3 focus:ring-blue-600 focus:border-blue-600"
                                placeholder="nama@pani.co.id"
                            />
                        </div>
                        <p v-if="form.errors.email" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold text-slate-700">Kata Sandi</label>
                        <div class="mt-1">
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                required
                                class="w-full rounded border-slate-300 text-xs py-2 px-3 focus:ring-blue-600 focus:border-blue-600"
                                placeholder="••••••••"
                            />
                        </div>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-rose-600">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 w-4 h-4"
                            />
                            <span class="text-xs text-slate-600">Ingat Saya</span>
                        </label>
                        <span class="text-xs text-slate-400">Default Password: <strong>password</strong></span>
                    </div>

                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full flex justify-center py-2.5 px-4 rounded border border-transparent text-xs font-extrabold text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 shadow-sm"
                        >
                            {{ form.processing ? 'Memverifikasi...' : 'Masuk ke Sistem' }}
                        </button>
                    </div>
                </form>

                <!-- Panel Quick Login / 5 Role RBAC Switcher (Hanya dirender pada Local/Testing/Debug Environment) -->
                <div v-if="showDemoAccounts" class="mt-6 pt-6 border-t border-slate-200">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-2">
                        Pilih Cepat Akun Demo (5 Role RBAC):
                    </div>
                    <div class="space-y-1.5 max-h-56 overflow-y-auto pr-1">
                        <button
                            v-for="acc in demoAccounts"
                            :key="acc.email"
                            type="button"
                            @click="quickFill(acc)"
                            class="w-full text-left p-2 rounded border border-slate-200 hover:bg-slate-50 hover:border-blue-400 transition-colors flex items-center justify-between"
                        >
                            <div>
                                <div class="text-xs font-bold text-slate-900">{{ acc.role_label }}</div>
                                <div class="text-[11px] font-mono text-blue-600">{{ acc.email }}</div>
                            </div>
                            <span class="text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded">
                                Isi &rarr;
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

