export async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(`/api${path}`, {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`API ${path} falhou: ${response.status}`);
  }

  return response.json() as Promise<T>;
}
