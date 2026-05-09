const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

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
