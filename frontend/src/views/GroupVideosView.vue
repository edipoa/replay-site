<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import TopBar    from '../components/TopBar.vue'
import VideoCard from '../components/VideoCard.vue'
import Pagination from '../components/Pagination.vue'
import AppFooter from '../components/AppFooter.vue'
import {
  fetchGroupVideos,
  fetchGroupSubscription,
  initiateGroupSubscription,
  getSubscriptionPaymentStatus,
} from '../api.js'
import { useAuth } from '../composables/useAuth.js'

const PAGE_SIZE = 12
const router    = useRouter()
const { token, groupName, logout } = useAuth()

const videos     = ref([])
const loading    = ref(false)
const error      = ref(null)
const page       = ref(1)
const userRole   = ref(null)   // 'captain' | 'player' | null (legado)
const displayName = ref('')

const isCaptain = computed(() =>
  userRole.value === null || userRole.value === 'captain'
)

// ── Assinatura ────────────────────────────────────────────────────────────────
const subscription  = ref(null)
const showRenewModal = ref(false)
const renewStep      = ref('choose') // 'choose' | 'pix' | 'card' | 'done'
const renewLoading   = ref(false)
const renewError     = ref(null)
const pixData        = ref(null)
let   pollTimer      = null

const subStatus = computed(() => subscription.value?.status ?? 'active')
const daysLeft  = computed(() => subscription.value?.days_left ?? 999)
const showBanner = computed(() =>
  subStatus.value === 'expired' || subStatus.value === 'expiring'
)

async function loadSubscription() {
  if (!token.value) return
  try {
    subscription.value = await fetchGroupSubscription(token.value)
  } catch {}
}

async function loadUserMeta() {
  const ut = localStorage.getItem('user_token')
  if (!ut) return
  try {
    const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')
    const res  = await fetch(`${BASE}/api/user/me`, { headers: { Authorization: `Bearer ${ut}` } })
    if (!res.ok) return
    const data = await res.json()
    userRole.value   = data.group?.role ?? null
    displayName.value = data.name ?? ''
  } catch {}
}

