# Script to update public/preview.html to synchronize with Laravel Live Server & Inertia Trainer Portal
$filePath = "c:\Users\ahmad.imaduddin\Documents\LND PANI PROJEK\public\preview.html"
$content = [System.IO.File]::ReadAllText($filePath, [System.Text.Encoding]::UTF8)

# 1. State in setup()
$targetState = @"
            showMobileEvalCard.value = false;
            notify('Terima kasih! Penilaian & ulasan Anda terhadap Trainer berhasil disimpan.');
        };

        return {
"@

$replacementState = @"
            showMobileEvalCard.value = false;
            notify('Terima kasih! Penilaian & ulasan Anda terhadap Trainer berhasil disimpan.');
        };

        // =========================================================================
        // TRAINER PORTAL & SCHEDULE STATE (COMMAND CENTER)
        // =========================================================================
        const trainerPortalTab = ref('schedule'); // 'schedule' | 'kpi' | 'reports'
        const showTrainerLiveQrModal = ref(false);
        const trainerQrTimer = ref(15);
        const trainerQrCode = ref('849 203');
        let trainerQrInterval = null;

        const openTrainerLiveQr = () => {
            showTrainerLiveQrModal.value = true;
            trainerQrTimer.value = 15;
            if (!trainerQrInterval) {
                trainerQrInterval = setInterval(() => {
                    if (trainerQrTimer.value > 1) {
                        trainerQrTimer.value--;
                    } else {
                        trainerQrTimer.value = 15;
                        trainerQrCode.value = Math.floor(100000 + Math.random() * 900000).toString().replace(/(\d{3})(\d{3})/, '$1 $2');
                    }
                }, 1000);
            }
        };

        const closeTrainerLiveQr = () => {
            showTrainerLiveQrModal.value = false;
        };

        const refreshTrainerQr = () => {
            trainerQrTimer.value = 15;
            trainerQrCode.value = Math.floor(100000 + Math.random() * 900000).toString().replace(/(\d{3})(\d{3})/, '$1 $2');
            notify('Token Barcode/QR absensi peserta berhasil diperbarui secara manual.');
        };

        // Mode Tampilan Jadwal Trainer
        const trainerScheduleViewMode = ref('week'); // 'week' | 'month' | 'list'
        const trainerScheduleFilter = ref('');
        const trainerScheduleDetailModal = ref(null);

        const openTrainerScheduleDetail = (ev) => {
            trainerScheduleDetailModal.value = ev;
        };

        const closeTrainerScheduleDetail = () => {
            trainerScheduleDetailModal.value = null;
        };

        const trainerWeekDays = ref([
            { date: '2026-10-26', dayName: 'Senin', dayNum: '26', isToday: true },
            { date: '2026-10-27', dayName: 'Selasa', dayNum: '27', isToday: false },
            { date: '2026-10-28', dayName: 'Rabu', dayNum: '28', isToday: false },
            { date: '2026-10-29', dayName: 'Kamis', dayNum: '29', isToday: false },
            { date: '2026-10-30', dayName: 'Jumat', dayNum: '30', isToday: false },
            { date: '2026-10-31', dayName: 'Sabtu', dayNum: '31', isToday: false },
            { date: '2026-11-01', dayName: 'Minggu', dayNum: '01', isToday: false }
        ]);

        const trainerScheduleEvents = ref([
            {
                id: 1,
                trainer_id: 1,
                trainer_name: 'Dr. Siti Rahmawati',
                trainer_type: 'INTERNAL_SME',
                course_title: 'Pelatihan K3 Dasar & Pengenalan Tambang Emas (Reguler PBJ)',
                course_code: 'SAF-001',
                session_title: 'Sesi 1: Penilaian Risiko & SOP K3 Tambang Terbuka',
                session_date: '2026-10-26',
                session_date_formatted: 'Senin, 26 Okt 2026',
                start_time: '08:30',
                end_time: '11:30',
                time_formatted: '08:30 - 11:30 WITA',
                duration_hours: 3.0,
                room: 'Ruang Diklat Gd. Utama Lt. 2',
                location: 'Site PBJ Training Room',
                quota_attended: 18,
                quota_total: 20,
                shift: 'day',
                status: 'in_progress',
                is_today: true,
                roster_status: 'on_duty',
                participants: [
                    { name: 'Ahmad Fadillah', nik: 'PANI-0081', dept: 'Mining Operations', status: 'Present' },
                    { name: 'Dedi Kurniawan', nik: 'PANI-0092', dept: 'Processing Plant', status: 'Present' },
                    { name: 'Rian Prasetyo', nik: 'PANI-0104', dept: 'HSE & Risk', status: 'Present' },
                    { name: 'Eko Saputra', nik: 'PANI-0112', dept: 'Maintenance', status: 'Present' }
                ]
            },
            {
                id: 2,
                trainer_id: 2,
                trainer_name: 'Ir. Hendra Wijaya, M.T.',
                trainer_type: 'FULL_TIME_LND',
                course_title: 'Metalurgi Ekstraksi & Sirkuit Pelindian Emas CIL',
                course_code: 'MET-001',
                session_title: 'Sesi Praktik: Pengendalian pH & Konsumsi Reagen Sianida',
                session_date: '2026-10-27',
                session_date_formatted: 'Selasa, 27 Okt 2026',
                start_time: '13:00',
                end_time: '16:00',
                time_formatted: '13:00 - 16:00 WITA',
                duration_hours: 3.0,
                room: 'Laboratorium Metalurgi Plant',
                location: 'Processing Plant Workshop',
                quota_attended: 0,
                quota_total: 12,
                shift: 'day',
                status: 'upcoming',
                is_today: false,
                roster_status: 'on_duty',
                participants: [
                    { name: 'Faisal Ramadhan', nik: 'PANI-0077', dept: 'Processing Plant', status: 'Registered' },
                    { name: 'Hadi Susanto', nik: 'PANI-0085', dept: 'Processing Plant', status: 'Registered' }
                ]
            },
            {
                id: 3,
                trainer_id: 1,
                trainer_name: 'Dr. Siti Rahmawati',
                trainer_type: 'INTERNAL_SME',
                course_title: 'Izin Kerja Khusus & SOP Ruang Terbatas (Confined Space)',
                course_code: 'SAF-002',
                session_title: 'Sesi Ujian Praktek Gas Tester Portable & Rescue',
                session_date: '2026-10-28',
                session_date_formatted: 'Rabu, 28 Okt 2026',
                start_time: '09:00',
                end_time: '12:00',
                time_formatted: '09:00 - 12:00 WITA',
                duration_hours: 3.0,
                room: 'Training Center Confined Simulator',
                location: 'Safety Center Camp PBJ',
                quota_attended: 0,
                quota_total: 15,
                shift: 'day',
                status: 'upcoming',
                is_today: false,
                roster_status: 'on_duty',
                participants: [
                    { name: 'Bambang Sudibyo', nik: 'PANI-0120', dept: 'Mechanical Workshop', status: 'Registered' }
                ]
            },
            {
                id: 4,
                trainer_id: 3,
                trainer_name: 'Budi Santoso, S.T.',
                trainer_type: 'INTERNAL_SME',
                course_title: 'Defensive Driving & Penanganan Jalur Hauling Curam',
                course_code: 'SAF-003',
                session_title: 'Sesi Simulator Jalur Tambang Basah & Licin',
                session_date: '2026-10-29',
                session_date_formatted: 'Kamis, 29 Okt 2026',
                start_time: '14:00',
                end_time: '17:00',
                time_formatted: '14:00 - 17:00 WITA',
                duration_hours: 3.0,
                room: 'Simulator Room Hauling Camp',
                location: 'Pit Operations Training Base',
                quota_attended: 0,
                quota_total: 10,
                shift: 'night',
                status: 'upcoming',
                is_today: false,
                roster_status: 'on_duty',
                participants: [
                    { name: 'Supardi', nik: 'PANI-0142', dept: 'Mining Operations', status: 'Registered' }
                ]
            },
            {
                id: 5,
                trainer_id: 2,
                trainer_name: 'Ir. Hendra Wijaya, M.T.',
                trainer_type: 'FULL_TIME_LND',
                course_title: 'Pengelolaan Limbah B3 & Penetralan Sianida Tambang',
                course_code: 'ENV-001',
                session_title: 'Sesi Lapangan: Audit Titik Penaatan Tailing Detox',
                session_date: '2026-10-30',
                session_date_formatted: 'Jumat, 30 Okt 2026',
                start_time: '08:30',
                end_time: '11:00',
                time_formatted: '08:30 - 11:00 WITA',
                duration_hours: 2.5,
                room: 'Auditorium TSF Facility',
                location: 'TSF Plant Area',
                quota_attended: 0,
                quota_total: 16,
                shift: 'day',
                status: 'upcoming',
                is_today: false,
                roster_status: 'on_duty',
                participants: [
                    { name: 'Arif Wibowo', nik: 'PANI-0155', dept: 'Environmental', status: 'Registered' }
                ]
            }
        ]);

        const filteredTrainerScheduleEvents = computed(() => {
            return trainerScheduleEvents.value.filter(ev => {
                if (trainerScheduleFilter.value && ev.trainer_id !== Number(trainerScheduleFilter.value)) return false;
                return true;
            });
        });

        const trainerAssignedCourses = ref([
            {
                id: 1,
                code: 'SAF-001',
                title: 'Pelatihan K3 Dasar & Pengenalan Tambang Emas (Reguler PBJ)',
                category: 'Safety',
                status: 'running',
                total_sessions: 4,
                completed_sessions: 3,
                total_participants: 20,
                date_range: '18 Okt - 26 Okt 2026',
                completion_pct: 75
            },
            {
                id: 2,
                code: 'SAF-002',
                title: 'Izin Kerja Khusus & SOP Ruang Terbatas (Confined Space)',
                category: 'Safety',
                status: 'upcoming',
                total_sessions: 3,
                completed_sessions: 0,
                total_participants: 15,
                date_range: '28 Okt - 05 Nov 2026',
                completion_pct: 0
            },
            {
                id: 3,
                code: 'ENV-001',
                title: 'Pengelolaan Limbah B3 & Sianida Tambang Emas',
                category: 'Plant & Environment',
                status: 'completed',
                total_sessions: 3,
                completed_sessions: 3,
                total_participants: 18,
                date_range: '05 Okt - 12 Okt 2026',
                completion_pct: 100
            }
        ]);

        const activeTrainerUser = computed(() => trainersListSim.value[0]);

        return {
"@

if ($content.Contains($targetState)) {
    $content = $content.Replace($targetState, $replacementState)
    Write-Output "Successfully updated setup() state."
} else {
    Write-Warning "targetState not found!"
}

# 2. Add return values
$targetReturn = @"
            showMobileEvalCard, mobileEvalForm, submitMobileEvaluationSim,
        };
