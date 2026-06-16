<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import TopBar    from '../components/TopBar.vue'
import VideoCard from '../components/VideoCard.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchGame, fetchGameVideos, payGame } from '../api.js'

const route  = useRoute()
const router = useRouter()

const qrToken    = route.params.qrToken
const STORAGE_KEY = `game_token_${qrToken}`

const game    = ref(null)
const videos  = ref([])
const loading = ref(true)
const paying  = ref(false)
const error   = ref(null)
const view    = ref('choose') // 'choose' | 'group-login' | 'videos'

async function load() {
  loading.value = true
  error.value   = null
  try {
    game.value = await fetchGame(qrToken)

    // Verifica se já tem token de acesso salvo
    const savedToken = localStorage.getItem(STORAGE_KEY)
    if (savedToken) {
      await loadVideos(savedToken)
    }
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function loadVideos(token) {
  try {
    videos.value = await fetchGameVideos(qrToken, token)
    view.value   = 'videos'
  } catch (e) {
    if (e.message === 'UNAUTHORIZED') {
      localStorage.removeItem(STORAGE_KEY)
      view.value = 'choose'
    } else {
      error.value = e.message
    }
  }
}

async function payFull() {
  paying.value = true
  error.value  = null
  try {
    const data = await payGame(qrToken, 'full')
    localStorage.setItem(STORAGE_KEY, data.token)
    await loadVideos(data.token)
  } catch (e) {
    error.value = e.message
  } finally {
    paying.value = false
  }
}

function goGroupLogin() {
  // Salva o destino para redirecionar após login
  localStorage.setItem('after_login', `/jogo/${qrToken}`)
  router.push('/login')
}

const WEEKDAYS = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom']
function formatSlot(g) {
  if (!g) return ''
  const d = new Date(g.slot_date + 'T00:00:00')
  return `${d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} às ${String(g.slot_hour).padStart(2,'0')}h`
}

onMounted(load)
</script>

<template>
  <div class="page-layout">
    <TopBar />

    <div v-if="loading" class="gv-center">
      <div class="gv-spinner" />
    </div>

    <div v-else-if="error && !game" class="gv-center">
      <div class="display" style="color:var(--ink)">Jogo não encontrado</div>
      <p style="color:var(--muted)">{{ error }}</p>
    </div>

    <template v-else-if="game">

      <!-- Tela de escolha -->
      <main v-if="view === 'choose'" class="gv-choose">
        <div class="gv-header">
          <div class="hero-eyebrow"><span class="bar" />Replays do jogo</div>
          <h1 class="display" style="font-size:clamp(32px,5vw,64px);line-height:1;text-transform:uppercase;letter-spacing:-0.02em">
            Jogo de<br /><span class="gold">{{ formatSlot(game) }}</span>
          </h1>
          <p style="color:var(--muted);margin-top:12px">
            {{ game.clip_count }} clipe{{ game.clip_count !== 1 ? 's' : '' }} disponível{{ game.clip_count !== 1 ? 'is' : '' }}
          </p>
        </div>

        <div class="gv-options">
          <div class="gv-option" @click="goGroupLogin">
            <div class="gv-option-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Sou do time fixo</div>
              <div class="gv-option-sub">Tenho login e senha do meu grupo</div>
            </div>
            <svg class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>

          <div class="gv-option gv-option-gold" @click="payFull">
            <div class="gv-option-icon gv-icon-gold">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <rect x="2" y="5" width="20" height="14" rx="2"/>
                <path d="M2 10h20"/>
              </svg>
            </div>
            <div>
              <div class="gv-option-title">Pagar por este jogo</div>
              <div class="gv-option-sub">Acesso a todos os {{ game.clip_count }} clipes por 24h</div>
            </div>
            <div v-if="paying" class="gv-option-arrow">
              <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
            </div>
            <svg v-else class="gv-option-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="M9 18l6-6-6-6"/>
            </svg>
          </div>
        </div>

        <div v-if="error" class="gv-error">{{ error }}</div>
      </main>

      <!-- Clipes do jogo -->
      <main v-else-if="view === 'videos'" class="main-wrap">
        <div class="gv-back">
          <div class="hero-eyebrow" style="margin-bottom:24px">
            <span class="bar" />{{ formatSlot(game) }} · {{ videos.length }} clipes
          </div>
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
  </div>
</template>

<style scoped>
.gv-center {
  flex: 1; display: grid; place-items: center; padding: 80px 20px;
}
.gv-spinner {
  width: 40px; height: 40px; border-radius: 50%;
  border: 3px solid rgba(11,19,43,0.12);
  border-top-color: var(--gold);
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

.gv-choose {
  flex: 1; max-width: 560px; margin: 0 auto; padding: 56px 24px 80px;
  width: 100%;
}
.gv-header { margin-bottom: 40px; }

.gv-options { display: flex; flex-direction: column; gap: 16px; }

.gv-option {
  display: flex; align-items: center; gap: 20px;
  background: white; border: 1.5px solid rgba(11,19,43,0.1);
  border-radius: 16px; padding: 24px 20px;
  cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
}
.gv-option:hover {
  border-color: var(--navy);
  box-shadow: 0 8px 32px rgba(14,42,94,0.12);
  transform: translateY(-2px);
}
.gv-option-gold { border-color: var(--gold); background: rgba(232,184,66,0.04); }
.gv-option-gold:hover { border-color: var(--gold-deep); box-shadow: 0 8px 32px rgba(232,184,66,0.2); }

.gv-option-icon {
  width: 52px; height: 52px; border-radius: 12px;
  background: rgba(14,42,94,0.06);
  display: grid; place-items: center; flex-shrink: 0;
}
.gv-option-icon svg { width: 24px; height: 24px; color: var(--navy); }
.gv-icon-gold { background: rgba(232,184,66,0.15); }
.gv-icon-gold svg { color: var(--gold-deep); }

.gv-option-title {
  font-family: 'Archivo Black', sans-serif;
  font-size: 16px; letter-spacing: -0.01em;
  margin-bottom: 4px;
}
.gv-option-sub { font-size: 13px; color: var(--muted); }

.gv-option-arrow { margin-left: auto; flex-shrink: 0; }
.gv-option-arrow svg { width: 20px; height: 20px; color: var(--muted); }

.gv-error {
  margin-top: 20px;
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 10px; padding: 12px 16px;
  font-size: 13px; color: #c62828;
}
.gv-back { margin-bottom: 8px; }
.spin { width: 20px; height: 20px; animation: spin 0.8s linear infinite; }
</style>
