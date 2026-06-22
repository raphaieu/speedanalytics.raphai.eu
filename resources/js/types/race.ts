export type RaceStatus = 'pending' | 'settled';

export type RaceTimingStatus = 'upcoming' | 'live' | 'late' | 'stale' | 'unknown';

export type RaceTiming = {
  seconds_to_start: number | null;
  seconds_since_start: number | null;
  timing_status: RaceTimingStatus;
  is_stale: boolean;
  starts_at_iso: string | null;
  starts_at_label: string | null;
  starts_at_br_label?: string | null;
  schedule_time_label?: string | null;
};

export type PilotRow = {
  position: number;
  odd: string;
  color: string;
  is_favorite: boolean;
  is_winner: boolean;
};

export type OddsAnalysis = {
  forecast: string | null;
  tricast: string | null;
  favorite_position: number | null;
  ranked: number[];
  favorite_won: boolean | null;
  forecast_first_won: boolean | null;
};

export type RaceSummary = {
  id: number;
  external_id: string;
  status: RaceStatus;
  schedule_slot: string | null;
  race_hour: string | null;
  race_minute: string | null;
  pilot_odds_raw: string | null;
  favorite_position: number | null;
  odds_forecast: string | null;
  odds_tricast: string | null;
  favorite_won: boolean | null;
  underdog_won?: boolean | null;
  forecast_first_won: boolean | null;
  winner_position: number | null;
  winner_odd: string | null;
  pilot_name: string | null;
  first_seen_at: string | null;
  settled_at: string | null;
  settlement_latency_seconds?: number | null;
  stale_at?: string | null;
  stale_reason?: string | null;
  timing?: RaceTiming | null;
};

export type RaceDetail = RaceSummary & {
  odds_analysis: OddsAnalysis;
  pilots: PilotRow[];
  pending_pilots: PilotRow[] | null;
  timeline: {
    first_seen_at: string | null;
    settled_at: string | null;
    has_pending_snapshot: boolean;
  };
  raw_pending_payload: Record<string, unknown> | null;
  raw_result_payload: Record<string, unknown> | null;
};
