const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

function authHeaders(token) {
  return token ? { Authorization: `Bearer ${token}` } : {}
}

// ── Vídeos públicos ───────────────────────────────────────────────────────────

export async function fetchVideos({ period = '24h', date = '', sort = 'recent' } = {}) {
  const params = new URLSearchParams({ sort })
  date ? params.set('date', date) : params.set('period', period)
  const res = await fetch(`${BASE}/api/videos?${params}`)
  if (!res.ok) throw new Error('Falha ao carregar vídeos')
  return res.json()
}

export async function fetchVideo(id, token = null) {
  const res = await fetch(`${BASE}/api/videos/${id}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  })
  if (res.status === 401) throw new Error('UNAUTHORIZED')
  if (!res.ok) throw new Error('Vídeo não encontrado ou expirado')
  return res.json()
}

export function downloadUrl(id, token = null) {
  const base = `${BASE}/api/videos/${id}/download`
  return token ? `${base}?t=${encodeURIComponent(token)}` : base
}

export function thumbnailUrl(id) {
  return `${BASE}/api/videos/${id}/thumbnail`
}

// ── Auth de grupo ─────────────────────────────────────────────────────────────

export async function groupLogin(login, password) {
  const res = await fetch(`${BASE}/api/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ login, password }),
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error ?? 'Erro ao fazer login')
  }
  return res.json()
}

export async function groupLogout(token) {
  await fetch(`${BASE}/api/auth/logout`, {
    method: 'POST',
    headers: authHeaders(token),
  })
}

export async function fetchGroupVideos(token) {
  const res = await fetch(`${BASE}/api/group/videos`, {
    headers: authHeaders(token),
  })
  if (res.status === 401) throw new Error('UNAUTHORIZED')
  if (!res.ok) throw new Error('Falha ao carregar vídeos do grupo')
  return res.json()
}

// ── Games ─────────────────────────────────────────────────────────────────────

export async function fetchPublicGames() {
  const res = await fetch(`${BASE}/api/games`)
  if (!res.ok) throw new Error('Falha ao carregar jogos')
  return res.json()
}

export async function fetchCurrentGame(cameraId) {
  const res = await fetch(`${BASE}/api/games/current?camera=${encodeURIComponent(cameraId)}`)
  return { ok: res.ok, data: await res.json() }
}

export async function fetchGame(qrToken) {
  const res = await fetch(`${BASE}/api/games/${qrToken}`)
  if (!res.ok) throw new Error('Jogo não encontrado')
  return res.json()
}

export async function fetchGameVideos(qrToken, token) {
  const res = await fetch(`${BASE}/api/games/${qrToken}/videos`, {
    headers: authHeaders(token),
  })
  if (res.status === 401) throw new Error('UNAUTHORIZED')
  if (!res.ok) throw new Error('Falha ao carregar vídeos do jogo')
  return res.json()
}

export async function fetchGamePreviews(qrToken) {
  const res = await fetch(`${BASE}/api/games/${qrToken}/previews`)
  if (!res.ok) return { clips: [] }
  return res.json()
}

export async function fetchGameClips(qrToken) {
  const res = await fetch(`${BASE}/api/games/${qrToken}/clips`)
  if (!res.ok) throw new Error('Falha ao carregar clipes')
  return res.json()
}

export async function fetchFreeToken(qrToken) {
  const res = await fetch(`${BASE}/api/games/${qrToken}/free-token`, { method: 'POST' })
  if (!res.ok) throw new Error('Falha ao obter acesso')
  return res.json()
}

export async function payGame(qrToken, type, method, clipIds = [], email = '') {
  const body = { type, method, email }
  if (type === 'clips') body.clip_ids = clipIds
  const res = await fetch(`${BASE}/api/games/${qrToken}/pay`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error ?? 'Falha ao processar pagamento')
  }
  return res.json()
}

export async function recoverAccess(token) {
  const res = await fetch(`${BASE}/api/recover/${token}`)
  if (res.status === 404) throw new Error('EXPIRED')
  if (!res.ok) throw new Error('Falha ao recuperar acesso')
  return res.json()
}

export async function getPaymentStatus(paymentId) {
  const res = await fetch(`${BASE}/api/payments/${paymentId}/status`)
  if (!res.ok) throw new Error('Falha ao verificar pagamento')
  return res.json()
}

