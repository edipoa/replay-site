<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import TopBar    from '../components/TopBar.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchShareLink } from '../api.js'
import { clockTime, formatDuration, shortDate } from '../utils.js'

const route = useRoute()
const token = route.params.token

const data     = ref(null)
const loading  = ref(true)
const expired  = ref(false)
const active   = ref(null)   // clip currently playing (game type)
const copied   = ref(false)

async function load() {
  try {
    data.value = await fetchShareLink(token)
    if (data.value.type === 'clip') {
      active.value = data.value.video
    } else if (data.value.videos?.length) {
      const clipId = route.query.clip
      const found  = clipId && data.value.videos.find(v => v.id === clipId)
      active.value = found || data.value.videos[0]
    }
  } catch (e) {
    expired.value = true
  } finally {
    loading.value = false
  }
}

const slotLabel = computed(() => {
  if (!data.value) return ''
  const d = new Date(data.value.slot_date + 'T00:00:00')
  const h = String(data.value.slot_hour).padStart(2, '0')
  const m = String(data.value.slot_minute ?? 0).padStart(2, '0')
  return `${d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} às ${h}:${m}`
})

function copyToClipboard(text) {
  if (navigator.clipboard?.writeText) {
    return navigator.clipboard.writeText(text)
  }
  const el = document.createElement('textarea')
  el.value = text
  el.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0'
  document.body.appendChild(el)
  el.focus()
  el.select()
  document.execCommand('copy')
  document.body.removeChild(el)
  return Promise.resolve()
}

async function copyLink() {
  try {
    let url = location.origin + location.pathname
    if (data.value?.type === 'game' && active.value && active.value.id !== data.value.videos[0]?.id) {
      url += '?clip=' + active.value.id
    }
    await copyToClipboard(url)
    copied.value = true
    setTimeout(() => { copied.value = false }, 2000)
  } catch {}
}

onMounted(load)
</script>

