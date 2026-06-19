export async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(`/api${path}`, {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`API ${path} falhou: ${response.status}`);
  }

  return response.json() as Promise<T>;
}

async function parseApiError(response: Response, path: string): Promise<Error> {
  const payload = (await response.json().catch(() => ({}))) as { message?: string };
  return new Error(payload.message ?? `API ${path} falhou: ${response.status}`);
}

export async function apiPost<T>(path: string, body: unknown): Promise<T> {
  const response = await fetch(`/api${path}`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(body),
  });

  if (!response.ok) {
    throw await parseApiError(response, path);
  }

  return response.json() as Promise<T>;
}
