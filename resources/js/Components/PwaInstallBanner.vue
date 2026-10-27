<script setup>
import { ref, onMounted } from 'vue';

const deferredPrompt = ref(null);
const showInstallBanner = ref(false);
const isIos = ref(false);

onMounted(() => {
    // Cek apakah sudah berjalan sebagai aplikasi ter-install (standalone)
    const isStandalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;
    if (isStandalone) return;

    // Deteksi iOS Safari (karena iOS tidak mendukung beforeinstallprompt otomatis)
    const userAgent = window.navigator.userAgent.toLowerCase();
    isIos.value = /iphone|ipad|ipod/.test(userAgent);

    if (isIos.value && !localStorage.getItem('hide_pwa_banner')) {
        showInstallBanner.value = true;
    }

    // Tangkap event install native Chrome/Android
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt.value = e;
        if (!localStorage.getItem('hide_pwa_banner')) {
            showInstallBanner.value = true;
        }
    });
});

const installApp = async () => {
    if (!deferredPrompt.value) return;
    deferredPrompt.value.prompt();
    const { outcome } = await deferredPrompt.value.userChoice;
    if (outcome === 'accepted') {
        showInstallBanner.value = false;
    }
    deferredPrompt.value = null;
};

const dismissBanner = () => {
    showInstallBanner.value = false;
    localStorage.setItem('hide_pwa_banner', '1');
};
</script>

<template>
    <div
        v-if="showInstallBanner"
        class="bg-blue-50/90 text-slate-800 px-4 py-3 flex items-center justify-between border-b border-blue-200"
    >
        <div class="text-xs pr-2">
            <p class="font-bold text-blue-700">Install Aplikasi Pelatihan</p>
            <p v-if="!isIos" class="text-slate-600 mt-0.5">
                Akses Scan QR Absen &amp; Rapor Skill 2x lebih cepat dari layar utama HP.
            </p>
            <p v-else class="text-slate-600 mt-0.5">
                Ketuk ikon <b>Share (Bagikan)</b> di bawah browser, lalu pilih <b>"Add to Home Screen"</b>.
            </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button
                v-if="!isIos"
                type="button"
                @click="installApp"
                class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold px-3.5 py-1.5 rounded-lg"
            >
                Install
            </button>
            <button
                type="button"
                @click="dismissBanner"
                class="text-slate-400 hover:text-slate-700 text-xs px-1.5 py-1"
            >
                ✕
            </button>
        </div>
    </div>
</template>

