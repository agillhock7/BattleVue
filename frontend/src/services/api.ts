import { readCookie } from '@/utils/cookies';

const API_BASE = '/api';

export class ApiError extends Error {
  status: number;
  details: unknown;

  constructor(message: string, status: number, details?: unknown) {
    super(message);
    this.status = status;
    this.details = details;
  }
}

async function request<T>(path: string, method: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json'
  };

  if (method !== 'GET') {
    const csrf = readCookie('battlevue_csrf');
    if (csrf) {
      headers['X-CSRF-Token'] = csrf;
    }
  }

  const response = await fetch(`${API_BASE}${path}`, {
    method,
    credentials: 'include',
    headers,
    body: body ? JSON.stringify(body) : undefined
  });

  let json: any = null;
  try {
    json = await response.json();
  } catch {
    json = null;
  }

  if (!response.ok || !json?.ok) {
    throw new ApiError(json?.error || `Request failed ${response.status}`, response.status, json?.errors);
  }

  return json.data as T;
}

export const api = {
  get<T>(path: string) {
    return request<T>(path, 'GET');
  },
  post<T>(path: string, body?: unknown) {
    return request<T>(path, 'POST', body);
  }
};
