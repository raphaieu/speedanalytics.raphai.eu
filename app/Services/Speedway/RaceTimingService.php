<?php

namespace App\Services\Speedway;

use App\Models\SpeedwayRace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RaceTimingService
{
    public const TIMEZONE = 'America/Sao_Paulo';

    /**
     * A grade Hora/Minutos do BB Tips corre +offset h em relação ao relógio BR.
     * Ex.: BR 20:00 ↔ grade 00:00 quando offset = 4.
     */
    public function scheduleOffsetHours(): int
    {
        return (int) config('speedway.race_schedule_offset_hours', 4);
    }

    public function brazilHourFromVirtual(int $virtualHour): int
    {
        $offset = $this->scheduleOffsetHours();

        return ($virtualHour - $offset + 24) % 24;
    }

    public function virtualHourFromBrazil(int $brazilHour): int
    {
        $offset = $this->scheduleOffsetHours();

        return ($brazilHour + $offset) % 24;
    }

    /**
     * Horário exibido no BB Tips (grade virtual), sem conversão.
     */
    public function virtualScheduleLabel(SpeedwayRace $race): ?string
    {
        if ($race->race_hour === null || $race->race_minute === null) {
            return null;
        }

        return sprintf(
            '%02d:%02d',
            (int) $race->race_hour,
            (int) $race->race_minute,
        );
    }

    /**
     * Resolve o instante real de largada em America/Sao_Paulo, ancorado em first_seen_at.
     */
    public function resolveStartsAt(SpeedwayRace $race, ?Carbon $reference = null): ?Carbon
    {
        if ($race->race_hour === null || $race->race_minute === null) {
            return null;
        }

        $virtualHour = (int) $race->race_hour;
        $minute = (int) $race->race_minute;
        $brazilHour = $this->brazilHourFromVirtual($virtualHour);

        $referencePoint = ($reference ?? $race->first_seen_at ?? now())
            ->copy()
            ->timezone(self::TIMEZONE);

        $startsAt = Carbon::create(
            $referencePoint->year,
            $referencePoint->month,
            $referencePoint->day,
            $brazilHour,
            $minute,
            0,
            self::TIMEZONE,
        );

        if ($startsAt->greaterThan($referencePoint->copy()->addHours(12))) {
            $startsAt->subDay();
        } elseif ($referencePoint->greaterThan($startsAt->copy()->addHours(12))) {
            $startsAt->addDay();
        }

        return $startsAt;
    }

    /**
     * @return array{
     *     seconds_to_start: int|null,
     *     seconds_since_start: int|null,
     *     timing_status: 'upcoming'|'live'|'late'|'stale'|'unknown',
     *     is_stale: bool,
     *     starts_at_iso: string|null,
     *     starts_at_label: string|null,
     *     starts_at_br_label: string|null,
     *     schedule_time_label: string|null
     * }
     */
    public function analyze(
        SpeedwayRace $race,
        ?Carbon $now = null,
        ?int $maxPendingExternalId = null,
    ): array {
        $now = ($now ?? now())->copy()->timezone(self::TIMEZONE);
        $startsAt = $this->resolveStartsAt($race);
        $scheduleTimeLabel = $this->virtualScheduleLabel($race);

        if ($startsAt === null) {
            return [
                'seconds_to_start' => null,
                'seconds_since_start' => null,
                'timing_status' => 'unknown',
                'is_stale' => $race->stale_at !== null,
                'starts_at_iso' => null,
                'starts_at_label' => $scheduleTimeLabel,
                'starts_at_br_label' => null,
                'schedule_time_label' => $scheduleTimeLabel,
            ];
        }

        $bufferMinutes = (int) config('speedway.pending_stale_buffer_minutes', 8);
        $liveWindowMinutes = (int) config('speedway.race_live_window_minutes', 4);

        $secondsToStart = (int) $now->diffInSeconds($startsAt, false);
        $secondsSinceStart = max(0, -$secondsToStart);

        $isPastBuffer = $now->greaterThanOrEqualTo($startsAt->copy()->addMinutes($bufferMinutes));
        $isStale = $race->stale_at !== null
            || ($race->status === 'pending' && $isPastBuffer)
            || $this->isPendingExternalIdLagStale($race, $maxPendingExternalId);

        if ($isStale) {
            $timingStatus = 'stale';
        } elseif ($secondsToStart > 0) {
            $timingStatus = 'upcoming';
        } elseif ($secondsSinceStart <= ($liveWindowMinutes * 60)) {
            $timingStatus = 'live';
        } else {
            $timingStatus = 'late';
        }

        return [
            'seconds_to_start' => max(0, $secondsToStart),
            'seconds_since_start' => $secondsSinceStart,
            'timing_status' => $timingStatus,
            'is_stale' => $isStale,
            'starts_at_iso' => $startsAt->toIso8601String(),
            'starts_at_label' => $scheduleTimeLabel,
            'starts_at_br_label' => $startsAt->format('H:i'),
            'schedule_time_label' => $scheduleTimeLabel,
        ];
    }

    public function isActionablePending(
        SpeedwayRace $race,
        ?Carbon $now = null,
        ?int $maxPendingExternalId = null,
    ): bool {
        if ($race->status !== 'pending') {
            return false;
        }

        return ! $this->analyze($race, $now, $maxPendingExternalId)['is_stale'];
    }

    /**
     * @param  Collection<int, SpeedwayRace>  $races
     */
    public function maxPendingExternalId(Collection $races): ?int
    {
        $max = $races
            ->filter(fn (SpeedwayRace $race) => $race->status === 'pending')
            ->max(fn (SpeedwayRace $race) => (int) $race->external_id);

        return is_numeric($max) ? (int) $max : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentation(
        SpeedwayRace $race,
        ?Carbon $now = null,
        ?int $maxPendingExternalId = null,
    ): array {
        return $this->analyze($race, $now, $maxPendingExternalId);
    }

    private function isPendingExternalIdLagStale(SpeedwayRace $race, ?int $maxPendingExternalId): bool
    {
        if ($race->status !== 'pending' || $maxPendingExternalId === null) {
            return false;
        }

        $externalId = (int) $race->external_id;
        if ($externalId <= 0) {
            return false;
        }

        $lag = $maxPendingExternalId - $externalId;
        $threshold = (int) config('speedway.pending_external_id_lag', 80);

        return $lag > $threshold;
    }
}