// ── Share links ───────────────────────────────────────────────────────────────

export async function createShareLink(token, gameId, clipId = null) {
  const body = { game_id: gameId }
  if (clipId) body.clip_id = clipId
  const res = await fetch(`${BASE}/api/share`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...authHeaders(token) },
    body: JSON.stringify(body),
  })
  if (res.status === 403) throw new Error('Acesso não autorizado')
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error ?? 'Falha ao gerar link')
  }
  return res.json()
}

export async function fetchShareLink(shareToken) {
  const res = await fetch(`${BASE}/api/share/${shareToken}`)
  if (res.status === 404) throw new Error('EXPIRED')
  if (!res.ok) throw new Error('Falha ao carregar link')
  return res.json()
}

// ── Admin ─────────────────────────────────────────────────────────────────────

async function adminFetch(path, opts = {}) {
  const token = localStorage.getItem('admin_token')
  const res = await fetch(`${BASE}${path}`, {
    ...opts,
    headers: { 'Content-Type': 'application/json', ...authHeaders(token), ...(opts.headers ?? {}) },
  })
  if (res.status === 401) throw new Error('ADMIN_UNAUTHORIZED')
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error ?? 'Erro na requisição')
  }
  return res.json()
}

export async function adminLogin(login, password) {
  const res = await fetch(`${BASE}/api/admin/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ login, password }),
  })
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error ?? 'Credenciais inválidas')
  }
  return res.json()
}

export const adminLogout         = ()           => adminFetch('/api/admin/logout', { method: 'POST' })
export const fetchAdminStats          = ()           => adminFetch('/api/admin/stats')
export const fetchAdminDashboard      = (from, to)  => adminFetch('/api/admin/dashboard?' + new URLSearchParams({ from, to }))
export const fetchAdminOrphanedClips  = ()           => adminFetch('/api/admin/orphaned-clips')
export const createGameForOrphanedClip = (id, data) => adminFetch(`/api/admin/orphaned-clips/${id}/create-game`, { method: 'POST', body: JSON.stringify(data) })
export const fetchAdminSlots  = ()          => adminFetch('/api/admin/slots')
export const fetchAdminGroups = ()          => adminFetch('/api/admin/groups')
export const fetchAdminGames  = ()          => adminFetch('/api/admin/games')

export const createAdminSlot  = (data)      => adminFetch('/api/admin/slots',        { method: 'POST',   body: JSON.stringify(data) })
export const updateAdminSlot  = (id, data)  => adminFetch(`/api/admin/slots/${id}`,  { method: 'PUT',    body: JSON.stringify(data) })
export const deleteAdminSlot  = (id)        => adminFetch(`/api/admin/slots/${id}`,  { method: 'DELETE' })

export const createAdminGroup = (data)      => adminFetch('/api/admin/groups',       { method: 'POST',   body: JSON.stringify(data) })
export const updateAdminGroup = (id, data)  => adminFetch(`/api/admin/groups/${id}`, { method: 'PUT',    body: JSON.stringify(data) })
export const deleteAdminGroup = (id)        => adminFetch(`/api/admin/groups/${id}`, { method: 'DELETE' })

// ── Assinatura ────────────────────────────────────────────────────────────────

export async function fetchGroupSubscription(token) {
  const res = await fetch(`${BASE}/api/group/subscription`, {
    headers: authHeaders(token),
  })
  if (res.status === 401) throw new Error('UNAUTHORIZED')
  if (!res.ok) throw new Error('Falha ao carregar assinatura')
  return res.json()
}

export async function initiateGroupSubscription(token, method) {
  const res = await fetch(`${BASE}/api/group/subscribe`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ...authHeaders(token) },
    body: JSON.stringify({ method }),
  })
  if (res.status === 401) throw new Error('UNAUTHORIZED')
  if (!res.ok) {
    const err = await res.json().catch(() => ({}))
    throw new Error(err.error ?? 'Falha ao iniciar pagamento')
  }
  return res.json()
}

export async function getSubscriptionPaymentStatus(paymentId) {
  const res = await fetch(`${BASE}/api/payments/sub/${paymentId}/status`)
  if (!res.ok) throw new Error('Falha ao verificar pagamento')
  return res.json()
}
