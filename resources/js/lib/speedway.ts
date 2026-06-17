export type PilotOdd = {
  position: number;
  odd: string;
};

export type RaceStatus = 'pending' | 'settled';

/** Corridas a cada 3 min → 20/h × 24h = 480 por dia. */
export const RACES_PER_DAY = 480;

/** ~175k–200k linhas/ano em operação 24/7. */
export const RACES_PER_YEAR_ESTIMATE = RACES_PER_DAY * 365;

const STATUS_LABELS: Record<RaceStatus, string> = {
  pending: 'Próxima',
  settled: 'Encerrada',
};

/** Cores fixas por posição no Speedway. */
export const PILOT_POSITION_COLORS: Record<number, string> = {
  1: 'Verde',
  2: 'Vermelho',
  3: 'Amarelo',
  4: 'Roxo',
};

const PILOT_COLOR_CLASSES: Record<string, string> = {
  Vermelho: 'bg-red-500',
  Azul: 'bg-blue-500',
  Amarelo: 'bg-yellow-400',
  Verde: 'bg-green-500',
  Roxo: 'bg-purple-500',
  Laranja: 'bg-orange-500',
  Preto: 'bg-zinc-800',
  Branco: 'bg-zinc-100 ring-1 ring-zinc-300',
};

export function statusLabel(status: RaceStatus): string {
  return STATUS_LABELS[status] ?? status;
}

/** Grade virtual BB Tips — Hora + Minutos (corridas a cada 3 min, não é relógio local). */
export function formatScheduleSlot(hour: string | null, minute: string | null): string {
  const h = hour ?? '?';
  const m = minute?.padStart(2, '0') ?? '??';
  return `${h}h${m}`;
}

export function parsePilotOdds(raw: string | null): PilotOdd[] {
  if (!raw) return [];

  return raw
    .split('|')
    .map((odd, index) => ({ position: index + 1, odd: odd.trim() }))
    .filter((item) => item.odd !== '');
}

export function favoritePilot(odds: PilotOdd[]): PilotOdd | null {
  if (odds.length === 0) return null;

  return odds.reduce((best, current) =>
    Number(current.odd) < Number(best.odd) ? current : best,
  );
}

/** Ordem dos pilotos por odd crescente (P = posição na pista). */
export function rankPilotsByOdds(raw: string | null): number[] {
  const odds = parsePilotOdds(raw);
  if (odds.length === 0) return [];

  return [...odds]
    .sort((a, b) => Number(a.odd) - Number(b.odd) || a.position - b.position)
    .map((p) => p.position);
}

export function forecastFromOdds(raw: string | null): string | null {
  const ranked = rankPilotsByOdds(raw);
  if (ranked.length < 2) return null;
  return `${ranked[0]}-${ranked[1]}`;
}

export function tricastFromOdds(raw: string | null): string | null {
  const ranked = rankPilotsByOdds(raw);
  if (ranked.length < 3) return null;
  return `${ranked[0]}-${ranked[1]}-${ranked[2]}`;
}

export function favoritePositionFromOdds(raw: string | null): number | null {
  const ranked = rankPilotsByOdds(raw);
  return ranked[0] ?? null;
}

export function pilotColorClass(color?: string | null): string {
  if (!color) return 'bg-muted';
  return PILOT_COLOR_CLASSES[color] ?? 'bg-muted';
}

export function pilotPositionColorClass(position?: number | null): string {
  if (!position) return 'bg-muted';
  return pilotColorClass(PILOT_POSITION_COLORS[position]);
}

export function formatWinner(race: {
  winner_position: number | null;
  winner_color: string | null;
  winner_odd: string | null;
  pilot_name: string | null;
}): string {
  if (!race.winner_position) return '—';

  const parts = [`P${race.winner_position}`];
  if (race.winner_color) parts.push(race.winner_color);
  if (race.winner_odd) parts.push(`@${race.winner_odd}`);
  return parts.join(' · ');
}