<template>
  <div style="min-height:100vh;display:flex;flex-direction:column;">
    <TopBar />

    <!-- Loading -->
    <div v-if="loading" class="sv-center">
      <div class="sv-spinner" />
    </div>

    <!-- Expirado / inválido -->
    <div v-else-if="expired" class="sv-center">
      <div class="sv-expired-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
        </svg>
      </div>
      <div class="display sv-expired-title">Link expirado</div>
      <p class="sv-expired-sub">Este link de compartilhamento não está mais disponível.<br>Os vídeos são removidos 24h após o jogo.</p>
      <a href="/" class="sv-back-btn">← Ver jogos disponíveis</a>
    </div>

    <!-- Conteúdo -->
    <main v-else-if="data" style="flex:1">

      <!-- ── CLIP único ── -->
      <template v-if="data.type === 'clip' && data.video">
        <div class="sv-wrap">
          <div class="sv-eyebrow">
            <span class="bar" />Jogo de {{ slotLabel }}
          </div>
          <h1 class="sv-title">Lance das {{ clockTime(data.video.triggered_at) }}</h1>

          <div class="sv-video-frame">
            <video :src="data.video.stream_url" controls preload="metadata" />
          </div>

          <div class="sv-actions">
            <a :href="data.video.download_url" class="btn gold-btn" download>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M12 3v12m0 0l-5-5m5 5l5-5M4 21h16"/>
              </svg>
              Baixar lance
            </a>
            <button class="btn ghost-btn" @click="copyLink">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M9 5h9a2 2 0 012 2v9M5 9h9a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9a2 2 0 012-2z"/>
              </svg>
              {{ copied ? 'Copiado!' : 'Copiar link' }}
            </button>
          </div>

          <div class="sv-meta">
            <div class="sv-meta-item"><span class="sv-meta-lbl">Data</span><span class="sv-meta-val">{{ shortDate(data.video.triggered_at) }}</span></div>
            <div class="sv-meta-item"><span class="sv-meta-lbl">Horário</span><span class="sv-meta-val">{{ clockTime(data.video.triggered_at) }}</span></div>
            <div class="sv-meta-item"><span class="sv-meta-lbl">Duração</span><span class="sv-meta-val">{{ formatDuration(data.video.duration_s) }}</span></div>
          </div>
        </div>
      </template>

      <!-- ── JOGO completo ── -->
      <template v-else-if="data.type === 'game'">
        <div class="sv-wrap sv-game-wrap">

          <!-- Player principal -->
          <div class="sv-main-col">
            <div class="sv-eyebrow">
              <span class="bar" />Jogo de {{ slotLabel }}
            </div>
            <h1 class="sv-title">
              {{ data.videos.length }} lance{{ data.videos.length !== 1 ? 's' : '' }}
            </h1>

            <div v-if="active" class="sv-video-frame">
              <video :key="active.id" :src="active.stream_url" controls preload="metadata" autoplay />
            </div>

            <div v-if="active" class="sv-active-info">
              <div class="sv-active-label">Lance das {{ clockTime(active.triggered_at) }}</div>
              <div class="sv-active-meta">{{ active.display_id }} · {{ formatDuration(active.duration_s) }}</div>
            </div>

            <div class="sv-actions">
              <a v-if="active" :href="active.download_url" class="btn gold-btn" download>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M12 3v12m0 0l-5-5m5 5l5-5M4 21h16"/>
                </svg>
                Baixar este lance
              </a>
              <button class="btn ghost-btn" @click="copyLink">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M9 5h9a2 2 0 012 2v9M5 9h9a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9a2 2 0 012-2z"/>
                </svg>
                {{ copied ? 'Copiado!' : 'Copiar link' }}
              </button>
            </div>

            <div v-if="active" class="sv-meta">
              <div class="sv-meta-item"><span class="sv-meta-lbl">Data</span><span class="sv-meta-val">{{ shortDate(active.triggered_at) }}</span></div>
              <div class="sv-meta-item"><span class="sv-meta-lbl">Horário</span><span class="sv-meta-val">{{ clockTime(active.triggered_at) }}</span></div>
              <div class="sv-meta-item"><span class="sv-meta-lbl">Duração</span><span class="sv-meta-val">{{ formatDuration(active.duration_s) }}</span></div>
            </div>
          </div>

          <!-- Lista de lances -->
          <aside class="sv-aside">
            <div class="sv-aside-title"><span class="bar" />Todos os lances</div>
            <div class="sv-clip-list">
              <div
                v-for="v in data.videos"
                :key="v.id"
                class="sv-clip-row"
                :class="{ active: active?.id === v.id }"
                @click="active = v"
              >
                <div class="sv-clip-thumb">
                  <img v-if="v.thumbnail_url" :src="v.thumbnail_url" alt="" />
                  <div v-else class="sv-clip-ph" />
                  <div class="sv-clip-dur">{{ formatDuration(v.duration_s) }}</div>
                </div>
                <div class="sv-clip-info">
                  <div class="sv-clip-title">Lance das {{ clockTime(v.triggered_at) }}</div>
                  <div class="sv-clip-meta">{{ v.display_id }}</div>
                </div>
              </div>
            </div>
          </aside>

        </div>
      </template>

    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
