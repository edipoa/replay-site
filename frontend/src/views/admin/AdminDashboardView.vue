<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchAdminDashboard } from '../../api.js'

const router  = useRouter()
const loading = ref(false)
const error   = ref('')
const data    = ref(null)

const today = new Date()
const fromDate = ref(
  `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-01`
)
const toDate = ref(
  new Date(today.getFullYear(), today.getMonth() + 1, 0).toISOString().split('T')[0]
)

async function load() {
  loading.value = true
  error.value   = ''
  try {
    data.value = await fetchAdminDashboard(fromDate.value, toDate.value)
  } catch (e) {
    if (e.message === 'ADMIN_UNAUTHORIZED') { router.push('/admin/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function fmt(v) {
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v ?? 0)
}

function fmtDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR', {
    day: '2-digit', month: '2-digit', year: '2-digit',
  })
}

function fmtTime(h, m) {
  return `${String(h).padStart(2, '0')}:${String(m ?? 0).padStart(2, '0')}`
}

function totalApproved(d) {
  if (!d) return 0
  return (d.revenue?.subscriptions?.approved ?? 0) + (d.revenue?.pay_per_game?.approved ?? 0)
}

onMounted(load)
</script>

<template>
  <div>
    <!-- Header -->
    <div class="adm-page-header">
      <div>
        <h1 class="adm-title display">Dashboard</h1>
        <p class="adm-sub">Métricas e receita por período</p>
      </div>
    </div>

    <!-- Period bar -->
    <div class="period-bar">
      <div class="period-group">
        <label class="period-label mono">De</label>
        <input type="date" class="date-input" v-model="fromDate" />
      </div>
      <div class="period-group">
        <label class="period-label mono">Até</label>
        <input type="date" class="date-input" v-model="toDate" />
      </div>
      <button class="apply-btn" :disabled="loading" @click="load">
        <svg v-if="loading" class="spin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
        </svg>
        <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.62-9.2"/>
        </svg>
        Aplicar
      </button>
    </div>

    <div v-if="error" class="adm-error">{{ error }}</div>

    <template v-if="data || loading">

      <!-- Total aprovado banner -->
      <div class="total-banner" :class="{ shimmer: loading }">
        <div class="total-label mono">Total aprovado no período</div>
        <div class="total-value display">{{ fmt(totalApproved(data)) }}</div>
      </div>

      <!-- ── Receita ─────────────────────────────────────────────── -->
      <div class="section-header">
        <span class="section-bar"></span>
        <span class="section-title display">Receita</span>
      </div>

      <div class="revenue-grid">
        <div class="revenue-card" :class="{ shimmer: loading }">
          <div class="rev-title mono">Assinaturas</div>
          <div class="rev-rows">
            <div class="rev-row approved">
              <span class="rev-dot"></span>
              <span class="rev-status">Aprovado</span>
              <span class="rev-amount mono">{{ fmt(data?.revenue?.subscriptions?.approved) }}</span>
            </div>
            <div class="rev-row pending">
              <span class="rev-dot"></span>
              <span class="rev-status">Pendente</span>
              <span class="rev-amount mono">{{ fmt(data?.revenue?.subscriptions?.pending) }}</span>
            </div>
            <div class="rev-row failed">
              <span class="rev-dot"></span>
              <span class="rev-status">Falhou</span>
              <span class="rev-amount mono">{{ fmt(data?.revenue?.subscriptions?.failed) }}</span>
            </div>
          </div>
        </div>

        <div class="revenue-card" :class="{ shimmer: loading }">
          <div class="rev-title mono">Avulsas</div>
          <div class="rev-rows">
            <div class="rev-row approved">
              <span class="rev-dot"></span>
              <span class="rev-status">Aprovado</span>
              <span class="rev-amount mono">{{ fmt(data?.revenue?.pay_per_game?.approved) }}</span>
            </div>
            <div class="rev-row pending">
              <span class="rev-dot"></span>
              <span class="rev-status">Pendente</span>
              <span class="rev-amount mono">{{ fmt(data?.revenue?.pay_per_game?.pending) }}</span>
            </div>
            <div class="rev-row failed">
              <span class="rev-dot"></span>
              <span class="rev-status">Falhou</span>
              <span class="rev-amount mono">{{ fmt(data?.revenue?.pay_per_game?.failed) }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Operacional ─────────────────────────────────────────── -->
      <div class="section-header">
        <span class="section-bar"></span>
        <span class="section-title display">Operacional</span>
      </div>

      <div class="stat-grid">
        <div class="stat-card" :class="{ shimmer: loading }">
          <div class="stat-value display">{{ data?.totals?.clips ?? 0 }}</div>
          <div class="stat-label mono">Clipes gerados</div>
        </div>
        <div class="stat-card" :class="{ shimmer: loading }">
          <div class="stat-value display">{{ data?.totals?.games ?? 0 }}</div>
          <div class="stat-label mono">Jogos registrados</div>
        </div>
      </div>

      <!-- ── Por Grupo ───────────────────────────────────────────── -->
      <div class="section-header">
        <span class="section-bar"></span>
        <span class="section-title display">Por Grupo</span>
      </div>

      <div class="adm-table-wrap" :class="{ shimmer: loading }">
        <table class="adm-table" v-if="data?.by_group?.length">
          <thead>
            <tr>
              <th>Grupo</th>
              <th style="text-align:center">Jogos</th>
              <th style="text-align:center">Clipes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in data.by_group" :key="g.group_id">
              <td class="group-name">{{ g.group_name }}</td>
              <td style="text-align:center">
                <span class="count-num">{{ g.game_count }}</span>
              </td>
              <td style="text-align:center">
                <span class="count-num gold">{{ g.clip_count }}</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loading" class="adm-empty">Nenhum grupo no período.</div>
        <div v-else class="adm-empty" style="color:transparent">—</div>
      </div>

      <!-- ── Por Jogo ────────────────────────────────────────────── -->
      <div class="section-header">
        <span class="section-bar"></span>
        <span class="section-title display">Por Jogo</span>
      </div>

      <div class="adm-table-wrap" :class="{ shimmer: loading }">
        <table class="adm-table" v-if="data?.by_game?.length">
          <thead>
            <tr>
              <th>Data / Hora</th>
              <th>Câmera</th>
              <th style="text-align:center">Compras</th>
              <th style="text-align:right">Receita aprovada</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in data.by_game" :key="g.id">
              <td class="mono" style="font-size:13px">
                {{ fmtDate(g.slot_date) }} {{ fmtTime(g.slot_hour, g.slot_minute) }}
              </td>
              <td>
                <span class="mono" style="font-size:12px;color:var(--muted)">{{ g.camera_id }}</span>
              </td>
              <td style="text-align:center">
                <span class="count-num" :class="{ 'zero': g.purchase_count === 0 }">
                  {{ g.purchase_count }}
                </span>
              </td>
              <td style="text-align:right">
                <span class="mono" style="font-size:13px;font-weight:700"
                  :class="g.revenue_approved > 0 ? 'approved-text' : 'zero-text'">
                  {{ fmt(g.revenue_approved) }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-else-if="!loading" class="adm-empty">Nenhum jogo no período.</div>
        <div v-else class="adm-empty" style="color:transparent">—</div>
      </div>

    </template>
  </div>
</template>

<style scoped>
/* ── Header ──────────────────────────────────────────────── */
.adm-page-header { margin-bottom: 28px; }
.adm-title { font-size: 32px; line-height: 1; text-transform: uppercase; letter-spacing: -0.02em; }
.adm-sub   { color: var(--muted); font-size: 13px; margin-top: 6px; }
.adm-error {
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #c62828;
  margin-bottom: 20px;
}

/* ── Period bar ──────────────────────────────────────────── */
.period-bar {
  display: flex; align-items: center; gap: 12px;
  flex-wrap: wrap; margin-bottom: 28px;
  padding: 18px 20px;
  background: white; border: 1px solid rgba(11,19,43,0.08);
  border-radius: 14px;
}
.period-group { display: flex; align-items: center; gap: 8px; }
.period-label {
  font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase;
  color: var(--muted); white-space: nowrap;
}
.date-input {
  appearance: none; border: 1px solid rgba(11,19,43,0.14);
  background: var(--paper); padding: 8px 12px; border-radius: 8px;
  font-family: 'JetBrains Mono', monospace; font-size: 13px; color: var(--ink);
  cursor: pointer; min-height: 44px;
}
.date-input:focus { outline: 2px solid var(--gold); outline-offset: -1px; }
.apply-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px; border-radius: 10px; border: 0;
  background: var(--navy); color: var(--paper);
  font-family: 'Archivo', sans-serif; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: background 0.15s, transform 0.1s;
  min-height: 44px; letter-spacing: 0.04em; text-transform: uppercase;
}
.apply-btn:hover:not(:disabled) { background: var(--navy-soft); transform: translateY(-1px); }
.apply-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.apply-btn svg { width: 15px; height: 15px; flex-shrink: 0; }
@keyframes spin { to { transform: rotate(360deg); } }
.spin-icon { animation: spin 0.9s linear infinite; }

/* ── Shimmer loading ─────────────────────────────────────── */
@keyframes shimmer {
  0%   { opacity: 1; }
  50%  { opacity: 0.45; }
  100% { opacity: 1; }
}
.shimmer { animation: shimmer 1.2s ease-in-out infinite; pointer-events: none; }

/* ── Total banner ────────────────────────────────────────── */
.total-banner {
  display: flex; align-items: center; justify-content: space-between;
  gap: 16px; flex-wrap: wrap;
  padding: 20px 24px; margin-bottom: 32px;
  background: var(--navy-deep); border-radius: 14px;
  border: 1px solid rgba(232,184,66,0.25);
}
.total-label {
  font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase;
  color: rgba(247,244,237,0.55);
}
.total-value {
  font-size: 36px; letter-spacing: -0.02em;
  color: var(--gold); line-height: 1;
}

/* ── Section headers ─────────────────────────────────────── */
.section-header {
  display: flex; align-items: center; gap: 12px;
  margin: 28px 0 16px;
}
.section-bar { width: 4px; height: 22px; background: var(--gold); border-radius: 2px; flex-shrink: 0; }
.section-title {
  font-size: 18px; text-transform: uppercase;
  letter-spacing: -0.01em; color: var(--ink);
}

/* ── Revenue cards ───────────────────────────────────────── */
.revenue-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
  margin-bottom: 8px;
}
@media (max-width: 640px) { .revenue-grid { grid-template-columns: 1fr; } }
.revenue-card {
  background: white; border: 1px solid rgba(11,19,43,0.08);
  border-radius: 14px; padding: 20px 22px;
}
.rev-title {
  font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase;
  color: var(--muted); margin-bottom: 16px;
}
.rev-rows { display: flex; flex-direction: column; gap: 10px; }
.rev-row {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; border-radius: 8px;
}
.rev-row.approved { background: rgba(34,197,94,0.07); }
.rev-row.pending  { background: rgba(232,184,66,0.1); }
.rev-row.failed   { background: rgba(255,59,48,0.06); }
.rev-dot {
  width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
}
.rev-row.approved .rev-dot { background: #22c55e; }
.rev-row.pending  .rev-dot { background: var(--gold); }
.rev-row.failed   .rev-dot { background: #FF3B30; }
.rev-status {
  font-size: 13px; font-weight: 600; color: var(--ink); flex: 1;
}
.rev-amount {
  font-size: 14px; font-weight: 700; color: var(--ink);
  letter-spacing: 0.02em;
}

/* ── Stat cards ──────────────────────────────────────────── */
.stat-grid {
  display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
  margin-bottom: 8px;
}
@media (max-width: 480px) { .stat-grid { grid-template-columns: 1fr; } }
.stat-card {
  background: white; border: 1px solid rgba(11,19,43,0.08);
  border-radius: 14px; padding: 24px 22px;
  display: flex; flex-direction: column; align-items: flex-start; gap: 6px;
}
.stat-value {
  font-size: 48px; line-height: 1; color: var(--navy);
  letter-spacing: -0.03em;
}
.stat-label {
  font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase;
  color: var(--muted);
}

/* ── Tables ──────────────────────────────────────────────── */
.adm-table-wrap {
  background: white; border: 1px solid rgba(11,19,43,0.08);
  border-radius: 14px; overflow: hidden; margin-bottom: 8px;
  overflow-x: auto;
}
.adm-table { width: 100%; border-collapse: collapse; min-width: 400px; }
.adm-table th {
  background: var(--navy); color: var(--paper);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase;
  padding: 13px 20px; text-align: left; font-weight: 500;
}
.adm-table td {
  padding: 13px 20px; font-size: 14px;
  border-bottom: 1px solid rgba(11,19,43,0.06);
  vertical-align: middle;
}
.adm-table tr:last-child td { border-bottom: 0; }
.adm-table tr:hover td { background: rgba(11,19,43,0.02); }
.adm-empty {
  text-align: center; padding: 40px; color: var(--muted); font-size: 14px;
}
.group-name { font-weight: 600; color: var(--ink); }
.count-num  {
  font-family: 'Archivo Black', sans-serif;
  font-size: 18px; color: var(--navy);
}
.count-num.gold  { color: var(--gold-deep); }
.count-num.zero  { color: var(--muted); font-size: 14px; font-family: 'Archivo', sans-serif; }
.approved-text { color: #15803d; }
.zero-text     { color: var(--muted); font-weight: 400 !important; }

/* ── Mobile ──────────────────────────────────────────────── */
@media (max-width: 768px) {
  .adm-title { font-size: 24px; }
  .total-value { font-size: 26px; }
  .stat-value  { font-size: 36px; }
  .period-bar  { padding: 14px 16px; }
}
</style>
