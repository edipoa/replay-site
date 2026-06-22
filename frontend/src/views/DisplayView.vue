<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import QRCode from 'qrcode'
import { fetchCurrentGame } from '../api.js'

const props = defineProps({
  camera: { type: String, default: 'cam1' },
})

const camera   = new URLSearchParams(window.location.search).get('camera') ?? props.camera
const game     = ref(null)
const nextSlot = ref(null)
const qrDataUrl = ref('')
const now      = ref(new Date())

let pollTimer = null
let clockTimer = null

const WEEKDAYS = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom']

async function poll() {
  const { ok, data } = await fetchCurrentGame(camera)
  if (ok && data.qr_token) {
    game.value     = data
    nextSlot.value = null
    const url = `${window.location.origin}/jogo/${data.qr_token}`
    qrDataUrl.value = await QRCode.toDataURL(url, {
      width: 480,
      margin: 2,
      color: { dark: '#0E2A5E', light: '#F7F4ED' },
    })
  } else {
    game.value      = null
    nextSlot.value  = data.next_slot ?? null
    qrDataUrl.value = ''
  }
}

const timeStr = computed(() => now.value.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }))
const dateStr = computed(() => now.value.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' }))

function formatNextSlot(s) {
  if (!s) return ''
  const h = String(s.start_hour).padStart(2, '0')
  const m = String(s.start_minute ?? 0).padStart(2, '0')
  return `${WEEKDAYS[s.weekday]} às ${h}:${m}`
}

onMounted(async () => {
  await poll()
  pollTimer  = setInterval(poll, 30_000)
  clockTimer = setInterval(() => { now.value = new Date() }, 1000)
})

onUnmounted(() => {
  clearInterval(pollTimer)
  clearInterval(clockTimer)
})
</script>

<template>
  <div class="dp-page">
    <!-- Header -->
    <div class="dp-header">
      <span class="dp-brand display">RE<span class="gold">PLAY</span></span>
      <span class="dp-clock mono">{{ timeStr }}</span>
    </div>

    <!-- QR ativo -->
    <div v-if="game && qrDataUrl" class="dp-main">
      <div class="dp-qr-wrap">
        <img :src="qrDataUrl" class="dp-qr" alt="QR Code" />
      </div>
      <div class="dp-info">
        <div class="dp-label">ESCANEIE PARA VER OS REPLAYS</div>
        <div class="dp-slot display">{{ String(game.slot_hour).padStart(2,'0') }}:{{ String(game.slot_minute ?? 0).padStart(2,'0') }}</div>
        <div class="dp-clips mono">{{ game.clip_count ?? 0 }} clipes disponíveis</div>
        <div class="dp-hint">Acesso via login do time<br />ou pagamento por jogo</div>
      </div>
    </div>

    <!-- Sem jogo ativo -->
    <div v-else class="dp-idle">
      <div class="dp-idle-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.87V15.13a1 1 0 0 1-1.447.9L15 14M3 8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/>
        </svg>
      </div>
      <div class="dp-idle-title display">Sem jogo ativo</div>
      <div v-if="nextSlot" class="dp-idle-next mono">
        Próximo: {{ formatNextSlot(nextSlot) }}
      </div>
    </div>

    <!-- Footer -->
    <div class="dp-footer mono">{{ dateStr }}</div>
  </div>
</template>

<style scoped>
.dp-page {
  min-height: 100vh;
  background: var(--navy-deep);
  color: var(--paper);
  display: flex;
  flex-direction: column;
  padding: 32px;
  gap: 0;
}
.dp-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 48px;
}
.dp-brand {
  font-size: 28px;
  letter-spacing: -0.02em;
  text-transform: uppercase;
}
.dp-clock {
  font-size: 28px;
  letter-spacing: 0.08em;
  color: rgba(247,244,237,0.6);
}
.dp-main {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 80px;
}
@media (max-width: 900px) {
  .dp-main { flex-direction: column; gap: 40px; }
}
.dp-qr-wrap {
  background: var(--paper);
  border-radius: 24px;
  padding: 20px;
  box-shadow: 0 32px 80px rgba(0,0,0,0.4);
}
.dp-qr { display: block; width: 320px; height: 320px; }

.dp-info { display: flex; flex-direction: column; gap: 16px; max-width: 360px; }
.dp-label {
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px;
  letter-spacing: 0.24em;
  color: var(--gold);
  text-transform: uppercase;
}
.dp-slot {
  font-size: 96px;
  line-height: 0.9;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  color: var(--paper);
}
.dp-clips {
  font-size: 18px;
  letter-spacing: 0.06em;
  color: rgba(247,244,237,0.6);
}
.dp-hint {
  margin-top: 8px;
  font-size: 15px;
  line-height: 1.6;
  color: rgba(247,244,237,0.5);
  border-left: 3px solid var(--gold);
  padding-left: 16px;
}

.dp-idle {
  flex: 1; display: flex; flex-direction: column;
  align-items: center; justify-content: center; gap: 20px;
}
.dp-idle-icon svg {
  width: 80px; height: 80px;
  color: rgba(247,244,237,0.2);
}
.dp-idle-title {
  font-size: 48px;
  letter-spacing: -0.02em;
  text-transform: uppercase;
  color: rgba(247,244,237,0.4);
}
.dp-idle-next {
  font-size: 20px;
  letter-spacing: 0.08em;
  color: var(--gold);
}

.dp-footer {
  text-align: center;
  font-size: 13px;
  letter-spacing: 0.12em;
  color: rgba(247,244,237,0.3);
  text-transform: capitalize;
  margin-top: 32px;
}
</style>
