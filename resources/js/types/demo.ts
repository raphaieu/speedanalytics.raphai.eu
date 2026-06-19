export type DemoAccount = {
  id: number;
  name: string;
  slug: string;
  initial_balance: string;
  current_balance: string;
  is_default: boolean;
};

export type DemoBankrollCurvePoint = {
  at: string;
  balance: string;
  type: string;
  label: string | null;
  operation_id: number | null;
};

export type DemoBankrollCurve = {
  initial_balance: string;
  current_balance: string;
  points: DemoBankrollCurvePoint[];
};

export type DemoBankrollCurveResponse = { data: DemoBankrollCurve };

export type DemoJournal = {
  id: number;
  note: string;
  emotion: string | null;
  tags: string[];
  created_at: string | null;
};

export type PricingStatus = 'observed' | 'estimated' | 'manual' | 'unavailable';

export type DemoOperation = {
  id: number;
  speedway_race_id: number | null;
  race: {
    id: number;
    external_id: string;
    status: string;
    race_hour: number | null;
    race_minute: number | null;
  } | null;
  market_type: 'winner' | 'forecast' | 'tricast';
  bet_type: 'single' | 'combo';
  status: 'open' | 'settled';
  result: 'pending' | 'win' | 'loss' | 'void' | null;
  risk_enforced: boolean;
  after_stop: boolean;
  tags: string[];
  entry_payload_json: Record<string, unknown>;
  stake_amount: string;
  potential_gross_return: string;
  potential_net_profit: string;
  actual_gross_return: string | null;
  profit_loss: string | null;
  entry_position: number | null;
  entry_color: string | null;
  entry_odd: string | null;
  opened_at: string | null;
  settled_at: string | null;
  settlement_mode: 'auto' | 'manual' | null;
  journal: DemoJournal | null;
};

export type DemoAccountResponse = { data: DemoAccount };
export type DemoOperationsResponse = { data: DemoOperation[] };
export type DemoOperationResponse = { data: DemoOperation };

export type QuickEntry = {
  id: string;
  label: string;
  tier: 'primary' | 'alternate';
  market_type: 'winner' | 'forecast' | 'tricast';
  bet_type: 'single';
  order: string;
  entry_position?: number;
  entry_odd: number | null;
  pricing_status: PricingStatus;
  helper_text?: string | null;
};

export type PendingDemoRace = {
  id: number;
  external_id: string;
  status: 'pending';
  schedule_slot: string | null;
  race_hour: number | null;
  race_minute: number | null;
  pilot_odds_raw: string | null;
  pilot_odds: Array<{ position: number; odd: string }>;
  rank_1_position: number | null;
  rank_1_odd: string | null;
  rank_2_position: number | null;
  rank_2_odd: string | null;
  rank_3_position: number | null;
  rank_3_odd: string | null;
  rank_4_position: number | null;
  rank_4_odd: string | null;
  favorite_position: number | null;
  favorite_odd: string | null;
  underdog_position: number | null;
  underdog_odd: string | null;
  market_rank_forecast_order: string | null;
  market_rank_tricast_order: string | null;
  quick_entries: QuickEntry[];
};

export type PendingDemoRacesResponse = {
  data: PendingDemoRace[];
  meta: { total: number; limit: number };
};

export type QuickPresetId =
  | 'winner_favorite'
  | 'winner_underdog'
  | 'forecast_suggested'
  | 'tricast_suggested';
