<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchAdminGames } from '../../api.js'

const router = useRouter()
const games  = ref([])
const loading = ref(false)
const error   = ref('')

async function load() {
  loading.value = true
  try {
    games.value = await fetchAdminGames()
  } catch (e) {
    if (e.message === 'ADMIN_UNAUTHORIZED') { router.push('/admin/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function formatDate(d) {
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR', { weekday: 'short', day: '2-digit', month: '2-digit' })
}

function gameUrl(qrToken) {
  return `${window.location.origin}/jogo/${qrToken}`
}

function copyLink(qrToken) {
  navigator.clipboard.writeText(gameUrl(qrToken))
}

onMounted(load)
</script>

<template>
  <div>
    <div class="adm-page-header">
      <div>
        <h1 class="adm-title display">Jogos</h1>
        <p class="adm-sub">Criados automaticamente quando o primeiro clipe chega</p>
      </div>
    </div>

    <div v-if="loading && !games.length" class="adm-loading">Carregando...</div>
    <div v-else-if="error" class="adm-error">{{ error }}</div>

    <div v-else-if="games.length === 0" class="adm-empty">
      Nenhum jogo registrado ainda.
    </div>

    <template v-else>
      <!-- Tabela (desktop) -->
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Data</th><th>Câmera</th><th>Hora</th><th>Clipes</th><th>QR / Link</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in games" :key="g.id">
              <td>{{ formatDate(g.slot_date) }}</td>
              <td><span class="mono">{{ g.camera_id }}</span></td>
              <td>{{ String(g.slot_hour).padStart(2,'0') }}:00</td>
              <td>
                <span class="clip-count">{{ g.clip_count }}</span>
              </td>
              <td>
                <div class="adm-link-cell">
                  <span class="mono" style="font-size:11px;color:var(--muted)">{{ g.qr_token }}</span>
                  <button class="adm-icon-btn" @click="copyLink(g.qr_token)" title="Copiar link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                    </svg>
                  </button>
                  <a :href="gameUrl(g.qr_token)" target="_blank" class="adm-icon-btn" title="Abrir">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                      <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                    </svg>
                  </a>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards (mobile) -->
      <div class="adm-cards">
        <div v-for="g in games" :key="g.id" class="adm-card">
          <div class="adm-card-top">
            <div class="adm-card-info">
              <span class="adm-card-date">{{ formatDate(g.slot_date) }}</span>
              <span class="adm-card-sub">{{ g.camera_id }} · {{ String(g.slot_hour).padStart(2,'0') }}:00</span>
            </div>
            <div class="adm-card-clips">
              <span class="clip-count">{{ g.clip_count }}</span>
              <span class="adm-card-clips-label">clipes</span>
            </div>
          </div>
          <div class="adm-card-token">
            <span class="mono adm-token-text">{{ g.qr_token }}</span>
            <div class="adm-card-btns">
              <button class="adm-icon-btn" @click="copyLink(g.qr_token)" title="Copiar link">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                </svg>
              </button>
              <a :href="gameUrl(g.qr_token)" target="_blank" class="adm-icon-btn" title="Abrir">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.adm-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 32px; }
.adm-title { font-size: 32px; line-height: 1; text-transform: uppercase; letter-spacing: -0.02em; }
.adm-sub { color: var(--muted); font-size: 13px; margin-top: 6px; }
.adm-loading, .adm-empty { color: var(--muted); padding: 48px; text-align: center; font-size: 14px; }
.adm-error { background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3); border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #c62828; margin-bottom: 20px; }
.adm-table-wrap { background: white; border-radius: 16px; border: 1px solid rgba(11,19,43,0.08); overflow: hidden; }
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th { background: var(--navy); color: var(--paper); font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; padding: 14px 20px; text-align: left; font-weight: 500; }
.adm-table td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid rgba(11,19,43,0.06); vertical-align: middle; }
.adm-table tr:last-child td { border-bottom: 0; }
.adm-table tr:hover td { background: rgba(11,19,43,0.02); }
.clip-count { font-family: 'Archivo Black', sans-serif; font-size: 18px; color: var(--navy); }
.adm-link-cell { display: flex; align-items: center; gap: 8px; }
.adm-icon-btn { appearance: none; border: 0; background: transparent; cursor: pointer; padding: 6px; border-radius: 8px; transition: background 0.15s; display: inline-flex; color: var(--navy); text-decoration: none; }
.adm-icon-btn svg { width: 16px; height: 16px; }
.adm-icon-btn:hover { background: rgba(11,19,43,0.06); }

/* Cards — ocultos no desktop */
.adm-cards { display: none; }

@media (max-width: 768px) {
  .adm-page-header { flex-direction: column; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
  .adm-title { font-size: 24px; }

  .adm-table-wrap { display: none; }

  .adm-cards { display: flex; flex-direction: column; gap: 10px; }

  .adm-card {
    background: white; border-radius: 14px;
    border: 1px solid rgba(11,19,43,0.08);
    padding: 16px 18px;
    display: flex; flex-direction: column; gap: 12px;
  }
  .adm-card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  .adm-card-info { display: flex; flex-direction: column; gap: 3px; }
  .adm-card-date { font-size: 15px; font-weight: 700; color: var(--navy); text-transform: capitalize; }
  .adm-card-sub { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; }

  .adm-card-clips { display: flex; flex-direction: column; align-items: center; }
  .adm-card-clips .clip-count { font-size: 24px; line-height: 1; }
  .adm-card-clips-label { font-size: 10px; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted); font-family: 'JetBrains Mono', monospace; }

  .adm-card-token {
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(11,19,43,0.04); border-radius: 8px; padding: 8px 12px;
    gap: 8px;
  }
  .adm-token-text { font-size: 11px; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; flex: 1; min-width: 0; }
  .adm-card-btns { display: flex; gap: 2px; flex-shrink: 0; }
}
</style>
