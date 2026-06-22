<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import TopBar from '../components/TopBar.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchVideo, fetchVideos, downloadUrl, createShareLink } from '../api.js'
import { clockTime, formatDuration, shortDate, relativeTime, timeRemaining, thumbClass } from '../utils.js'

const route  = useRoute()
const router = useRouter()

const id         = route.params.id
const videoToken = localStorage.getItem(`video_token_${id}`) || null

const video      = ref(null)
const related    = ref([])
const loading    = ref(true)
const error      = ref(null)
const showPh     = ref(true)
const toastMsg   = ref('')
const toastOn    = ref(false)
const videoEl    = ref(null)
const sharing    = ref(false)

const rem = computed(() => video.value ? timeRemaining(video.value.expires_at) : null)

function hasToken(videoId) { return !!localStorage.getItem(`video_token_${videoId}`) }

async function load() {
  if (!videoToken) {
    error.value = 'UNAUTHORIZED'
    loading.value = false
    return
  }
  try {
    video.value = await fetchVideo(id, videoToken)
    const all  = await fetchVideos({ period: '24h', sort: 'recent' })
    const sameGame = video.value.game_id
      ? all.filter(v => v.id !== id && v.game_id === video.value.game_id)
      : []
    related.value = sameGame.slice(0, 6)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function onPlay() { showPh.value = false }

function showToast(msg) {
  toastMsg.value = msg
  toastOn.value = true
  setTimeout(() => { toastOn.value = false }, 1800)
}

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

async function shareClip() {
  if (!video.value || sharing.value) return
  sharing.value = true
  try {
    const gameId = video.value.game_id
    if (!gameId) { showToast('Jogo não identificado'); return }
    const res = await createShareLink(videoToken, gameId, id)
    await copyToClipboard(res.url)
    showToast('Link copiado!')
  } catch (e) {
    showToast('Falha ao gerar link')
    console.error('[share]', e)
  } finally {
    sharing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div style="min-height:100vh;display:flex;flex-direction:column;">
    <TopBar>
      <RouterLink to="/" class="back-link">← Voltar aos clipes</RouterLink>
    </TopBar>

    <!-- Loading -->
    <div v-if="loading" style="flex:1;max-width:1280px;margin:80px auto;text-align:center;font-family:'JetBrains Mono',monospace;color:var(--muted)">
      Carregando…
    </div>

    <!-- Sem acesso -->
    <div v-else-if="error === 'UNAUTHORIZED'" style="flex:1;max-width:560px;margin:80px auto;text-align:center;padding:0 24px">
      <div class="display" style="font-size:28px;color:var(--ink);margin-bottom:8px">Acesso necessário</div>
      <p style="color:var(--muted);margin-bottom:24px">Este clipe é pago. Acesse a página do jogo para comprar.</p>
      <a href="/" style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--navy);text-decoration:underline">← Ver jogos disponíveis</a>
    </div>

    <!-- Error -->
    <div v-else-if="error" style="flex:1;max-width:1280px;margin:80px auto;text-align:center">
      <div class="display" style="font-size:28px;color:var(--ink);margin-bottom:8px">Clipe não encontrado</div>
      <div style="color:var(--muted)">{{ error }}</div>
    </div>

    <!-- Player -->
    <div v-else-if="video" class="player-wrap">
      <!-- Main column -->
      <section>
        <div class="breadcrumb">
          <RouterLink to="/">Clipes</RouterLink>
          <span class="gold"> / </span>
          <span>{{ shortDate(video.triggered_at) }}</span>
          <span class="gold"> / </span>
          <span>{{ video.display_id.toUpperCase() }}</span>
        </div>

        <h1 class="video-title">Clipe das {{ clockTime(video.triggered_at) }}</h1>

        <!-- Expiry banner -->
        <div class="expire-banner" :class="rem && rem.urgency >= 0.92 ? 'urgent' : ''">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
          </svg>
          <div v-if="rem && rem.minutes > 0">
            <strong>Disponível por 24h</strong> — depois disso o vídeo é removido. Baixe ou compartilhe agora.
          </div>
          <div v-else><strong>Clipe expirado</strong> — este lance não está mais disponível.</div>
          <div v-if="rem && rem.minutes > 0" class="countdown">
            {{ rem.text.replace('expira em ', '').toUpperCase() }}
          </div>
        </div>

        <!-- Video -->
        <div class="video-frame">
          <video
            ref="videoEl"
            :src="video.stream_url"
            controls
            preload="metadata"
            @play="onPlay"
          />
          <div v-if="showPh" class="placeholder-overlay" @click="videoEl?.src && videoEl.play().catch(() => {})">
            <div class="ph-text">▶ Toque para reproduzir o lance</div>
          </div>
        </div>

        <!-- Actions -->
        <div class="actions">
          <a :href="downloadUrl(id, videoToken)" class="btn gold-btn" download>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 3v12m0 0l-5-5m5 5l5-5M4 21h16"/>
            </svg>
            Baixar lance
          </a>
          <button v-if="video.game_id" class="btn ghost-btn" :disabled="sharing" @click="shareClip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/>
            </svg>
            {{ sharing ? 'Gerando…' : 'Compartilhar' }}
          </button>
        </div>

        <!-- Meta grid -->
        <div class="meta-grid">
          <div class="item">
            <div class="lbl">Data</div>
            <div class="val">{{ shortDate(video.triggered_at) }}</div>
          </div>
          <div class="item">
            <div class="lbl">Horário</div>
            <div class="val">{{ clockTime(video.triggered_at) }}</div>
          </div>
          <div class="item">
            <div class="lbl">Duração</div>
            <div class="val">{{ formatDuration(video.duration_s) }}</div>
          </div>
        </div>
      </section>

      <!-- Sidebar -->
      <aside class="player-aside">
        <div class="side-title"><span class="bar" />Mais lances do jogo</div>
        <div class="side-list">
          <component
            :is="hasToken(v.id) ? 'RouterLink' : 'div'"
            v-for="v in related"
            :key="v.id"
            :to="hasToken(v.id) ? `/player/${v.id}` : undefined"
            class="side-clip"
            :class="{ 'side-clip-locked': !hasToken(v.id) }"
          >
            <div class="side-thumb" :class="v.seq % 2 === 0 ? 'side-thumb-night' : 'side-thumb-grass'">
              <div v-if="!hasToken(v.id)" class="side-lock">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="14" height="14"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
              </div>
              <div v-else class="side-dur">{{ formatDuration(v.duration_s) }}</div>
            </div>
            <div class="side-info">
              <div class="t">Clipe das {{ clockTime(v.triggered_at) }}</div>
              <div class="m">
                {{ relativeTime(v.triggered_at) }} · {{ formatDuration(v.duration_s) }}
                <span v-if="!hasToken(v.id)" class="side-buy-hint">· Voltar ao jogo para comprar</span>
              </div>
            </div>
          </component>
        </div>
      </aside>
    </div>

    <AppFooter />

    <!-- Toast -->
    <div class="toast" :class="{ show: toastOn }">
      {{ toastMsg }} <span class="gold">✓</span>
    </div>
  </div>
</template>
