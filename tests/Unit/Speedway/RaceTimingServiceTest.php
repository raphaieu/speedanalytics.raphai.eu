<?php

namespace Tests\Unit\Speedway;

use App\Models\SpeedwayRace;
use App\Services\Speedway\RaceTimingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RaceTimingServiceTest extends TestCase
{
    use RefreshDatabase;

    private RaceTimingService $timingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->timingService = app(RaceTimingService::class);
    }

    public function test_virtual_schedule_is_four_hours_ahead_of_brazil(): void
    {
        $this->assertSame(0, $this->timingService->virtualHourFromBrazil(20));
        $this->assertSame(20, $this->timingService->brazilHourFromVirtual(0));
        $this->assertSame(19, $this->timingService->brazilHourFromVirtual(23));
    }

    public function test_countdown_uses_brazil_time_when_virtual_shows_midnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-22 20:00:00', RaceTimingService::TIMEZONE));

        $race = SpeedwayRace::query()->create([
            'external_id' => 'offset-countdown',
            'status' => 'pending',
            'race_hour' => '0',
            'race_minute' => '31',
            'pilot_odds_raw' => '2.00|3.00|4.00|5.00',
            'first_seen_at' => Carbon::parse('2026-06-22 19:55:00', RaceTimingService::TIMEZONE)->utc(),
        ]);

        $timing = $this->timingService->analyze($race);

        $this->assertSame('00:31', $timing['schedule_time_label']);
        $this->assertSame('20:31', $timing['starts_at_br_label']);
        $this->assertSame('upcoming', $timing['timing_status']);
        $this->assertSame(31 * 60, $timing['seconds_to_start']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
