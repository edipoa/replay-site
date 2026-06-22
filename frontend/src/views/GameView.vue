<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TopBar    from '../components/TopBar.vue'
import VideoCard from '../components/VideoCard.vue'
import AppFooter from '../components/AppFooter.vue'
import {
  fetchGame, fetchGameVideos, fetchGamePreviews, fetchGameClips,
  fetchFreeToken, payGame, getPaymentStatus, createShareLink,
} from '../api.js'
import { clockTime, formatDuration } from '../utils.js'

const route  = useRoute()
const router = useRouter()

const qrToken     = route.params.qrToken
const STORAGE_KEY = `game_token_${qrToken}`

const game          = ref(null)
const videos        = ref([])
const previews      = ref([])
const clips         = ref([])
const clipsLoading  = ref(false)
const cart          = ref(new Set())
const loading       = ref(true)
const paying        = ref(false)
const sharing       = ref(false)
const shareCopied   = ref(false)
const error         = ref(null)
const view          = ref('loading')

// Payment modal state
const modal           = ref(null)  // null | 'method-select' | 'pix-qr' | 'polling'
const payment         = ref(null)  // response from payGame
const pendingPurchase = ref(null)  // { type, clipIds }
const pixCountdown    = ref(0)
const pixCopied       = ref(false)
let   pollTimer       = null
let   countdownTimer  = null

// ── Computed ──────────────────────────────────────────────────────────────────

const pendingAmount = computed(() => {
  if (!pendingPurchase.value) return 0
  return pendingPurchase.value.type === 'full'
    ? 25
    : pendingPurchase.value.clipIds.length * 5
})
const cartTotal = computed(() => cart.value.size * 5)

function formatBRL(val) {
  return 'R$ ' + Number(val).toFixed(2).replace('.', ',')
}
function formatCountdown(s) {
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}

// ── Store helpers ─────────────────────────────────────────────────────────────

function storeTokensForVideos(vids, token) {
  vids.forEach(v => localStorage.setItem(`video_token_${v.id}`, token))
}

// ── Load ──────────────────────────────────────────────────────────────────────

async function load() {
  loading.value = true
  error.value   = null
  try {
    game.value = await fetchGame(qrToken)
    console.log('[GameView] game loaded, access_type=', game.value.access_type)

    const savedToken = localStorage.getItem(STORAGE_KEY)
    if (savedToken) {
      console.log('[GameView] found game token, loading videos')
      await loadVideos(savedToken)
      return
    }

    if (game.value.free_mode) {
      console.log('[GameView] free mode — fetching free token')
      const data = await fetchFreeToken(qrToken)
      localStorage.setItem(STORAGE_KEY, data.token)
      await loadVideos(data.token)
      return
    }

    if (game.value.access_type === 'group') {
      const groupTk = localStorage.getItem('group_token')
      if (groupTk) {
        await loadVideos(groupTk)
        return
      }
      view.value = 'group-or-avulso'
      fetchGamePreviews(qrToken).then(data => { previews.value = data.clips ?? [] }).catch(() => {})
      return
    }

    view.value = 'avulso-preview'
    fetchGamePreviews(qrToken).then(data => { previews.value = data.clips ?? [] }).catch(() => {})
  } catch (e) {
    console.error('[GameView] load error:', e)
    error.value = e.message
    view.value  = 'error'
  } finally {
    loading.value = false
  }
}

async function loadVideos(token) {
  try {
    console.log('[GameView] fetchGameVideos with token=', token.slice(0,8)+'...')
    const vids = await fetchGameVideos(qrToken, token)
    const active = vids.filter(v => new Date(v.expires_at) > Date.now())
    console.log('[GameView] got', vids.length, 'videos,', active.length, 'active → view=videos')
    videos.value = active
    storeTokensForVideos(vids, token)
    view.value = 'videos'
  } catch (e) {
    console.error('[GameView] loadVideos error:', e.message)
    if (e.message === 'UNAUTHORIZED') {
      localStorage.removeItem(STORAGE_KEY)
      if (game.value?.access_type === 'group') {
        view.value = 'group-or-avulso'
        fetchGamePreviews(qrToken).then(data => { previews.value = data.clips ?? [] }).catch(() => {})
      } else {
        view.value = 'avulso-preview'
        fetchGamePreviews(qrToken).then(data => { previews.value = data.clips ?? [] }).catch(() => {})
      }
    } else {
      error.value = e.message
    }
  }
}

// ── Payment modal ─────────────────────────────────────────────────────────────

const buyerEmail = ref('')
const emailValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(buyerEmail.value.trim()))

function openPaymentModal(type, clipIds = []) {
  pendingPurchase.value = { type, clipIds }
  error.value = null
  modal.value = 'method-select'
}

