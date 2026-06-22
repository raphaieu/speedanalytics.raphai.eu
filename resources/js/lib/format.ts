const TIMEZONE = 'America/Sao_Paulo';

export function formatDateTimeBr(value?: string | null, options?: Intl.DateTimeFormatOptions): string {
  if (!value) return '—';

  return new Date(value).toLocaleString('pt-BR', {
    timeZone: TIMEZONE,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    ...options,
  });
}

export function formatTimeBr(value?: string | null): string {
  if (!value) return '—';

  return new Date(value).toLocaleTimeString('pt-BR', {
    timeZone: TIMEZONE,
    hour: '2-digit',
    minute: '2-digit',
  });
}

export function formatRelativeBr(value?: string | null): string | null {
  if (!value) return null;

  const diffMs = Date.now() - new Date(value).getTime();
  const diffSec = Math.round(diffMs / 1000);

  if (diffSec < 45) return 'agora';
  if (diffSec < 90) return 'há 1 min';

  const diffMin = Math.round(diffSec / 60);
  if (diffMin < 60) return `há ${diffMin} min`;

  const diffHours = Math.round(diffMin / 60);
  if (diffHours < 24) return `há ${diffHours}h`;

  const diffDays = Math.round(diffHours / 24);
  return `há ${diffDays}d`;
}

/** Data de hoje em Brasília (YYYY-MM-DD). */
export function todayBr(): string {
  return new Date().toLocaleDateString('sv-SE', { timeZone: TIMEZONE });
}

export function formatDayPill(dateStr: string): string {
  const [, month, day] = dateStr.split('-');
  return `${day}/${month}`;
}

export function parseYearMonth(dateStr: string): string {
  return dateStr.slice(0, 7);
}

export function formatMonthLabel(yearMonth: string): string {
  const [year, month] = yearMonth.split('-').map(Number);
  const date = new Date(year, month - 1, 1);
  return date.toLocaleDateString('pt-BR', { month: 'short', year: 'numeric' });
}

export function formatYear(dateStr: string): string {
  return dateStr.slice(0, 4);
}

/** @deprecated use formatDayPill */
export function formatDayLabel(dateStr: string, today = todayBr()): string {
  return formatDayPill(dateStr);
}

export function formatDayLong(dateStr: string, today = todayBr()): string {
  const date = new Date(`${dateStr}T12:00:00`);
  const formatted = date.toLocaleDateString('pt-BR', {
    timeZone: TIMEZONE,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: dateStr.slice(0, 4) !== today.slice(0, 4) ? 'numeric' : undefined,
  });
  return formatted.charAt(0).toUpperCase() + formatted.slice(1);
}

export function formatUnits(value?: string | number | null): string {
  if (value === null || value === undefined || value === '') return '—';
  const num = typeof value === 'string' ? Number.parseFloat(value) : value;
  if (Number.isNaN(num)) return '—';
  const prefix = num > 0 ? '+' : '';
  return `${prefix}${num.toFixed(2)}u`;
}

export function formatCountdown(totalSeconds?: number | null): string {
  if (totalSeconds === null || totalSeconds === undefined) return '—';
  const seconds = Math.max(0, Math.round(totalSeconds));
  const minutes = Math.floor(seconds / 60);
  const remainder = seconds % 60;
  return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
}

export function formatSecondsAgo(totalSeconds?: number | null): string {
  if (totalSeconds === null || totalSeconds === undefined) return '—';
  const seconds = Math.max(0, Math.round(totalSeconds));
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes} min`;
  const hours = Math.floor(minutes / 60);
  return `${hours}h`;
}

export function isSameDayBr(isoDate?: string | null, reference = todayBr()): boolean {
  if (!isoDate) return false;
  const date = new Date(isoDate).toLocaleDateString('sv-SE', { timeZone: TIMEZONE });
  return date === reference;
}
