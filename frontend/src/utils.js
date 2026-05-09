const MONTHS = ['jan','fev','mar','abr','mai','jun','jul','ago','set','out','nov','dez']
const THUMBS  = ['thumb-grass','thumb-night','thumb-grass thumb-chalk','thumb-night thumb-chalk']

export function clockTime(iso) {
  const d = new Date(iso)
  return `${String(d.getUTCHours()).padStart(2,'0')}:${String(d.getUTCMinutes()).padStart(2,'0')}`
}

export function formatDuration(seconds) {
  return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2,'0')}`
}

export function shortDate(iso) {
  const d = new Date(iso)
  return `${String(d.getUTCDate()).padStart(2,'0')} ${MONTHS[d.getUTCMonth()]}`
}

export function isoDate(iso) {
  return new Date(iso).toISOString().slice(0, 10)
}

export function relativeTime(iso) {
  const sec = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (sec < 60) return 'agora'
  if (sec < 3600) return `${Math.floor(sec / 60)} min atrás`
  if (sec < 86400) return `${Math.floor(sec / 3600)}h atrás`
  return `${Math.floor(sec / 86400)}d atrás`
}

export function timeRemaining(expiresIso) {
  const diffMin = Math.max(0, Math.floor((new Date(expiresIso) - Date.now()) / 60000))
  const urgency  = 1 - Math.min(1, diffMin / (24 * 60))
  let text
  if (diffMin <= 0)   text = 'expirado'
  else if (diffMin < 60) text = `expira em ${diffMin}min`
  else {
    const h = Math.floor(diffMin / 60)
    const m = diffMin % 60
    text = m > 0 && h < 6 ? `expira em ${h}h${String(m).padStart(2,'0')}` : `expira em ${h}h`
  }
  return { text, urgency, minutes: diffMin }
}

export function thumbClass(seq) {
  return THUMBS[(seq - 1) % 4]
}
