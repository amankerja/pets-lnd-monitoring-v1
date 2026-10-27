<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    public const ROTATION_INTERVAL_SECONDS = 15;

    protected $fillable = [
        'course_id',
        'trainer_id',
        'title',
        'session_date',
        'start_time',
        'end_time',
        'location',
        'quota',
        'plan_status',
        'duration_hours',
        'qr_token',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'duration_hours' => 'float',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function teachingLog(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TrainerTeachingLog::class, 'attendance_session_id');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(TrainerEvaluation::class, 'attendance_session_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Stateless Rotating QR Code (15-Second Time-Window HMAC-SHA256)
    |--------------------------------------------------------------------------
    */

    /**
     * Menghitung time-window 15 detikan saat ini.
     * Menggunakan now()->timestamp (bukan native time()) agar dapat di-mock oleh Carbon::setTestNow() / $this->travel().
     */
    public static function getCurrentTimeWindow(?int $timestamp = null): int
    {
        $now = $timestamp ?? now()->timestamp; // JANGAN pakai time() agar bisa di-mock oleh Carbon/Pest

        return (int) floor($now / self::ROTATION_INTERVAL_SECONDS);
    }

    /**
     * Menghasilkan signature HMAC-SHA256 sepanjang 12 karakter (uppercase hex)
     * menggunakan kolom `qr_token` sebagai secret_key sesi tanpa query UPDATE ke DB.
     */
    public function generateWindowSignature(int $timeWindow): string
    {
        $dataToSign = "{$this->id}:{$this->qr_token}:{$timeWindow}";
        $rawHmac = hash_hmac('sha256', $dataToSign, (string) $this->qr_token);

        return strtoupper(substr($rawHmac, 0, 12));
    }

    /**
     * Menghasilkan paket payload QR dinamis & kode manual 6-digit berdasarkan offset window.
     * Format qr_string / payload: "{session_id}:{hmac_signature}"
     *
     * @param  int  $windowOffset  Offset terhadap window saat ini (0 = saat ini, -1 = 15 detik lalu), atau nilai window absolut.
     * @return array{qr_string: string, payload: string, manual_code: string, time_window: int, rotation_interval: int, expires_in: int, generated_at: string}
     */
    public function getDynamicPayload(int $windowOffset = 0): array
    {
        $interval = self::ROTATION_INTERVAL_SECONDS;
        $now = now()->timestamp; // JANGAN pakai time() agar bisa di-mock oleh Carbon/Pest
        $currentWindow = abs($windowOffset) < 100000
            ? (int) floor($now / $interval) + $windowOffset
            : $windowOffset;

        $signature = $this->generateWindowSignature($currentWindow);
        $qrString = "{$this->id}:{$signature}";

        // Konversi 6 karakter hex pertama menjadi 6 digit angka (000000 - 999999)
        $numeric = hexdec(substr($signature, 0, 6)) % 1000000;
        $manualCode = str_pad((string) $numeric, 6, '0', STR_PAD_LEFT);

        return [
            'qr_string' => $qrString,
            'payload' => $qrString,
            'manual_code' => $manualCode,
            'time_window' => $currentWindow,
            'rotation_interval' => $interval,
            'expires_in' => $interval - ($now % $interval),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Menghasilkan kode manual 6-digit yang diturunkan dari signature window saat ini
     * untuk ditampilkan di layar proyektor sebagai fallback input HP.
     */
    public function getDynamicManualCode(int $windowOffset = 0): string
    {
        return $this->getDynamicPayload($windowOffset)['manual_code'];
    }

    /**
     * Memvalidasi apakah payload QR ("{session_id}:{hmac_signature}") atau kode manual 6-digit
     * cocok dengan time-window saat ini ($windowOffset = 0) ATAU 1 window sebelumnya ($windowOffset = -1)
     * sebagai toleransi lag jaringan peserta (masa hidup efektif token = 15-30 detik).
     */
    public function verifyDynamicPayload(string $payload): bool
    {
        $cleanPayload = strtoupper(trim($payload));

        // Cek di window saat ini (offset 0) dan 1 window sebelumnya (offset -1 / grace period)
        foreach ([0, -1] as $windowOffset) {
            $expected = $this->getDynamicPayload($windowOffset);
            $expectedQrPayload = strtoupper($expected['qr_string']);
            $expectedManualCode = strtoupper($expected['manual_code']);
            $expectedPrefixedManual = "{$this->id}:{$expectedManualCode}";

            if (
                hash_equals($expectedQrPayload, $cleanPayload) ||
                hash_equals($expectedManualCode, $cleanPayload) ||
                hash_equals($expectedPrefixedManual, $cleanPayload)
            ) {
                return true;
            }
        }

        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes & Helpers
    |--------------------------------------------------------------------------
    */

    public function scopeWithAttendanceCounts(Builder $query): Builder
    {
        return $query->withCount([
            'attendances as present_count' => fn (Builder $q) => $q->where('status', 'present'),
            'attendances as permit_count' => fn (Builder $q) => $q->where('status', 'permit'),
            'attendances as absent_count' => fn (Builder $q) => $q->where('status', 'absent'),
        ]);
    }

    public function isExpired(): bool
    {
        return !$this->is_active || $this->expires_at->isPast();
    }
}
