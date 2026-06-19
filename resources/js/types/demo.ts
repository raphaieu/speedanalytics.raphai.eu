export type DemoAccount = {
  id: number;
  name: string;
  slug: string;
  initial_balance: string;
  current_balance: string;
  is_default: boolean;
};

export type DemoJournal = {
  id: number;
  note: string;
  emotion: string | null;
  tags: string[];
  created_at: string | null;
};

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
  journal: DemoJournal | null;
};

export type DemoAccountResponse = { data: DemoAccount };
export type DemoOperationsResponse = { data: DemoOperation[] };
export type DemoOperationResponse = { data: DemoOperation };
