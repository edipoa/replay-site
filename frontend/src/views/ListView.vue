<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import TopBar from '../components/TopBar.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchPublicGames } from '../api.js'

const router  = useRouter()
const games   = ref([])
const loading = ref(true)
const error   = ref(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    games.value = await fetchPublicGames()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const groupedGames = computed(() => {
  const groups = {}
  for (const g of games.value) {
    if (!groups[g.slot_date]) groups[g.slot_date] = []
    groups[g.slot_date].push(g)
  }
  return Object.entries(groups)
    .sort(([a], [b]) => b.localeCompare(a))
    .map(([date, items]) => ({ date, items: items.sort((a, b) => b.slot_hour - a.slot_hour || (b.slot_minute ?? 0) - (a.slot_minute ?? 0)) }))
})

function formatTime(g) {
  return `${String(g.slot_hour).padStart(2, '0')}:${String(g.slot_minute ?? 0).padStart(2, '0')}`
}

function formatWeekday(dateStr) {
  return new Date(dateStr + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'long' })
}

function formatDateShort(dateStr) {
  return new Date(dateStr + 'T12:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })
}

function formatWeekdayShort(dateStr) {
  return new Date(dateStr + 'T12:00:00').toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '').toUpperCase()
}

function dateLabel(dateStr) {
  const today = localDateStr()
  const d = new Date(); d.setDate(d.getDate() - 1)
  const yesterday = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
  if (dateStr === today)     return 'HOJE'
  if (dateStr === yesterday) return 'ONTEM'
  return formatWeekdayShort(dateStr)
}