"@

$replacementReturn = @"
            showMobileEvalCard, mobileEvalForm, submitMobileEvaluationSim,
            trainerPortalTab, showTrainerLiveQrModal, trainerQrTimer, trainerQrCode,
            openTrainerLiveQr, closeTrainerLiveQr, refreshTrainerQr,
            trainerScheduleViewMode, trainerScheduleFilter, trainerScheduleDetailModal,
            openTrainerScheduleDetail, closeTrainerScheduleDetail,
            trainerWeekDays, trainerScheduleEvents, filteredTrainerScheduleEvents,
            trainerAssignedCourses, activeTrainerUser,
        };
"@

if ($content.Contains($targetReturn)) {
    $content = $content.Replace($targetReturn, $replacementReturn)
    Write-Output "Successfully updated setup() returns."
} else {
    Write-Warning "targetReturn not found!"
}

# 3. Sidebar Navigation for Trainer
$targetSidebar = @"
                            <button v-if="activeRoleSim === 'trainer'" @click="currentView = 'admin-trainer-management'; selectedTrainerDetail = trainersListSim[0];" :class="['w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-left transition-all', currentView === 'admin-trainer-management' && selectedTrainerDetail ? 'bg-blue-600 text-white font-bold shadow-sm shadow-blue-500/30' : 'text-slate-600 hover:bg-blue-50/80 hover:text-blue-700 font-semibold']">
                                <span>Portal, Roster &amp; KPI Saya</span>
                                <span class="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 text-[9px] font-bold">KPI</span>
                            </button>
