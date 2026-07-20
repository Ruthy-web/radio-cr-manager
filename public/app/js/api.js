/**
 * Client HTTP vers l'API Laravel (F6). Le jeton Sanctum est injecté sur
 * chaque appel ; une réponse 401 déclenche une déconnexion locale (R3 :
 * jamais de session active côté PWA sans jeton serveur valide).
 */
import { MetaStore } from './db.js';

const API_BASE = '/api/v1';

class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

let onUnauthorized = () => {};

export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn;
}

async function request(path, options = {}) {
  const token = await MetaStore.get('token');
  const headers = { Accept: 'application/json', ...(options.headers || {}) };

  if (token) headers.Authorization = `Bearer ${token}`;
  if (options.json !== undefined) headers['Content-Type'] = 'application/json';

  let response;

  try {
    response = await fetch(API_BASE + path, {
      ...options,
      headers,
      body: options.json !== undefined ? JSON.stringify(options.json) : options.body,
    });
  } catch (networkError) {
    throw new ApiError('Connexion impossible (hors ligne ?)', 0);
  }

  if (response.status === 401) {
    onUnauthorized();
    throw new ApiError('Session expirée, reconnectez-vous.', 401);
  }

  const data = await response.json().catch(() => ({}));

  if (!response.ok) {
    const message = data.message || data.errors ? formatErrors(data) : `Erreur HTTP ${response.status}`;
    throw new ApiError(message, response.status);
  }

  return data;
}

function formatErrors(data) {
  if (data.message) return data.message;
  if (data.errors) return Object.values(data.errors).flat().join(' ');
  return 'Requête refusée.';
}

export const Api = {
  login: (email, password) => request('/auth/login', { method: 'POST', json: { email, password } }),
  verifyTwoFactor: (challengeToken, code) =>
    request('/auth/2fa/verify', { method: 'POST', json: { challenge_token: challengeToken, code } }),
  logout: () => request('/auth/logout', { method: 'POST' }),
  heartbeat: () => request('/heartbeat'),
  catalog: () => request('/catalog'),

  syncPush: (reports) => request('/reports/sync', { method: 'POST', json: { reports } }),
  syncPull: (since) => request(`/reports/sync${since ? `?since=${encodeURIComponent(since)}` : ''}`),

  transcribe: (audioBlob, filename) => {
    const form = new FormData();
    form.append('audio', audioBlob, filename || 'dictation.webm');
    form.append('language', 'fr');
    return request('/stt', { method: 'POST', body: form });
  },

  refine: (results, conclusion, dictation) =>
    request('/ai/refine', { method: 'POST', json: { results, conclusion, dictation } }),

  draft: (payload) => request('/ai/draft', { method: 'POST', json: payload }),
};

export { ApiError };