function localDateStr() {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`
}

function isToday(dateStr) {
  return dateStr === localDateStr()
}

function expiryMs(g) {
  const gameStart = new Date(`${g.slot_date}T${String(g.slot_hour).padStart(2,'0')}:${String(g.slot_minute ?? 0).padStart(2,'0')}:00`)
  return new Date(gameStart.getTime() + 24 * 3600 * 1000) - Date.now()
}

function expiryLabel(g) {
  const ms = expiryMs(g)
  if (ms <= 0) return null
  const h = Math.floor(ms / 3600000)
  const m = Math.floor((ms % 3600000) / 60000)
  if (h < 1)  return `${m}min`
  if (h < 4)  return `${h}h ${m}min`
  return `${h}h`
}

function expiryStatus(g) {
  const ms = expiryMs(g)
  if (ms <= 0)              return 'expired'
  if (ms < 4 * 3600000)    return 'urgent'
  if (ms < 8 * 3600000)    return 'warn'
  return 'fresh'
}

function goGame(qrToken) {
  router.push(`/jogo/${qrToken}`)
}

onMounted(load)
</script>

<template>
  <div class="page-layout">
    <TopBar />

    <!-- Hero -->
    <section class="hero">
      <div class="hero-inner">
        <div>
          <div class="hero-eyebrow"><span class="bar" />Todos os jogos</div>
          <h1>
            OS JOGOS<br />
            <span class="gold">DO CAMPO.</span><br />
            <span class="outline">NA SUA MÃO.</span>
          </h1>
        </div>
        <div class="hero-side">
          <p class="hero-tag">
            Clique em qualquer jogo para acessar os clipes.
            <strong style="color:var(--gold)">Clipes ficam disponíveis por 24 horas</strong>
            após a partida — baixe ou compartilhe antes que expire.
          </p>
          <div class="hero-warn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
            <div><strong>Janela de 24h</strong> · Após esse prazo os vídeos são removidos automaticamente.</div>
          </div>
          <div class="hero-subscribe">
            <p class="hero-subscribe-label">Joga aqui? Assine e tenha acesso a todos os replays do seu time.</p>
            <RouterLink to="/cadastro" class="btn gold-btn">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
              Criar conta e assinar
            </RouterLink>
            <RouterLink to="/login" class="hero-login-link">Já tenho conta →</RouterLink>
          </div>
        </div>
      </div>
    </section>

    <!-- Lista -->
    <main class="lv-wrap">

      <!-- Skeleton -->
      <template v-if="loading">
        <div class="lv-group-header lv-skeleton-header" />
        <div class="lv-list">
          <div v-for="i in 3" :key="i" class="lv-card lv-card--skeleton" />
        </div>
      </template>

      <!-- Erro -->
      <div v-else-if="error" class="empty">
        <div class="display">Erro ao carregar</div>
        <div>{{ error }}</div>
      </div>

      <!-- Vazio -->
      <div v-else-if="groupedGames.length === 0" class="lv-empty">
        <div class="lv-empty-icon">
          <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
            <rect x="6" y="10" width="36" height="30" rx="4"/>
            <path d="M6 18h36"/>
            <path d="M16 6v8M32 6v8"/>
            <path d="M15 28h6M27 28h6M15 34h6"/>
          </svg>
        </div>
        <p class="lv-empty-title">Nenhum jogo recente</p>
        <p class="lv-empty-sub">Os jogos aparecem aqui assim que o primeiro clipe é gravado.</p>
      </div>

      <!-- Grupos por data -->
      <template v-else>
        <div v-for="group in groupedGames" :key="group.date" class="lv-group">

          <!-- Header de data -->
          <div class="lv-group-header">
            <span class="lv-group-label" :class="{ 'lv-group-label--today': isToday(group.date) }">
              {{ dateLabel(group.date) }}
            </span>
            <span class="lv-group-line" />
            <span class="lv-group-date">
              {{ formatWeekdayShort(group.date) }} · {{ formatDateShort(group.date) }}
            </span>
          </div>

          <!-- Cards do grupo -->
          <div class="lv-list">
            <button
              v-for="g in group.items"
              :key="g.qr_token"
              class="lv-card"
              :class="`lv-card--${expiryStatus(g)}`"
              @click="goGame(g.qr_token)"
            >
              <!-- Bloco de horário -->
              <div class="lv-time-block">
                <span class="lv-time">{{ formatTime(g) }}</span>
              </div>

              <!-- Info principal -->
              <div class="lv-info">
                <div class="lv-weekday">
                  <span v-if="isToday(group.date)" class="lv-today-tag">HOJE</span>
                  {{ formatWeekday(group.date) }}
                  <span v-if="g.label" class="lv-label-tag">{{ g.label }}</span>
                </div>
                <div class="lv-meta-row">
                  <!-- Clip count -->
                  <span class="lv-clips-badge">
                    <svg viewBox="0 0 16 16" fill="currentColor">
                      <path d="M2 3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V3ZM2 8a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V8ZM10 8a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5a1 1 0 0 1-1 1h-2a1 1 0 0 1-1-1V8Z"/>
                    </svg>
                    {{ g.clip_count }} clipe{{ g.clip_count !== 1 ? 's' : '' }}
                  </span>

                  <!-- Expiry -->
                  <span
                    v-if="expiryStatus(g) === 'urgent'"
                    class="lv-expiry lv-expiry--urgent"
                  >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                      <circle cx="8" cy="8" r="6"/><path d="M8 5v3.5l2 1"/>
                    </svg>
                    Expira em {{ expiryLabel(g) }}
                  </span>
                  <span
                    v-else-if="expiryStatus(g) === 'warn'"
                    class="lv-expiry lv-expiry--warn"
                  >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                      <circle cx="8" cy="8" r="6"/><path d="M8 5v3.5l2 1"/>
                    </svg>
                    Expira em {{ expiryLabel(g) }}
                  </span>
                  <span
                    v-else-if="expiryStatus(g) === 'fresh'"
                    class="lv-expiry lv-expiry--fresh"
                  >
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                      <circle cx="8" cy="8" r="6"/><path d="M8 5v3.5l2 1"/>
                    </svg>
                    {{ expiryLabel(g) }} restantes
                  </span>
                </div>
              </div>

              <!-- Seta -->
              <svg class="lv-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path d="M9 18l6-6-6-6"/>
              </svg>
            </button>
          </div>
        </div>
      </template>

    </main>

    <AppFooter />
  </div>
</template>

<style scoped>
.lv-wrap {
  max-width: 680px;
  margin: 0 auto;
  padding: 48px 24px 96px;
  display: flex;
  flex-direction: column;
  gap: 40px;
}

/* ── Group ─────────────────────────────────────────── */
.lv-group {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.lv-group-header {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 4px;
}
.lv-group-label {
  font-family: 'Archivo Black', sans-serif;
  font-size: 13px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: var(--muted);
  white-space: nowrap;
}
.lv-group-label--today {
  color: var(--gold-deep);
}
.lv-group-line {
  flex: 1;
  height: 1px;
  background: rgba(11,19,43,0.1);
}
.lv-group-date {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.14em;
  color: var(--muted);
  white-space: nowrap;
}

/* ── Card ─────────────────────────────────────────── */
.lv-list {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.lv-card {
  display: flex;
  align-items: center;
  gap: 0;
  width: 100%;
  text-align: left;
  background: white;
  border: 1.5px solid rgba(11,19,43,0.09);
  border-left-width: 3px;
  border-left-color: rgba(11,19,43,0.12);
  border-radius: 14px;
  overflow: hidden;
  cursor: pointer;
  transition: border-color 0.15s, box-shadow 0.15s, transform 0.15s;
  appearance: none;
  color: var(--ink);
  padding: 0;
}

.lv-card:hover {
  border-color: var(--navy);
  border-left-color: var(--navy);
  box-shadow: 0 6px 28px rgba(14,42,94,0.1);
  transform: translateY(-2px);
}

/* Status variants — left border color */
.lv-card--fresh {
  border-left-color: #22c55e;
}
.lv-card--fresh:hover {
  border-left-color: #16a34a;
}
.lv-card--warn {
  border-left-color: var(--gold);
}
.lv-card--warn:hover {
  border-left-color: var(--gold-deep);
  border-color: rgba(200,150,30,0.4);
}
.lv-card--urgent {
  border-left-color: #ef4444;
}
.lv-card--urgent:hover {
  border-left-color: #dc2626;
  border-color: rgba(239,68,68,0.3);
}
.lv-card--expired {
  opacity: 0.55;
  pointer-events: none;
}

/* ── Time block ─────────────────────────────────────── */
.lv-time-block {
  flex-shrink: 0;
  padding: 20px 20px 20px 22px;
  background: var(--navy);
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 96px;
  align-self: stretch;
}
.lv-time {
  font-family: 'JetBrains Mono', monospace;
  font-size: 22px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: var(--paper);
  line-height: 1;
}
.lv-card--warn .lv-time-block {
  background: rgba(200,150,30,0.12);
}
.lv-card--warn .lv-time {
  color: var(--navy);
}
.lv-card--urgent .lv-time-block {
  background: rgba(239,68,68,0.08);
}
.lv-card--urgent .lv-time {
  color: #c62828;
}

/* ── Info ─────────────────────────────────────────── */
.lv-info {
  flex: 1;
  min-width: 0;
  padding: 16px 14px 16px 20px;
  display: flex;
  flex-direction: column;
  gap: 7px;
}
.lv-weekday {
  font-family: 'Archivo Black', sans-serif;
  font-size: 14px;
  letter-spacing: -0.005em;
  text-transform: capitalize;
  color: var(--ink);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}
.lv-today-tag {
  font-family: 'JetBrains Mono', monospace;
  font-size: 10px;
  letter-spacing: 0.12em;
  background: var(--gold);
  color: var(--navy-deep);
  padding: 2px 7px;
  border-radius: 4px;
  font-weight: 700;
}
.lv-label-tag {
  font-family: 'Archivo', sans-serif;
  font-size: 12px;
  font-weight: 400;
  color: var(--muted);
  text-transform: none;
  letter-spacing: 0;
}

.lv-meta-row {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}

.lv-clips-badge {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.08em;
  color: var(--muted);
}
.lv-clips-badge svg {
  width: 12px;
  height: 12px;
  opacity: 0.6;
}

.lv-expiry {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.08em;
  padding: 3px 8px 3px 6px;
  border-radius: 99px;
}
.lv-expiry svg {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}
.lv-expiry--fresh {
  background: rgba(34,197,94,0.1);
  color: #15803d;
}
.lv-expiry--warn {
  background: rgba(232,184,66,0.18);
  color: #8a6512;
}
.lv-expiry--urgent {
  background: rgba(239,68,68,0.1);
  color: #c62828;
}

/* ── Arrow ─────────────────────────────────────────── */
.lv-arrow {
  width: 18px;
  height: 18px;
  color: var(--muted);
  flex-shrink: 0;
  margin-right: 18px;
  transition: transform 0.15s, color 0.15s;
}
.lv-card:hover .lv-arrow {
  color: var(--navy);
  transform: translateX(2px);
}

/* ── Skeleton ─────────────────────────────────────── */
.lv-skeleton-header {
  height: 20px;
  width: 200px;
  border-radius: 6px;
  background: linear-gradient(90deg, rgba(11,19,43,0.05) 25%, rgba(11,19,43,0.09) 50%, rgba(11,19,43,0.05) 75%);
  background-size: 400% 100%;
  animation: shimmer 1.4s infinite;
}
.lv-card--skeleton {
  height: 78px;
  background: linear-gradient(90deg, rgba(11,19,43,0.04) 25%, rgba(11,19,43,0.08) 50%, rgba(11,19,43,0.04) 75%);
  background-size: 400% 100%;
  animation: shimmer 1.4s infinite;
  pointer-events: none;
  border: 1.5px solid transparent;
  border-left: 3px solid transparent;
}
@keyframes shimmer {
  0%   { background-position: 100% 50%; }
  100% { background-position: 0% 50%; }
}

/* ── Empty ─────────────────────────────────────────── */
.lv-empty {
  text-align: center;
  padding: 80px 20px;
}
.lv-empty-icon {
  width: 64px;
  height: 64px;
  margin: 0 auto 20px;
  color: rgba(11,19,43,0.18);
}
.lv-empty-icon svg {
  width: 100%;
  height: 100%;
}
.lv-empty-title {
  font-family: 'Archivo Black', sans-serif;
  font-size: 22px;
  color: var(--ink);
  margin: 0 0 8px;
  letter-spacing: -0.01em;
}
.lv-empty-sub {
  font-size: 14px;
  color: var(--muted);
  margin: 0;
  line-height: 1.5;
}

/* ── Mobile ─────────────────────────────────────────── */
@media (max-width: 640px) {
  .lv-wrap {
    padding: 32px 16px 80px;
    gap: 32px;
  }

  .lv-time-block {
    min-width: 80px;
    padding: 18px 14px 18px 16px;
  }
  .lv-time {
    font-size: 18px;
  }

  .lv-info {
    padding: 14px 10px 14px 16px;
    gap: 6px;
  }
  .lv-weekday {
    font-size: 13px;
  }

  .lv-arrow {
    margin-right: 14px;
  }
}
</style>