/* ── Spinner / Centro ── */
.sv-center {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  padding: 80px 24px; gap: 16px; text-align: center;
}
.sv-spinner {
  width: 40px; height: 40px; border-radius: 50%;
  border: 3px solid rgba(11,19,43,0.12); border-top-color: var(--gold);
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ── Expirado ── */
.sv-expired-icon {
  width: 64px; height: 64px; border-radius: 50%;
  background: rgba(11,19,43,0.06);
  display: grid; place-items: center; margin-bottom: 4px;
}
.sv-expired-icon svg { width: 32px; height: 32px; color: var(--muted); }
.sv-expired-title { font-size: 28px; color: var(--ink); margin-bottom: 8px; }
.sv-expired-sub { color: var(--muted); font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
.sv-back-btn {
  font-family: 'JetBrains Mono', monospace; font-size: 12px;
  color: var(--navy); text-decoration: underline;
}

/* ── Layout ── */
.sv-wrap {
  max-width: 720px; margin: 0 auto;
  padding: 48px 32px 80px;
}
.sv-game-wrap {
  max-width: 1280px;
  display: grid; grid-template-columns: 1fr 320px; gap: 40px;
  align-items: start;
}
@media (max-width: 960px) {
  .sv-game-wrap { grid-template-columns: 1fr; }
  .sv-aside { order: -1; }
}

.sv-eyebrow {
  display: flex; align-items: center; gap: 10px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted);
  margin-bottom: 8px;
}
.sv-eyebrow .bar { width: 20px; height: 2px; background: var(--gold); flex-shrink: 0; }

.sv-title {
  font-family: 'Archivo Black', sans-serif;
  font-size: clamp(22px, 4vw, 36px);
  letter-spacing: -0.02em; color: var(--ink);
  margin: 0 0 24px;
}

/* ── Video ── */
.sv-video-frame {
  background: #0a0a0a; border-radius: 12px; overflow: hidden;
  margin-bottom: 20px; aspect-ratio: 16/9;
}
.sv-video-frame video { width: 100%; height: 100%; display: block; }

/* ── Active info ── */
.sv-active-info { margin-bottom: 16px; }
.sv-active-label {
  font-family: 'Archivo Black', sans-serif; font-size: 16px;
  letter-spacing: -0.01em; color: var(--ink); margin-bottom: 2px;
}
.sv-active-meta { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }

/* ── Actions ── */
.sv-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 28px; }

/* ── Meta grid ── */
.sv-meta {
  display: grid; grid-template-columns: repeat(3, 1fr);
  gap: 1px; background: rgba(11,19,43,0.08);
  border-radius: 12px; overflow: hidden;
}
.sv-meta-item {
  background: white; padding: 16px;
  display: flex; flex-direction: column; gap: 4px;
}
.sv-meta-lbl { font-family: 'JetBrains Mono', monospace; font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); }
.sv-meta-val { font-family: 'Archivo Black', sans-serif; font-size: 15px; letter-spacing: -0.01em; color: var(--ink); }

/* ── Aside ── */
.sv-aside-title {
  display: flex; align-items: center; gap: 10px;
  font-family: 'JetBrains Mono', monospace; font-size: 10px;
  letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted);
  margin-bottom: 12px;
}
.sv-aside-title .bar { width: 16px; height: 2px; background: var(--gold); flex-shrink: 0; }

.sv-clip-list { display: flex; flex-direction: column; gap: 8px; }

.sv-clip-row {
  display: flex; align-items: center; gap: 12px;
  background: white; border: 1.5px solid rgba(11,19,43,0.08);
  border-radius: 12px; padding: 10px; cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.sv-clip-row:hover { border-color: rgba(11,19,43,0.2); }
.sv-clip-row.active { border-color: var(--gold); background: rgba(232,184,66,0.05); }

.sv-clip-thumb {
  position: relative; width: 64px; height: 44px;
  border-radius: 8px; overflow: hidden; flex-shrink: 0;
  background: rgba(11,19,43,0.08);
}
.sv-clip-thumb img { width: 100%; height: 100%; object-fit: cover; }
.sv-clip-ph { width: 100%; height: 100%; background: rgba(11,19,43,0.1); }
.sv-clip-dur {
  position: absolute; bottom: 3px; right: 3px;
  background: rgba(0,0,0,0.65); color: white;
  font-size: 9px; font-family: 'JetBrains Mono', monospace;
  padding: 1px 4px; border-radius: 3px;
}
.sv-clip-info { flex: 1; min-width: 0; }
.sv-clip-title {
  font-family: 'Archivo Black', sans-serif; font-size: 13px;
  letter-spacing: -0.01em; color: var(--ink);
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.sv-clip-meta { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-top: 2px; }

@media (max-width: 640px) {
  .sv-wrap { padding: 32px 16px 80px; }
  .sv-meta { grid-template-columns: repeat(2, 1fr); }
  .sv-meta-item:last-child { grid-column: span 2; }
}
</style>