async function selectMethod(method) {
  paying.value = true
  error.value  = null
  try {
    const { type, clipIds } = pendingPurchase.value
    const data = await payGame(qrToken, type, method, clipIds, buyerEmail.value.trim())
    payment.value = data

    if (method === 'pix') {
      if (data.mock) {
        // Ambiente de dev: pagamento já aprovado, só buscar os tokens
        modal.value = 'polling'
        const approved = await getPaymentStatus(data.payment_id)
        handlePaymentApproved(approved)
      } else {
        modal.value = 'pix-qr'
        startCountdown(data.expires_in ?? 1800)
        startPolling(data.payment_id)
      }
    } else {
      // card: redirect to Mercado Pago Checkout Pro
      window.location.href = data.checkout_url
    }
  } catch (e) {
    error.value = e.message
  } finally {
    paying.value = false
  }
}

function startCountdown(seconds) {
  clearInterval(countdownTimer)
  pixCountdown.value = seconds
  countdownTimer = setInterval(() => {
    if (pixCountdown.value > 0) pixCountdown.value--
    else clearInterval(countdownTimer)
  }, 1000)
}

function startPolling(paymentId) {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const data = await getPaymentStatus(paymentId)
      if (data.status === 'approved') {
        handlePaymentApproved(data)
      } else if (['rejected', 'cancelled'].includes(data.status)) {
        stopPolling()
        modal.value = null
        error.value = 'Pagamento não aprovado. Tente novamente.'
      }
    } catch {}
  }, 3000)
}

function stopPolling() {
  clearInterval(pollTimer)
  pollTimer = null
}

async function handlePaymentApproved(data) {
  stopPolling()
  clearInterval(countdownTimer)
  modal.value = null

  if (data.type === 'full') {
    localStorage.setItem(STORAGE_KEY, data.token)
    await loadVideos(data.token)
  } else {
    Object.entries(data.tokens).forEach(([clipId, tok]) => {
      localStorage.setItem(`video_token_${clipId}`, tok)
    })
    if (clips.value.length === 0) {
      try { clips.value = await fetchGameClips(qrToken) } catch {}
    }
    const purchased = new Set(Object.keys(data.tokens))
    videos.value = clips.value.filter(c => purchased.has(c.id) && new Date(c.expires_at) > Date.now())
    view.value = 'videos'
  }
}

async function copyPix() {
  try {
    await navigator.clipboard.writeText(payment.value.qr_code)
    pixCopied.value = true
    setTimeout(() => { pixCopied.value = false }, 2000)
  } catch {}
}

function closeModal() {
  if (modal.value === 'pix-qr' || modal.value === 'polling') return
  modal.value = null
}

// ── Clips carrinho ────────────────────────────────────────────────────────────

async function goToClips() {
  if (clips.value.length === 0) {
    clipsLoading.value = true
    try {
      clips.value = await fetchGameClips(qrToken)
    } catch (e) {
      error.value = e.message
      return
    } finally {
      clipsLoading.value = false
    }
  }
  view.value = 'avulso-clips'
}

function toggleCart(id) {
  if (cart.value.has(id)) cart.value.delete(id)
  else cart.value.add(id)
  cart.value = new Set(cart.value)
}

function selectAll() {
  cart.value = new Set(clips.value.map(c => c.id))
}

function buyFull() { openPaymentModal('full') }

function buyCart() {
  if (cart.value.size === 0) return
  openPaymentModal('clips', Array.from(cart.value))
}

// ── Grupo ─────────────────────────────────────────────────────────────────────

