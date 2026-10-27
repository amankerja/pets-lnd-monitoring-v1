<?php

namespace App\Services;

use App\Models\TrainerEvaluation;
use App\Models\TrainerTaskAndKpi;
use App\Models\TrainerTeachingLog;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrainerKpiActualsService
{
    public function actualsFor(Collection $kpis): Collection
    {
        if ($kpis->isEmpty()) {
            return collect();
        }

        $periods = $kpis->mapWithKeys(function (TrainerTaskAndKpi $kpi) {
            $createdAt = $kpi->created_at ? Carbon::parse($kpi->created_at) : now();
            $start = $kpi->start_date?->copy()->startOfDay() ?? match ($kpi->period) {
                'daily' => $createdAt->copy()->startOfDay(),
                'weekly' => $createdAt->copy()->startOfWeek(),
                'monthly' => $createdAt->copy()->startOfMonth(),
                'yearly' => $createdAt->copy()->startOfYear(),
                default => $createdAt->copy()->startOfDay(),
            };
            $end = $kpi->due_date?->copy()->endOfDay() ?? now()->endOfDay();
            $end = $end->gt(now()->endOfDay()) ? now()->endOfDay() : $end;

            return [$kpi->id => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
            ]];
        });

        $validPeriods = $periods->filter(fn (array $period) => $period['start'] <= $period['end']);
        $logActivityByTrainer = collect();
        $evaluationActivityByTrainer = collect();

        if ($validPeriods->isNotEmpty()) {
            $trainerIds = $kpis->pluck('trainer_id')->unique()->all();
            $rangeStart = $validPeriods->min('start');
            $rangeEnd = $validPeriods->max('end');

            $logActivityByTrainer = TrainerTeachingLog::query()
                ->whereIn('trainer_id', $trainerIds)
                ->whereBetween('log_date', [$rangeStart, $rangeEnd])
                ->selectRaw('trainer_id, log_date, SUM(duration_hours) as total_hours, SUM(scanned_participants_count) as total_participants')
                ->groupBy('trainer_id', 'log_date')
                ->get()
                ->groupBy('trainer_id');

            $evaluationActivityByTrainer = TrainerEvaluation::query()
                ->whereIn('trainer_id', $trainerIds)
                ->whereBetween('created_at', [$rangeStart . ' 00:00:00', $rangeEnd . ' 23:59:59'])
                ->selectRaw('trainer_id, DATE(created_at) as activity_date, COUNT(*) as evaluation_count, AVG(rating) as average_rating')
                ->groupBy('trainer_id', 'activity_date')
                ->get()
                ->groupBy('trainer_id');
        }

        return $kpis->mapWithKeys(function (TrainerTaskAndKpi $kpi) use ($periods, $validPeriods, $logActivityByTrainer, $evaluationActivityByTrainer) {
            $period = $periods->get($kpi->id);
            $periodIsValid = $validPeriods->has($kpi->id);
            $periodLogs = $periodIsValid
                ? $logActivityByTrainer->get($kpi->trainer_id, collect())->filter(
                    fn ($activity) => (string) $activity->log_date >= $period['start'] && (string) $activity->log_date <= $period['end']
                )
                : collect();
            $periodEvaluations = $periodIsValid
                ? $evaluationActivityByTrainer->get($kpi->trainer_id, collect())->filter(
                    fn ($activity) => (string) $activity->activity_date >= $period['start'] && (string) $activity->activity_date <= $period['end']
                )
                : collect();
            $evaluationCount = (int) $periodEvaluations->sum('evaluation_count');
            $actualRating = $evaluationCount > 0
                ? $periodEvaluations->sum(fn ($activity) => (float) $activity->average_rating * (int) $activity->evaluation_count) / $evaluationCount
                : null;

            return [$kpi->id => [
                'start_date' => $period['start'],
                'end_date' => $period['end'],
                'actual_teaching_hours' => round((float) $periodLogs->sum('total_hours'), 1),
                'actual_participants_count' => (int) $periodLogs->sum('total_participants'),
                'actual_rating' => $actualRating === null ? 0.0 : round($actualRating, 2),
                'evaluation_count' => $evaluationCount,
            ]];
        });
    }

    public function syncTrainer(int $trainerId): void
    {
        $kpis = TrainerTaskAndKpi::query()
            ->where('trainer_id', $trainerId)
            ->whereIn('status', ['in_progress', 'pending'])
            ->get();

        $actualsById = $this->actualsFor($kpis);
        foreach ($kpis as $kpi) {
            $actuals = $actualsById->get($kpi->id);
            $kpi->update([
                'actual_teaching_hours' => $actuals['actual_teaching_hours'],
                'actual_participants_count' => $actuals['actual_participants_count'],
                'actual_rating' => $actuals['actual_rating'],
            ]);
        }
    }
}
