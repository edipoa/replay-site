<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchAdminOrphanedClips, createGameForOrphanedClip } from '../../api.js'

const router  = useRouter()
const clips   = ref([])
const loading = ref(false)
const error   = ref('')

const modal   = ref(null)   // clip sendo editado
const saving  = ref(false)
const saveErr = ref('')
const form    = ref({ hour: 20, minute: 0, duration_m: 60 })
const created = ref(null)   // { qr_token } após criação

async function load() {
  loading.value = true
  error.value   = ''
  try {
    clips.value = await fetchAdminOrphanedClips()
  } catch (e) {
    if (e.message === 'ADMIN_UNAUTHORIZED') { router.push('/admin/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openModal(clip) {
  form.value    = { hour: clip.local_hour, minute: clip.local_minute, duration_m: 60 }
  saveErr.value = ''
  created.value = null
  modal.value   = clip
}

function closeModal() {
  modal.value   = null
  created.value = null
}

async function save() {
  saving.value  = true
  saveErr.value = ''
  try {
    const res = await createGameForOrphanedClip(modal.value.id, form.value)
    created.value = res
    await load()
  } catch (e) {
    saveErr.value = e.message
  } finally {
    saving.value = false
  }
}

function gameUrl(qrToken) {
  return `${window.location.origin}/jogo/${qrToken}`
}

function formatLocal(isoStr) {
  return new Date(isoStr).toLocaleString('pt-BR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

onMounted(load)
</script>

<template>
  <div>
    <div class="adm-page-header">
      <div>
        <h1 class="adm-title display">Clipes Órfãos</h1>
        <p class="adm-sub">Vídeos gravados sem jogo correspondente — crie o jogo retroativamente</p>
      </div>
      <button class="btn ghost-btn" @click="load" :disabled="loading">Atualizar</button>
    </div>

    <div v-if="error" class="adm-error">{{ error }}</div>
    <div v-if="loading && !clips.length" class="adm-loading">Carregando...</div>

    <div v-else-if="clips.length === 0 && !loading" class="adm-empty">
      Nenhum clipe órfão encontrado.
    </div>

    <template v-else>
      <!-- Tabela (desktop) -->
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead>
            <tr>
              <th>ID</th><th>Câmera</th><th>Gravado em</th><th>Duração</th><th>Status</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in clips" :key="c.id" :class="{ expired: !c.is_active }">
              <td><span class="mono">{{ c.display_id }}</span></td>
              <td><span class="mono">{{ c.camera_id }}</span></td>
              <td>{{ formatLocal(c.triggered_at) }}</td>
              <td>{{ c.duration_s }}s</td>
              <td>
                <span v-if="c.is_active" class="badge active">Ativo</span>
                <span v-else class="badge expired">Expirado</span>
              </td>
              <td>
                <button class="btn gold-btn sm" @click="openModal(c)">Criar jogo</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Cards (mobile) -->
      <div class="adm-cards">
        <div v-for="c in clips" :key="c.id" class="adm-card" :class="{ expired: !c.is_active }">
          <div class="adm-card-top">
            <div class="adm-card-info">
              <span class="adm-card-id mono">{{ c.display_id }}</span>
              <span class="adm-card-sub">{{ c.camera_id }} · {{ formatLocal(c.triggered_at) }}</span>
            </div>
            <span v-if="c.is_active" class="badge active">Ativo</span>
            <span v-else class="badge expired">Expirado</span>
          </div>
          <button class="btn gold-btn sm full" @click="openModal(c)">Criar jogo</button>
        </div>
      </div>
    </template>

    <!-- Modal -->
    <div v-if="modal" class="adm-modal-backdrop" @click.self="closeModal">
      <div class="adm-modal">
        <h2 class="adm-modal-title display">Criar Jogo</h2>
        <p class="adm-modal-sub">
          <span class="mono">{{ modal.display_id }}</span> ·
          {{ modal.camera_id }} ·
          {{ formatLocal(modal.triggered_at) }}
        </p>

        <!-- Após criação com sucesso -->
        <div v-if="created" class="created-box">
          <p class="created-msg">Jogo criado! Os clipes já aparecem no link:</p>
          <a :href="gameUrl(created.qr_token)" target="_blank" class="created-link">
            {{ gameUrl(created.qr_token) }}
          </a>
          <div class="adm-modal-actions">
            <button class="btn gold-btn" @click="closeModal">Fechar</button>
          </div>
        </div>

        <form v-else @submit.prevent="save" class="adm-form">
          <div class="adm-form-row">
            <label>
              <span class="field-label">Hora início</span>
              <input v-model.number="form.hour" type="number" min="0" max="23" class="adm-input" />
            </label>
            <label>
              <span class="field-label">Minuto início</span>
              <select v-model.number="form.minute" class="adm-select">
                <option :value="0">:00</option>
                <option :value="15">:15</option>
                <option :value="30">:30</option>
                <option :value="45">:45</option>
              </select>
            </label>
          </div>
          <label>
            <span class="field-label">Duração (min)</span>
            <input v-model.number="form.duration_m" type="number" min="30" max="300" class="adm-input" />
          </label>
          <div v-if="saveErr" class="adm-error">{{ saveErr }}</div>
          <div class="adm-modal-actions">
            <button type="button" class="btn ghost-btn" @click="closeModal">Cancelar</button>
            <button type="submit" class="btn gold-btn" :disabled="saving">
              {{ saving ? 'Criando…' : 'Criar jogo' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.adm-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 32px; }
.adm-title { font-size: 32px; line-height: 1; text-transform: uppercase; letter-spacing: -0.02em; }
.adm-sub { color: var(--muted); font-size: 13px; margin-top: 6px; }
.adm-loading, .adm-empty { color: var(--muted); padding: 48px; text-align: center; font-size: 14px; }
.adm-error { background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3); border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #c62828; margin-bottom: 16px; }

.adm-table-wrap { background: white; border-radius: 16px; border: 1px solid rgba(11,19,43,0.08); overflow: hidden; }
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th { background: var(--navy); color: var(--paper); font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; padding: 14px 20px; text-align: left; font-weight: 500; }
.adm-table td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid rgba(11,19,43,0.06); vertical-align: middle; }
.adm-table tr:last-child td { border-bottom: 0; }
.adm-table tr:hover td { background: rgba(11,19,43,0.02); }
.adm-table tr.expired td { opacity: 0.5; }

.badge {
  display: inline-block; padding: 3px 10px; border-radius: 99px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.1em; text-transform: uppercase;
}
.badge.active   { background: rgba(52,199,89,0.12); color: #1b7a36; }
.badge.expired  { background: rgba(11,19,43,0.07); color: var(--muted); }

.btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 10px; border: 0; cursor: pointer; font-family: 'Archivo', sans-serif; font-size: 14px; font-weight: 700; transition: opacity 0.15s; }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn.sm { padding: 7px 14px; font-size: 13px; }
.btn.full { width: 100%; justify-content: center; }
.gold-btn { background: var(--gold); color: var(--navy-deep); }
.gold-btn:hover:not(:disabled) { opacity: 0.88; }
.ghost-btn { background: rgba(11,19,43,0.07); color: var(--navy); }
.ghost-btn:hover:not(:disabled) { background: rgba(11,19,43,0.12); }

.adm-modal-backdrop { position: fixed; inset: 0; background: rgba(7,21,58,0.6); display: grid; place-items: center; z-index: 100; padding: 24px; }
.adm-modal { background: var(--paper); border-radius: 20px; padding: 36px; width: 100%; max-width: 460px; box-shadow: 0 32px 80px rgba(7,21,58,0.3); }
.adm-modal-title { font-size: 24px; text-transform: uppercase; letter-spacing: -0.02em; margin-bottom: 6px; }
.adm-modal-sub { font-size: 13px; color: var(--muted); margin-bottom: 24px; }
.adm-form { display: flex; flex-direction: column; gap: 16px; }
.adm-form label { display: flex; flex-direction: column; gap: 8px; }
.adm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.field-label { font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); }
.adm-input, .adm-select { border: 1px solid rgba(11,19,43,0.14); border-radius: 10px; padding: 12px 14px; font-family: 'Archivo', sans-serif; font-size: 14px; color: var(--ink); background: white; }
.adm-input:focus, .adm-select:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.adm-modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 8px; }

.created-box { display: flex; flex-direction: column; gap: 14px; }
.created-msg { font-size: 14px; color: var(--ink); }
.created-link { font-family: 'JetBrains Mono', monospace; font-size: 12px; color: var(--gold); word-break: break-all; text-decoration: underline; }

/* Cards — ocultos no desktop */
.adm-cards { display: none; }

@media (max-width: 768px) {
  .adm-page-header { flex-direction: column; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
  .adm-title { font-size: 24px; }
  .adm-modal { padding: 24px; }
  .adm-table-wrap { display: none; }

  .adm-cards { display: flex; flex-direction: column; gap: 10px; }
  .adm-card { background: white; border-radius: 14px; border: 1px solid rgba(11,19,43,0.08); padding: 16px 18px; display: flex; flex-direction: column; gap: 12px; }
  .adm-card.expired { opacity: 0.55; }
  .adm-card-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 8px; }
  .adm-card-info { display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 0; }
  .adm-card-id { font-size: 15px; font-weight: 700; color: var(--navy); }
  .adm-card-sub { font-size: 12px; color: var(--muted); font-family: 'JetBrains Mono', monospace; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
}
</style>