"@

$replacementSidebar = @"
                            <button v-if="activeRoleSim === 'trainer'" @click="currentView = 'trainer-command-center'" :class="['w-full flex items-center justify-between px-2.5 py-2 rounded-lg text-left transition-all', currentView === 'trainer-command-center' ? 'bg-blue-600 text-white font-bold shadow-sm shadow-blue-500/30' : 'text-slate-600 hover:bg-blue-50/80 hover:text-blue-700 font-semibold']">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                                    <span>Portal Instruktur (Command Center)</span>
                                </div>
                                <span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 text-[9px] font-bold">LIVE</span>
                            </button>
"@

if ($content.Contains($targetSidebar)) {
    $content = $content.Replace($targetSidebar, $replacementSidebar)
    Write-Output "Successfully updated sidebar navigation for trainer."
} else {
    Write-Warning "targetSidebar not found!"
}

# 4. Role switcher select
$targetSelect = "activeRoleSim === 'trainer' ? (currentView = 'admin-trainer-hours')"
$replacementSelect = "activeRoleSim === 'trainer' ? (currentView = 'trainer-command-center')"

if ($content.Contains($targetSelect)) {
    $content = $content.Replace($targetSelect, $replacementSelect)
    Write-Output "Successfully updated role switcher select."
} else {
    Write-Warning "targetSelect not found!"
}

# 5. Header Title
$targetHeader = "{{ currentView === 'admin-dashboard' ? 'Dashboard Analitik Pelatihan & Kompetensi' : currentView === 'admin-trainer-management' ? 'Pusat Manajemen Trainer, Roster, Jam Mengajar & KPI'"
$replacementHeader = "{{ currentView === 'admin-dashboard' ? 'Dashboard Analitik Pelatihan & Kompetensi' : currentView === 'trainer-command-center' ? 'Portal Instruktur — Command Center Pribadi Trainer' : currentView === 'admin-trainer-management' ? 'Pusat Manajemen Trainer, Roster, Jam Mengajar & KPI'"

if ($content.Contains($targetHeader)) {
    $content = $content.Replace($targetHeader, $replacementHeader)
    Write-Output "Successfully updated header title."
} else {
    Write-Warning "targetHeader not found!"
}

[System.IO.File]::WriteAllText($filePath, $content, [System.Text.Encoding]::UTF8)
Write-Output "Step 1 complete."