function goGroupLogin() {
  localStorage.setItem('after_login', `/jogo/${qrToken}`)
  router.push('/login')
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

async function shareGame() {
  if (!game.value || sharing.value) return
  sharing.value = true
  try {
    const token = localStorage.getItem(STORAGE_KEY) || localStorage.getItem('group_token')
    const res   = await createShareLink(token, game.value.id)
    await copyToClipboard(res.url)
    shareCopied.value = true
    setTimeout(() => { shareCopied.value = false }, 2500)
  } catch (e) {
    error.value = 'Falha ao gerar link de compartilhamento'
    console.error('[share]', e)
  } finally {
    sharing.value = false
  }
}

function formatSlot(g) {
  if (!g) return ''
  const d = new Date(g.slot_date + 'T00:00:00')
  const h = String(g.slot_hour).padStart(2, '0')
  const m = String(g.slot_minute ?? 0).padStart(2, '0')
  return `${d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} às ${h}:${m}`
}

onMounted(async () => {
  await load()

  const paymentId = route.query.payment
  const pstatus   = route.query.pstatus

  if (paymentId && pstatus !== 'failure') {
    payment.value = { payment_id: paymentId }
    modal.value   = 'polling'
    // Check immediately before starting interval
    try {
      const data = await getPaymentStatus(paymentId)
      if (data.status === 'approved') { handlePaymentApproved(data); return }
    } catch {}
    startPolling(paymentId)
  } else if (paymentId && pstatus === 'failure') {
    error.value = 'Pagamento não aprovado. Tente novamente.'
  }
})

onUnmounted(() => {
  stopPolling()
  clearInterval(countdownTimer)
})
</script>

<template>
  <div class="page-layout">
    <TopBar />

    <!-- Loading -->
    <div v-if="loading" class="gv-center">
      <div class="gv-spinner" />
    </div>

    <!-- Erro fatal -->
    <div v-else-if="view === 'error'" class="gv-center">
      <div class="display" style="color:var(--ink)">Jogo não encontrado</div>
      <p style="color:var(--muted)">{{ error }}</p>
      <router-link to="/" class="back-link">← Voltar aos jogos</router-link>
    </div>

    <template v-else-if="game">

      <!-- ── GRUPO OU AVULSO ── -->
      <template v-if="view === 'group-or-avulso'">
        <div class="ap-header">
          <router-link to="/" class="back-link" style="margin-bottom:24px;display:inline-block">← Jogos</router-link>
          <div class="hero-eyebrow"><span class="bar" />Replays do jogo</div>
          <h1 class="display gv-h1">Jogo de<br /><span class="gold">{{ formatSlot(game) }}</span></h1>
          <p class="gv-sub">{{ game.clip_count }} clipe{{ game.clip_count !== 1 ? 's' : '' }} disponíve{{ game.clip_count !== 1 ? 'is' : 'l' }}</p>
        </div>

        <!-- Opções de acesso -->
        <div class="ap-options">
          <div class="gv-option" @click="goGroupLogin">
            <div class="gv-option-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Entrar com login do time</div>
              <div class="gv-option-sub">Use o login e senha do grupo fixo</div>
            </div>
            <svg class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>

          <div class="goa-divider"><span>ou</span></div>

          <div class="gv-option gv-option-gold" @click="buyFull">
            <div class="gv-option-icon gv-icon-gold">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Comprar jogo completo</div>
              <div class="gv-option-sub">Todos os {{ game.clip_count }} clipes · <strong>R$&nbsp;25,00</strong></div>
            </div>
            <svg class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>

          <div class="gv-option" @click="goToClips">
            <div class="gv-option-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Selecionar clipes</div>
              <div class="gv-option-sub">R$&nbsp;5,00 por clipe</div>
            </div>
            <svg class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>

          <div v-if="error" class="gv-error">{{ error }}</div>
        </div>

        <!-- Grid de preview — todos os clipes com GIF -->
        <div v-if="previews.length > 0 || game.clip_count > 0" class="ap-preview-wrap">
          <div class="ap-teaser-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;flex-shrink:0">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Prévia dos lances
          </div>
          <div class="ap-preview-grid">
            <div
              v-for="(clip, idx) in previews"
              :key="idx"
              class="ap-prev-card"
            >
              <div class="ap-prev-media">
                <img
                  v-if="clip.gif_url"
                  :src="clip.gif_url"
                  alt=""
                  draggable="false"
                  class="ap-prev-gif"
                />
                <img
                  v-else-if="clip.thumbnail_url"
                  :src="clip.thumbnail_url"
                  alt=""
                  draggable="false"
                  class="ap-prev-gif"
                />
                <div v-else class="ap-prev-ph" />
                <div class="ap-prev-lock-badge">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </div>
              </div>
              <div class="ap-prev-label">Lance {{ clip.seq }}</div>
            </div>
          </div>
        </div>
      </template>

      <!-- ── AVULSO: preview + compra ── -->
      <template v-else-if="view === 'avulso-preview'">
        <!-- Cabeçalho -->
        <div class="ap-header">
          <router-link to="/" class="back-link" style="margin-bottom:24px;display:inline-block">← Jogos</router-link>
          <div class="hero-eyebrow"><span class="bar" />Replays do jogo</div>
          <h1 class="display gv-h1">Jogo de<br /><span class="gold">{{ formatSlot(game) }}</span></h1>
          <p class="gv-sub">{{ game.clip_count }} clipe{{ game.clip_count !== 1 ? 's' : '' }} disponível{{ game.clip_count !== 1 ? 'is' : '' }}</p>
        </div>

        <!-- Opções de compra — antes da prévia -->
        <div class="ap-options">
          <div class="gv-option gv-option-gold" @click="buyFull">
            <div class="gv-option-icon gv-icon-gold">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Comprar jogo completo</div>
              <div class="gv-option-sub">Acesso a todos os {{ game.clip_count }} clipes · <strong>R$&nbsp;25,00</strong></div>
            </div>
            <svg class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>

          <div class="gv-option" @click="goToClips">
            <div class="gv-option-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Selecionar clipes</div>
              <div class="gv-option-sub">R$&nbsp;5,00 por clipe · pague só o que quer</div>
            </div>
            <div v-if="clipsLoading" class="gv-option-arrow">
              <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/>
              </svg>
            </div>
            <svg v-else class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>

          <div v-if="error" class="gv-error">{{ error }}</div>
        </div>

        <!-- Grid de preview — todos os clipes com GIF -->
        <div v-if="previews.length > 0 || game.clip_count > 0" class="ap-preview-wrap">
          <div class="ap-teaser-label">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px;flex-shrink:0">
              <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            Prévia dos lances
          </div>
          <div class="ap-preview-grid">
            <div
              v-for="(clip, idx) in previews"
              :key="idx"
              class="ap-prev-card"
            >
              <div class="ap-prev-media">
                <img
                  v-if="clip.gif_url"
                  :src="clip.gif_url"
                  alt=""
                  draggable="false"
                  class="ap-prev-gif"
                />
                <img
                  v-else-if="clip.thumbnail_url"
                  :src="clip.thumbnail_url"
                  alt=""
                  draggable="false"
                  class="ap-prev-gif"
                />
                <div v-else class="ap-prev-ph" />
                <div class="ap-prev-lock-badge">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                  </svg>
                </div>
              </div>
              <div class="ap-prev-label">Lance {{ clip.seq }}</div>
            </div>
          </div>
        </div>
      </template>

      <!-- ── AVULSO: seleção de clips ── -->
      <main v-else-if="view === 'avulso-clips'" class="gv-choose">
        <div class="gv-header">
          <button class="back-link" style="background:none;border:none;cursor:pointer;margin-bottom:24px;display:inline-block;padding:0" @click="view = 'avulso-preview'">← Voltar</button>
          <div class="hero-eyebrow"><span class="bar" />Escolha os clipes</div>
          <h1 class="display gv-h1">{{ game.clip_count }} lance{{ game.clip_count !== 1 ? 's' : '' }} disponíve{{ game.clip_count !== 1 ? 'is' : 'l' }}</h1>
          <p class="gv-sub">R$&nbsp;5,00 por clipe · selecione os lances que quer desbloquear</p>
        </div>

        <div class="clips-list">
          <div
            v-for="clip in clips"
            :key="clip.id"
            class="clip-row"
            :class="{ selected: cart.has(clip.id) }"
            @click="toggleCart(clip.id)"
          >
            <div class="clip-row-thumb">
              <img v-if="clip.gif_url" :src="clip.gif_url" alt="" draggable="false" />
              <img v-else-if="clip.thumbnail_url" :src="clip.thumbnail_url" alt="" draggable="false" />
              <div v-else class="clip-row-ph" />
              <div class="clip-row-dur">{{ formatDuration(clip.duration_s) }}</div>
            </div>
            <div class="clip-row-info">
              <div class="clip-row-title">Lance das {{ clockTime(clip.triggered_at) }}</div>
              <div class="clip-row-meta">{{ clip.display_id }}</div>
            </div>
            <div class="clip-row-check" :class="{ active: cart.has(clip.id) }">
              <svg v-if="cart.has(clip.id)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <path d="M20 6L9 17l-5-5"/>
              </svg>
            </div>
          </div>
        </div>

        <div v-if="error" class="gv-error" style="margin-bottom:16px">{{ error }}</div>

        <!-- Barra do carrinho -->
        <div class="cart-bar">
          <div class="cart-bar-info">
            <template v-if="cart.size > 0">
              <strong>{{ cart.size }} clipe{{ cart.size !== 1 ? 's' : '' }}</strong>
              <span class="cart-bar-price"> · {{ formatBRL(cartTotal) }}</span>
            </template>
            <template v-else>Nenhum clipe selecionado</template>
          </div>
          <div class="cart-bar-actions">
            <button class="cart-select-all" @click.stop="selectAll">Todos</button>
            <button class="cart-pay-btn" :disabled="cart.size === 0" @click="buyCart">
              Desbloquear
            </button>
          </div>
        </div>
      </main>

      <!-- ── VÍDEOS ── -->
      <main v-else-if="view === 'videos'" class="main-wrap">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
          <div class="hero-eyebrow" style="margin-bottom:0">
            <span class="bar" />{{ formatSlot(game) }} · {{ videos.length }} clipes
          </div>
          <button class="gv-share-btn" :disabled="sharing" @click="shareGame">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:16px;height:16px">
              <path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8M16 6l-4-4-4 4M12 2v13"/>
            </svg>
            {{ shareCopied ? 'Link copiado!' : sharing ? 'Gerando…' : 'Compartilhar jogo' }}
          </button>
        </div>
        <div class="grid">
          <div v-if="videos.length === 0" class="empty">
            <div class="display">Nenhum clipe ainda</div>
          </div>
          <VideoCard v-else v-for="v in videos" :key="v.id" :video="v" />
        </div>
      </main>

    </template>

    <AppFooter />

    <!-- ── MODAL DE PAGAMENTO ── -->
    <Teleport to="body">
      <div v-if="modal" class="pm-backdrop" :class="{ 'pm-dismissible': modal === 'method-select' }" @click.self="closeModal">
        <div class="pm-card">

          <!-- Method select -->
          <template v-if="modal === 'method-select'">
            <button class="pm-close-btn" @click="closeModal" aria-label="Fechar">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>

            <div class="pm-eyebrow">Forma de pagamento</div>
            <div class="pm-big-amount">{{ formatBRL(pendingAmount) }}</div>
            <div class="pm-desc">
              {{ pendingPurchase?.type === 'full'
                ? `Jogo completo · ${game?.clip_count} clipes`
                : `${pendingPurchase?.clipIds.length} clipe${pendingPurchase?.clipIds.length !== 1 ? 's' : ''} selecionado${pendingPurchase?.clipIds.length !== 1 ? 's' : ''}` }}
            </div>

            <div class="pm-email-field">
              <label class="pm-email-label" for="buyer-email">E-mail para receber o link dos clipes</label>
              <input
                id="buyer-email"
                v-model="buyerEmail"
                type="email"
                class="pm-email-input"
                placeholder="seu@email.com"
                autocomplete="email"
                :disabled="paying"
              />
            </div>

            <div v-if="error" class="pm-error">{{ error }}</div>

            <div class="pm-methods">
              <button class="pm-method pm-method-pix" :disabled="paying || !emailValid" @click="selectMethod('pix')">
                <div class="pm-method-icon pm-icon-pix">
                  <svg viewBox="0 0 24 24" fill="currentColor" width="22" height="22">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                  </svg>
                </div>
                <div class="pm-method-text">
                  <div class="pm-method-name">Pix</div>
                  <div class="pm-method-sub">Aprovação imediata · 30 min para pagar</div>
                </div>
                <svg v-if="paying" class="spin pm-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px">
                  <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/>
                </svg>
                <svg v-else class="pm-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M9 18l6-6-6-6"/>
                </svg>
              </button>

              <button class="pm-method" :disabled="paying || !emailValid" @click="selectMethod('credit_card')">
                <div class="pm-method-icon">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22">
                    <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
                  </svg>
                </div>
                <div class="pm-method-text">
                  <div class="pm-method-name">Cartão de crédito</div>
                  <div class="pm-method-sub">Você será redirecionado ao Mercado Pago</div>
                </div>
                <svg class="pm-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                  <path d="M9 18l6-6-6-6"/>
                </svg>
              </button>
            </div>
          </template>

          <!-- Pix QR code -->
          <template v-else-if="modal === 'pix-qr'">
            <div class="pm-eyebrow">Pague via Pix</div>
            <div class="pm-big-amount">{{ formatBRL(payment?.amount) }}</div>

            <div class="pm-qr-wrap">
              <img
                v-if="payment?.qr_code_base64"
                :src="`data:image/png;base64,${payment.qr_code_base64}`"
                class="pm-qr-img"
                alt="QR Code Pix"
              />
              <div v-else class="pm-qr-placeholder">
                <div class="gv-spinner" />
              </div>
            </div>

            <div class="pm-copy-row">
              <input readonly :value="payment?.qr_code" class="pm-code-input" />
              <button class="pm-copy-btn" :class="{ copied: pixCopied }" @click="copyPix">
                {{ pixCopied ? '✓ Copiado' : 'Copiar' }}
              </button>
            </div>

            <div class="pm-countdown">
              Expira em <strong>{{ formatCountdown(pixCountdown) }}</strong>
            </div>

            <div class="pm-waiting">
              <div class="pm-dot-spin">
                <span /><span /><span />
              </div>
              Aguardando confirmação...
            </div>

            <p class="pm-footnote">O acesso é liberado automaticamente após o pagamento</p>
          </template>

          <!-- Verificando (volta do cartão) -->
          <template v-else-if="modal === 'polling'">
            <div class="pm-polling-wrap">
              <div class="gv-spinner pm-spinner-lg" />
              <div class="pm-polling-title">Verificando pagamento...</div>
              <p class="pm-footnote">Aguarde enquanto confirmamos com o Mercado Pago</p>
            </div>
          </template>

        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* ── Layout base ── */
.gv-center {
  flex: 1; display: flex; flex-direction: column; align-items: center;
  justify-content: center; padding: 80px 20px; gap: 12px;
}
.gv-spinner {
  width: 40px; height: 40px; border-radius: 50%;
  border: 3px solid rgba(11,19,43,0.12); border-top-color: var(--gold);
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.spin { animation: spin 0.8s linear infinite; }

.back-link {
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.12em; color: var(--muted); text-decoration: none;
  transition: color 0.15s;
  display: inline-flex; align-items: center; min-height: 44px;
}
.back-link:hover { color: var(--navy); }

.gv-choose {
  flex: 1; max-width: 560px; margin: 0 auto; padding: 48px 24px 80px; width: 100%;
}
.gv-header { margin-bottom: 32px; }
.gv-h1 {
  font-size: clamp(28px, 5vw, 56px); line-height: 1;
  text-transform: uppercase; letter-spacing: -0.02em; margin: 0 0 8px;
}
.gv-sub { color: var(--muted); margin: 0 0 32px; }

/* ── Avulso preview layout ── */
.ap-header {
  max-width: 560px; margin: 0 auto; padding: 48px 24px 0; width: 100%;
}
.ap-options {
  max-width: 560px; margin: 0 auto; padding: 8px 24px 80px; width: 100%;
}

/* ── Option cards ── */
.gv-option {
  display: flex; align-items: center; gap: 20px;
  background: white; border: 1.5px solid rgba(11,19,43,0.1);
  border-radius: 16px; padding: 24px 20px; margin-bottom: 12px;
  cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.gv-option:hover { border-color: var(--navy); box-shadow: 0 8px 32px rgba(14,42,94,0.12); transform: translateY(-2px); }
.gv-option-gold { border-color: var(--gold); background: rgba(232,184,66,0.04); }
.gv-option-gold:hover { border-color: var(--gold-deep); box-shadow: 0 8px 32px rgba(232,184,66,0.2); }
.gv-option-icon {
  width: 52px; height: 52px; border-radius: 12px; background: rgba(14,42,94,0.06);
  display: grid; place-items: center; flex-shrink: 0;
}
.gv-option-icon svg { width: 24px; height: 24px; color: var(--navy); }
.gv-icon-gold { background: rgba(232,184,66,0.15); }
.gv-icon-gold svg { color: var(--gold-deep); }
.gv-option-title { font-family: 'Archivo Black', sans-serif; font-size: 16px; letter-spacing: -0.01em; margin-bottom: 4px; }
.gv-option-sub { font-size: 13px; color: var(--muted); }
.gv-option-sub strong { color: var(--navy); }
.gv-option-arrow { margin-left: auto; flex-shrink: 0; width: 20px; height: 20px; color: var(--muted); }
.gv-share-btn {
  display: inline-flex; align-items: center; gap: 8px;
  background: none; border: 1.5px solid rgba(11,19,43,0.18);
  border-radius: 10px; padding: 9px 16px; cursor: pointer;
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--navy);
  transition: border-color 0.15s, background 0.15s; white-space: nowrap;
}
.gv-share-btn:hover:not(:disabled) { border-color: var(--navy); background: rgba(11,19,43,0.04); }
.gv-share-btn:disabled { opacity: 0.5; cursor: not-allowed; }

.gv-error {
  margin-top: 20px; background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #c62828;
}
.goa-divider {
  display: flex; align-items: center; gap: 12px; margin: 4px 0; color: var(--muted);
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.1em;
}
.goa-divider::before, .goa-divider::after {
  content: ''; flex: 1; height: 1px; background: rgba(11,19,43,0.1);
}

/* ── Clips list ── */
.clips-list { display: flex; flex-direction: column; gap: 8px; margin-bottom: 100px; }
.clip-row {
  display: flex; align-items: center; gap: 14px;
  background: white; border: 1.5px solid rgba(11,19,43,0.1);
  border-radius: 14px; padding: 10px 14px 10px 10px;
  cursor: pointer; transition: border-color 0.15s; user-select: none;
}
.clip-row:hover { border-color: rgba(11,19,43,0.25); }
.clip-row.selected { border-color: var(--gold); background: rgba(232,184,66,0.06); }
.clip-row-thumb {
  position: relative; width: 72px; height: 48px;
  border-radius: 8px; overflow: hidden; flex-shrink: 0; background: rgba(11,19,43,0.08);
}
.clip-row-thumb img { width: 100%; height: 100%; object-fit: cover; }
.clip-row-ph { width: 100%; height: 100%; background: rgba(11,19,43,0.1); }
.clip-row-dur {
  position: absolute; bottom: 3px; right: 4px;
  background: rgba(0,0,0,0.65); color: white;
  font-size: 9px; font-family: 'JetBrains Mono', monospace; padding: 1px 4px; border-radius: 3px;
}
.clip-row-info { flex: 1; min-width: 0; }
.clip-row-title { font-family: 'Archivo Black', sans-serif; font-size: 13px; letter-spacing: -0.01em; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.clip-row-meta { font-size: 11px; color: var(--muted); font-family: 'JetBrains Mono', monospace; margin-top: 2px; }
.clip-row-check {
  position: relative; width: 24px; height: 24px; border-radius: 50%; flex-shrink: 0;
  border: 2px solid rgba(11,19,43,0.2); display: grid; place-items: center;
  transition: border-color 0.15s, background 0.15s;
}
/* Área de toque invisível 44x44 ao redor do check */
.clip-row-check::before {
  content: ''; position: absolute; inset: -10px; border-radius: 50%;
}
.clip-row-check.active { border-color: var(--gold); background: var(--gold); }
.clip-row-check svg { width: 13px; height: 13px; stroke: var(--navy); }

/* ── Cart bar ── */
.cart-bar {
  position: fixed; bottom: 0; left: 0; right: 0;
  background: var(--navy); color: white; padding: 14px 20px;
  display: flex; align-items: center; justify-content: space-between; gap: 12px;
  box-shadow: 0 -4px 24px rgba(0,0,0,0.2); z-index: 100;
}
.cart-bar-info { font-size: 14px; opacity: 0.85; }
.cart-bar-info strong { color: var(--gold); }
.cart-bar-price { color: rgba(255,255,255,0.6); font-size: 13px; }
.cart-bar-actions { display: flex; gap: 10px; align-items: center; }
.cart-select-all {
  background: none; border: 1px solid rgba(255,255,255,0.25); color: white;
  border-radius: 8px; padding: 8px 14px; font-size: 12px; cursor: pointer;
  font-family: 'JetBrains Mono', monospace; letter-spacing: 0.05em; transition: border-color 0.15s;
}
.cart-select-all:hover { border-color: rgba(255,255,255,0.6); }
.cart-pay-btn {
  background: var(--gold); color: var(--navy); border: none; border-radius: 10px;
  padding: 10px 20px; font-family: 'Archivo Black', sans-serif; font-size: 13px;
  cursor: pointer; transition: opacity 0.15s;
}
.cart-pay-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.cart-pay-btn:not(:disabled):hover { opacity: 0.85; }

/* ── Payment modal ── */
.pm-backdrop {
  position: fixed; inset: 0; z-index: 200;
  background: rgba(11,19,43,0.6); backdrop-filter: blur(4px);
  display: flex; align-items: flex-end; justify-content: center;
  padding: 0;
}
@media (min-width: 560px) {
  .pm-backdrop { align-items: center; padding: 24px; }
}
.pm-card {
  background: white; border-radius: 24px 24px 0 0; padding: 28px 24px 40px;
  padding-bottom: max(40px, env(safe-area-inset-bottom));
  width: 100%; max-width: 480px; position: relative;
  max-height: 90vh; overflow-y: auto;
}
@media (min-width: 560px) {
  .pm-card { border-radius: 24px; padding: 40px 32px; }
}
.pm-close-btn {
  position: absolute; top: 16px; right: 16px;
  background: rgba(11,19,43,0.07); border: none; border-radius: 50%;
  width: 44px; height: 44px; display: grid; place-items: center;
  cursor: pointer; transition: background 0.15s;
}
.pm-close-btn:hover { background: rgba(11,19,43,0.13); }
.pm-close-btn svg { width: 16px; height: 16px; color: var(--navy); }

.pm-eyebrow {
  font-family: 'JetBrains Mono', monospace; font-size: 10px;
  letter-spacing: 0.15em; text-transform: uppercase; color: var(--muted);
  margin-bottom: 8px;
}
.pm-big-amount {
  font-family: 'Archivo Black', sans-serif; font-size: 40px;
  letter-spacing: -0.02em; color: var(--navy); line-height: 1; margin-bottom: 6px;
}
.pm-desc { font-size: 13px; color: var(--muted); margin-bottom: 20px; }
.pm-email-field { margin-bottom: 20px; }
.pm-email-label {
  display: block; font-size: 11px; font-family: 'JetBrains Mono', monospace;
  letter-spacing: .06em; text-transform: uppercase; color: var(--muted); margin-bottom: 6px;
}
.pm-email-input {
  width: 100%; box-sizing: border-box;
  border: 1.5px solid rgba(11,19,43,0.2); border-radius: 10px;
  padding: 12px 14px; font-size: 15px; color: var(--ink); background: #fff;
  outline: none; transition: border-color 0.15s;
  font-family: inherit;
}
.pm-email-input:focus { border-color: var(--navy); }
.pm-email-input:disabled { opacity: 0.5; }
.pm-error {
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.25);
  border-radius: 10px; padding: 12px 16px; font-size: 13px; color: #c62828;
  margin-bottom: 20px;
}

/* Methods */
.pm-methods { display: flex; flex-direction: column; gap: 10px; }
.pm-method {
  display: flex; align-items: center; gap: 16px;
  border: 1.5px solid rgba(11,19,43,0.12); border-radius: 16px; padding: 18px 16px;
  background: white; cursor: pointer; text-align: left; width: 100%;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.pm-method:hover:not(:disabled) { border-color: var(--navy); box-shadow: 0 4px 16px rgba(14,42,94,0.1); transform: translateY(-1px); }
.pm-method:disabled { opacity: 0.5; cursor: not-allowed; }
.pm-method-pix { border-color: #00b7a8; background: rgba(0,183,168,0.04); }
.pm-method-pix:hover:not(:disabled) { border-color: #009d90; box-shadow: 0 4px 16px rgba(0,183,168,0.2); }
.pm-method-icon {
  width: 48px; height: 48px; border-radius: 12px; background: rgba(14,42,94,0.07);
  display: grid; place-items: center; flex-shrink: 0; color: var(--navy);
}
.pm-icon-pix { background: rgba(0,183,168,0.15); color: #00b7a8; }
.pm-method-text { flex: 1; }
.pm-method-name { font-family: 'Archivo Black', sans-serif; font-size: 15px; letter-spacing: -0.01em; margin-bottom: 3px; color: var(--navy); }
.pm-method-sub { font-size: 12px; color: var(--muted); }
.pm-arrow { flex-shrink: 0; width: 18px; height: 18px; color: var(--muted); }

/* Pix QR */
.pm-qr-wrap {
  display: flex; align-items: center; justify-content: center;
  margin: 0 auto 20px;
  width: min(240px, 75vw); height: min(240px, 75vw);
}
.pm-qr-img {
  width: min(240px, 75vw); height: min(240px, 75vw);
  image-rendering: pixelated; border-radius: 8px;
}
.pm-qr-placeholder {
  width: min(240px, 75vw); height: min(240px, 75vw);
  background: rgba(11,19,43,0.05); border-radius: 8px;
  display: grid; place-items: center;
}
.pm-copy-row { display: flex; gap: 8px; margin-bottom: 16px; }
.pm-code-input {
  flex: 1; background: rgba(11,19,43,0.05); border: 1px solid rgba(11,19,43,0.12);
  border-radius: 10px; padding: 10px 14px; font-size: 11px;
  font-family: 'JetBrains Mono', monospace; color: var(--navy);
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  min-width: 0;
}
.pm-copy-btn {
  background: var(--navy); color: white; border: none; border-radius: 10px;
  padding: 10px 18px; font-family: 'Archivo Black', sans-serif; font-size: 12px;
  cursor: pointer; white-space: nowrap; transition: background 0.15s;
  flex-shrink: 0;
}
.pm-copy-btn.copied { background: #1e8a44; }

.pm-countdown {
  text-align: center; font-size: 13px; color: var(--muted); margin-bottom: 20px;
}
.pm-countdown strong { color: var(--navy); font-family: 'JetBrains Mono', monospace; }

.pm-waiting {
  display: flex; align-items: center; justify-content: center; gap: 12px;
  font-size: 13px; color: var(--muted); margin-bottom: 16px;
}
.pm-dot-spin { display: flex; gap: 5px; }
.pm-dot-spin span {
  width: 6px; height: 6px; border-radius: 50%; background: var(--gold);
  animation: dot-bounce 1.2s ease-in-out infinite;
}
.pm-dot-spin span:nth-child(2) { animation-delay: 0.2s; }
.pm-dot-spin span:nth-child(3) { animation-delay: 0.4s; }
@keyframes dot-bounce { 0%, 80%, 100% { transform: scale(0.7); opacity: 0.5; } 40% { transform: scale(1.1); opacity: 1; } }

.pm-footnote { text-align: center; font-size: 12px; color: var(--muted); margin: 0; }

/* Polling */
.pm-polling-wrap { display: flex; flex-direction: column; align-items: center; gap: 16px; padding: 16px 0; }
.pm-spinner-lg { width: 52px; height: 52px; border-width: 4px; }
.pm-polling-title { font-family: 'Archivo Black', sans-serif; font-size: 20px; letter-spacing: -0.01em; color: var(--navy); }

/* ── Mobile responsive ── */
@media (max-width: 640px) {
  .gv-choose {
    padding: 28px 16px 80px;
  }

  .ap-header {
    padding: 28px 16px 0;
  }

  .ap-options {
    padding: 8px 16px 80px;
  }

  .gv-h1 {
    font-size: clamp(26px, 8vw, 48px);
  }

  .gv-option {
    padding: 18px 14px;
    gap: 14px;
  }

  .gv-option-icon {
    width: 44px; height: 44px; border-radius: 10px;
  }

  .gv-option-title { font-size: 14px; }
  .gv-option-sub { font-size: 12px; }

  /* Cart bar safe-area para iPhone */
  .cart-bar {
    padding: 14px 16px;
    padding-bottom: max(14px, env(safe-area-inset-bottom));
  }
}

/* ── Preview grid (abaixo das opções) ── */
.ap-preview-wrap {
  max-width: 560px; margin: 0 auto; padding: 0 24px 56px; width: 100%;
}
.ap-teaser-label {
  display: inline-flex; align-items: center; gap: 6px;
  font-family: 'JetBrains Mono', monospace; font-size: 10px;
  letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted);
  margin-bottom: 12px;
}
.ap-preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
  gap: 10px;
}
.ap-prev-card { display: flex; flex-direction: column; gap: 6px; }
.ap-prev-media {
  position: relative; border-radius: 10px; overflow: hidden;
  aspect-ratio: 16/10; background: rgba(11,19,43,0.08);
}
.ap-prev-gif {
  width: 100%; height: 100%; object-fit: cover; display: block;
}
.ap-prev-ph { width: 100%; height: 100%; background: rgba(11,19,43,0.1); }
.ap-prev-lock-badge {
  position: absolute; top: 6px; right: 6px;
  background: rgba(0,0,0,0.55); border-radius: 6px;
  width: 26px; height: 26px; display: grid; place-items: center;
}
.ap-prev-lock-badge svg { width: 13px; height: 13px; stroke: white; }
.ap-prev-label {
  font-family: 'JetBrains Mono', monospace; font-size: 10px;
  letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted);
}
@media (max-width: 640px) {
  .ap-preview-wrap { padding: 0 16px 48px; }
  .ap-preview-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 8px; }
}
</style>