async function load() {
  const activeToken = token.value || localStorage.getItem('user_token') || ''
  if (!activeToken) { router.push('/login'); return }
  loading.value = true
  error.value   = null
  try {
    await loadUserMeta()
    const vids = await fetchGroupVideos(activeToken)
    videos.value = vids
    vids.forEach(v => localStorage.setItem(`video_token_${v.id}`, activeToken))
  } catch (e) {
    if (e.message === 'UNAUTHORIZED') { router.push('/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function handleLogout() {
  await logout()
  router.push('/login')
}

// ── Renovação ─────────────────────────────────────────────────────────────────
function openRenewModal() {
  renewStep.value  = 'choose'
  renewError.value = null
  pixData.value    = null
  showRenewModal.value = true
}

function closeRenewModal() {
  stopPolling()
  const wasDone = renewStep.value === 'done'
  showRenewModal.value = false
  if (wasDone) load()
}

async function choosePix() {
  renewLoading.value = true
  renewError.value   = null
  try {
    const res = await initiateGroupSubscription(token.value, 'pix')
    if (res.mock) {
      await loadSubscription()
      renewStep.value = 'done'
      return
    }
    pixData.value  = res
    renewStep.value = 'pix'
    startPolling(res.payment_id)
  } catch (e) {
    renewError.value = e.message
  } finally {
    renewLoading.value = false
  }
}

async function chooseCard() {
  renewLoading.value = true
  renewError.value   = null
  try {
    const res = await initiateGroupSubscription(token.value, 'credit_card')
    window.location.href = res.checkout_url
  } catch (e) {
    renewError.value   = e.message
    renewLoading.value = false
  }
}

function startPolling(paymentId) {
  stopPolling()
  pollTimer = setInterval(async () => {
    try {
      const { status } = await getSubscriptionPaymentStatus(paymentId)
      if (status === 'approved') {
        stopPolling()
        await loadSubscription()
        renewStep.value = 'done'
      } else if (status === 'rejected' || status === 'cancelled') {
        stopPolling()
        renewError.value = 'Pagamento recusado. Tente novamente.'
        renewStep.value  = 'choose'
      }
    } catch {}
  }, 4000)
}

function stopPolling() {
  if (pollTimer) { clearInterval(pollTimer); pollTimer = null }
}

async function copyPix() {
  try {
    await navigator.clipboard.writeText(pixData.value.qr_code)
  } catch {}
}

const totalPages = computed(() => Math.max(1, Math.ceil(videos.value.length / PAGE_SIZE)))
const pageVideos = computed(() => {
  const start = (page.value - 1) * PAGE_SIZE
  return videos.value.slice(start, start + PAGE_SIZE)
})

function goPage(p) { page.value = p; window.scrollTo({ top: 0, behavior: 'smooth' }) }

onMounted(async () => {
  const params     = new URLSearchParams(location.search)
  const subPayment = params.get('sub_payment')
  const pstatus    = params.get('pstatus')
  const renew      = params.get('renew')

  history.replaceState({}, '', location.pathname)

  if (renew === '1') {
    // Assinatura expirada → não tenta carregar vídeos (daria 401), só abre modal
    await loadSubscription()
    openRenewModal()
    return
  }

  await Promise.all([load(), loadSubscription()])

  if (subPayment && !pstatus) {
    // Retornou do checkout card com pagamento aprovado
    try {
      const { status } = await getSubscriptionPaymentStatus(subPayment)
      if (status === 'approved') await loadSubscription()
    } catch {}
  }
})
</script>

<template>
  <div class="page-layout">
    <TopBar />

    <!-- Banner de assinatura expirando/expirada -->
    <div v-if="showBanner && isCaptain" class="sub-banner" :class="subStatus">
      <span v-if="subStatus === 'expired'">
        ⚠️ Sua assinatura expirou. Renove para continuar assistindo.
      </span>
      <span v-else>
        ⚠️ Sua assinatura expira em {{ daysLeft }} dia{{ daysLeft === 1 ? '' : 's' }}.
      </span>
      <button class="btn renew-btn" @click="openRenewModal">Renovar agora</button>
    </div>

    <section class="hero">
      <div class="hero-inner">
        <div>
          <div class="hero-eyebrow"><span class="bar" />Meu time</div>
          <h1>
            SEUS<br />
            <span class="gold">LANCES.</span><br />
            <span class="outline">SEU TIME.</span>
          </h1>
        </div>
        <div class="hero-side">
          <p class="hero-tag">
            Bem-vindo, <strong>{{ displayName || groupName }}</strong>.<br />
            Estes são os replays do seu horário fixo.
          </p>
          <div style="display:flex;gap:0.75rem;flex-wrap:wrap;align-items:center">
            <button
              v-if="isCaptain && !showBanner"
              class="btn ghost-btn"
              style="width:fit-content"
              @click="openRenewModal"
            >
              Renovar assinatura
            </button>
            <button class="btn ghost-btn" style="width:fit-content" @click="handleLogout">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
              </svg>
              Sair
            </button>
          </div>
        </div>
      </div>
    </section>

    <main class="main-wrap">
      <div class="grid">
        <template v-if="loading">
          <div v-for="i in 8" :key="i" class="clip" style="pointer-events:none">
            <div class="thumb-wrap">
              <div class="thumb thumb-grass" style="opacity:.35" />
            </div>
          </div>
        </template>

        <div v-else-if="error" class="empty">
          <div class="display">Erro ao carregar</div>
          <div>{{ error }}</div>
        </div>

        <div v-else-if="pageVideos.length === 0" class="empty">
          <div class="display">Nenhum clipe ativo</div>
          <div>Os lances ficam disponíveis por 24h após a gravação.</div>
        </div>

        <VideoCard v-else v-for="v in pageVideos" :key="v.id" :video="v" />
      </div>

      <Pagination :page="page" :total-pages="totalPages" @change="goPage" />
    </main>

    <AppFooter />

    <!-- Modal de renovação -->
    <Teleport to="body">
      <div v-if="showRenewModal" class="modal-overlay" @click.self="closeRenewModal">
        <div class="modal-box">
          <button class="modal-close" @click="closeRenewModal">✕</button>

          <!-- Escolha do método -->
          <template v-if="renewStep === 'choose'">
            <h2 class="modal-title">Renovar Assinatura</h2>
            <p class="modal-sub">30 dias por <strong>R$ {{ subscription?.price?.toFixed(2).replace('.', ',') ?? '59,90' }}</strong></p>
            <p v-if="renewError" class="modal-error">{{ renewError }}</p>
            <div class="modal-actions">
              <button class="btn pay-btn" :disabled="renewLoading" @click="choosePix">
                <span v-if="renewLoading">Gerando Pix…</span>
                <span v-else>Pagar com Pix</span>
              </button>
              <button class="btn pay-btn secondary" :disabled="renewLoading" @click="chooseCard">
                <span v-if="renewLoading">Aguarde…</span>
                <span v-else>Cartão de crédito</span>
              </button>
            </div>
          </template>

          <!-- QR Code Pix -->
          <template v-else-if="renewStep === 'pix'">
            <h2 class="modal-title">Pague com Pix</h2>
            <p class="modal-sub">Aponte a câmera ou copie o código abaixo</p>
            <div class="pix-qr">
              <img :src="`data:image/png;base64,${pixData.qr_code_base64}`" alt="QR Pix" />
            </div>
            <button class="btn pay-btn" style="margin-top:1rem" @click="copyPix">
              Copiar código Pix
            </button>
            <p class="modal-hint">Aguardando confirmação do pagamento…</p>
          </template>

          <!-- Aprovado -->
          <template v-else-if="renewStep === 'done'">
            <div class="modal-done">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="48" height="48">
                <circle cx="12" cy="12" r="10"/>
                <path d="M8 12l3 3 5-5"/>
              </svg>
              <h2 class="modal-title">Assinatura renovada!</h2>
              <p class="modal-sub">Mais 30 dias de replay do seu horário.</p>
              <button class="btn pay-btn" @click="closeRenewModal">Fechar</button>
            </div>
          </template>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
/* ── Banner ─────────────────────────────────────────────────── */
.sub-banner {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 0.75rem 1.5rem;
  font-size: 0.9rem;
  font-weight: 500;
}
.sub-banner.expiring { background: #7c5c00; color: #ffd370; }
.sub-banner.expired  { background: #6b1a1a; color: #ffaaaa; }

.renew-btn {
  padding: 0.35rem 1rem;
  font-size: 0.85rem;
  background: white;
  color: #111;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 600;
}

/* ── Modal ──────────────────────────────────────────────────── */
.modal-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,.7);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}
.modal-box {
  position: relative;
  background: #1a1a1a;
  border: 1px solid #333;
  border-radius: 12px;
  padding: 2rem;
  width: min(420px, 92vw);
  text-align: center;
}
.modal-close {
  position: absolute;
  top: 0.75rem;
  right: 1rem;
  background: none;
  border: none;
  color: #888;
  font-size: 1.1rem;
  cursor: pointer;
}
.modal-close:hover { color: #fff; }
.modal-title { font-size: 1.3rem; font-weight: 700; margin: 0 0 0.3rem; color: #fff; }
.modal-sub   { color: #aaa; margin: 0 0 1.25rem; font-size: 0.95rem; }
.modal-hint  { color: #666; font-size: 0.8rem; margin-top: 0.75rem; }
.modal-error { color: #ff7b7b; font-size: 0.85rem; margin-bottom: 0.75rem; }
.modal-actions { display: flex; flex-direction: column; gap: 0.75rem; }

.pay-btn {
  width: 100%;
  padding: 0.85rem;
  border-radius: 8px;
  border: none;
  background: #e6c230;
  color: #000;
  font-weight: 700;
  font-size: 1rem;
  cursor: pointer;
}
.pay-btn:disabled { opacity: .55; cursor: default; }
.pay-btn.secondary { background: #2a2a2a; color: #ddd; border: 1px solid #444; }
.pay-btn.secondary:hover { background: #333; }

.pix-qr {
  display: flex;
  justify-content: center;
  margin: 0.5rem 0;
}
.pix-qr img { width: 200px; height: 200px; border-radius: 8px; background: #fff; padding: 6px; }

.modal-done {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.75rem;
}
.modal-done svg { color: #4caf50; }
</style>
