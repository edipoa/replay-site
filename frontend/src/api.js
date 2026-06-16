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

export async function fetchVideo(id) {
  const res = await fetch(`${BASE}/api/videos/${id}`)
  if (!res.ok) throw new Error('Vídeo não encontrado ou expirado')
  return res.json()
}

export function downloadUrl(id) {
  return `${BASE}/api/videos/${id}/download`
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

export async function payGame(qrToken, type, clipId = null) {
  const body = { type }
  if (clipId) body.clip_id = clipId
  const res = await fetch(`${BASE}/api/games/${qrToken}/pay`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  })
  if (!res.ok) throw new Error('Falha ao processar pagamento')
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

export const adminLogout      = ()          => adminFetch('/api/admin/logout', { method: 'POST' })
export const fetchAdminStats  = ()          => adminFetch('/api/admin/stats')
export const fetchAdminSlots  = ()          => adminFetch('/api/admin/slots')
export const fetchAdminGroups = ()          => adminFetch('/api/admin/groups')
export const fetchAdminGames  = ()          => adminFetch('/api/admin/games')

export const createAdminSlot  = (data)      => adminFetch('/api/admin/slots',        { method: 'POST',   body: JSON.stringify(data) })
export const deleteAdminSlot  = (id)        => adminFetch(`/api/admin/slots/${id}`,  { method: 'DELETE' })

export const createAdminGroup = (data)      => adminFetch('/api/admin/groups',       { method: 'POST',   body: JSON.stringify(data) })
export const updateAdminGroup = (id, data)  => adminFetch(`/api/admin/groups/${id}`, { method: 'PUT',    body: JSON.stringify(data) })
export const deleteAdminGroup = (id)        => adminFetch(`/api/admin/groups/${id}`, { method: 'DELETE' })
