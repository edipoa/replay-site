<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import TopBar from '../components/TopBar.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchVideo, fetchVideos, downloadUrl } from '../api.js'
import { clockTime, formatDuration, shortDate, isoDate, relativeTime, timeRemaining, thumbClass } from '../utils.js'

const route  = useRoute()
const router = useRouter()
const id     = route.params.id

const video    = ref(null)
const related  = ref([])
const loading  = ref(true)
const error    = ref(null)
const showPh   = ref(true)
const toastMsg = ref('')
const toastOn  = ref(false)
const videoEl  = ref(null)

const rem = computed(() => video.value ? timeRemaining(video.value.expires_at) : null)

async function load() {
  try {
    video.value = await fetchVideo(id)
    const date = isoDate(video.value.triggered_at)
    const all  = await fetchVideos({ period: '24h', sort: 'recent' })
    const same = all.filter(v => v.id !== id && isoDate(v.triggered_at) === date)
    related.value = (same.length ? same : all.filter(v => v.id !== id)).slice(0, 6)
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

async function copyLink() {
  try {
    await navigator.clipboard.writeText(location.href)
    showToast('Link copiado')
  } catch {
    showToast('Falha ao copiar')
  }
}

function whatsappUrl() {
  const text = `Olha esse lance no Campo Society Viana: ${video.value?.clip_title ?? ''} — ${location.href}`
  return `https://wa.me/?text=${encodeURIComponent(text)}`
}

onMounted(load)
</script>

<template>
  <div>
    <TopBar>
      <RouterLink to="/" class="back-link">← Voltar aos clipes</RouterLink>
    </TopBar>

    <!-- Loading -->
    <div v-if="loading" style="max-width:1280px;margin:80px auto;text-align:center;font-family:'JetBrains Mono',monospace;color:var(--muted)">
      Carregando…
    </div>

    <!-- Error -->
    <div v-else-if="error" style="max-width:1280px;margin:80px auto;text-align:center">
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
          <a :href="downloadUrl(id)" class="btn gold-btn" download>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M12 3v12m0 0l-5-5m5 5l5-5M4 21h16"/>
            </svg>
            Baixar lance
          </a>
          <a :href="whatsappUrl()" class="btn whatsapp-btn" target="_blank" rel="noopener">
            <svg viewBox="0 0 24 24" fill="currentColor">
              <path d="M17.6 6.3A7.85 7.85 0 0012 4a7.93 7.93 0 00-6.7 12.05L4 21l5.05-1.3A7.92 7.92 0 0019.93 12a7.85 7.85 0 00-2.32-5.7zM12 18.5a6.55 6.55 0 01-3.34-.92l-.24-.14-2.9.76.78-2.84-.16-.25A6.59 6.59 0 1112 18.5zm3.59-4.93c-.2-.1-1.16-.57-1.34-.64s-.31-.1-.44.1-.5.64-.62.77-.23.15-.43.05a5.36 5.36 0 01-1.58-.97 5.92 5.92 0 01-1.1-1.36c-.11-.2 0-.3.09-.4l.3-.34.2-.34a.37.37 0 000-.35c0-.1-.44-1.06-.6-1.45s-.32-.33-.44-.34h-.38a.72.72 0 00-.52.24 2.21 2.21 0 00-.7 1.65 3.86 3.86 0 00.81 2.05A8.83 8.83 0 0012.62 16a4.36 4.36 0 002.13.6 1.85 1.85 0 001.21-.86 1.49 1.49 0 00.1-.85c-.05-.07-.18-.13-.38-.22z"/>
            </svg>
            Compartilhar no Zap
          </a>
          <button class="btn ghost-btn" @click="copyLink">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 5h9a2 2 0 012 2v9M5 9h9a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2v-9a2 2 0 012-2z"/>
            </svg>
            Copiar link
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
          <RouterLink
            v-for="v in related"
            :key="v.id"
            :to="`/player/${v.id}`"
            class="side-clip"
          >
            <div class="side-thumb" :class="v.seq % 2 === 0 ? 'side-thumb-night' : 'side-thumb-grass'">
              <div class="side-dur">{{ formatDuration(v.duration_s) }}</div>
            </div>
            <div class="side-info">
              <div class="t">Clipe das {{ clockTime(v.triggered_at) }}</div>
              <div class="m">{{ relativeTime(v.triggered_at) }} · {{ formatDuration(v.duration_s) }}</div>
            </div>
          </RouterLink>
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
