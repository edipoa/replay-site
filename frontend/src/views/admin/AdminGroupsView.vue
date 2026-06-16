<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchAdminGroups, fetchAdminSlots, createAdminGroup, updateAdminGroup, deleteAdminGroup } from '../../api.js'

const router  = useRouter()
const groups  = ref([])
const slots   = ref([])
const loading = ref(false)
const error   = ref('')
const modal   = ref(null) // null | 'create' | { id, mode: 'edit' }
const editId  = ref(null)

const WEEKDAYS = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom']

const blank = () => ({
  name: '', camera_id: 'cam1', slot_weekday: 1, slot_hour: 20,
  login: '', password: '', subscription_expires_at: '',
})
const form = ref(blank())

async function load() {
  loading.value = true
  try {
    [groups.value, slots.value] = await Promise.all([fetchAdminGroups(), fetchAdminSlots()])
  } catch (e) {
    if (e.message === 'ADMIN_UNAUTHORIZED') { router.push('/admin/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openCreate() { form.value = blank(); editId.value = null; modal.value = 'open' }

function openEdit(g) {
  form.value = {
    name: g.name, camera_id: g.camera_id,
    slot_weekday: g.slot_weekday, slot_hour: g.slot_hour,
    login: g.login, password: '',
    subscription_expires_at: g.subscription_expires_at?.slice(0, 10) ?? '',
  }
  editId.value  = g.id
  modal.value   = 'open'
}

async function save() {
  loading.value = true
  error.value   = ''
  try {
    const payload = { ...form.value }
    if (editId.value) {
      if (!payload.password) delete payload.password
      await updateAdminGroup(editId.value, payload)
    } else {
      await createAdminGroup(payload)
    }
    modal.value = null
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function remove(id) {
  if (!confirm('Remover este grupo? Os tokens serão revogados.')) return
  await deleteAdminGroup(id).catch(e => { error.value = e.message })
  await load()
}

function isExpired(d) { return new Date(d) < new Date() }

onMounted(load)
</script>

<template>
  <div>
    <div class="adm-page-header">
      <div>
        <h1 class="adm-title display">Grupos</h1>
        <p class="adm-sub">Times com assinatura mensal</p>
      </div>
      <button class="btn gold-btn" @click="openCreate">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Novo Grupo
      </button>
    </div>

    <!-- Modal -->
    <div v-if="modal === 'open'" class="adm-modal-backdrop" @click.self="modal = null">
      <div class="adm-modal">
        <h2 class="adm-modal-title display">{{ editId ? 'Editar Grupo' : 'Novo Grupo' }}</h2>
        <form @submit.prevent="save" class="adm-form">
          <div class="adm-form-row">
            <label>
              <span class="field-label">Nome do time</span>
              <input v-model="form.name" type="text" required class="adm-input" placeholder="Rachão da Terça" />
            </label>
            <label>
              <span class="field-label">Câmera</span>
              <select v-model="form.camera_id" class="adm-select">
                <option v-for="s in [...new Set(slots.map(x=>x.camera_id))]" :key="s" :value="s">{{ s }}</option>
                <option value="cam1">cam1</option>
                <option value="cam2">cam2</option>
              </select>
            </label>
          </div>
          <div class="adm-form-row">
            <label>
              <span class="field-label">Dia do slot</span>
              <select v-model.number="form.slot_weekday" class="adm-select">
                <option v-for="(d, i) in WEEKDAYS" :key="i" :value="i">{{ d }}</option>
              </select>
            </label>
            <label>
              <span class="field-label">Hora do slot</span>
              <input v-model.number="form.slot_hour" type="number" min="0" max="23" class="adm-input" />
            </label>
          </div>
          <div class="adm-form-row">
            <label>
              <span class="field-label">Login</span>
              <input v-model="form.login" type="text" required class="adm-input" />
            </label>
            <label>
              <span class="field-label">Senha {{ editId ? '(deixe em branco para manter)' : '' }}</span>
              <input v-model="form.password" type="password" :required="!editId" class="adm-input" />
            </label>
          </div>
          <label>
            <span class="field-label">Assinatura válida até</span>
            <input v-model="form.subscription_expires_at" type="date" required class="adm-input" />
          </label>
          <div v-if="error" class="adm-error">{{ error }}</div>
          <div class="adm-modal-actions">
            <button type="button" class="btn ghost-btn" @click="modal = null">Cancelar</button>
            <button type="submit" class="btn gold-btn" :disabled="loading">Salvar</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="loading && !groups.length" class="adm-loading">Carregando...</div>

    <div v-else-if="groups.length === 0" class="adm-empty">
      Nenhum grupo cadastrado.
    </div>

    <div v-else class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Time</th><th>Login</th><th>Câmera</th><th>Slot</th><th>Assinatura</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="g in groups" :key="g.id">
            <td><strong>{{ g.name }}</strong></td>
            <td><span class="mono">{{ g.login }}</span></td>
            <td><span class="mono">{{ g.camera_id }}</span></td>
            <td>{{ WEEKDAYS[g.slot_weekday] }} {{ String(g.slot_hour).padStart(2,'0') }}:00</td>
            <td>
              <span :class="['exp-badge', isExpired(g.subscription_expires_at) ? 'urgent' : '']">
                <span class="exp-dot" />
                {{ new Date(g.subscription_expires_at).toLocaleDateString('pt-BR') }}
              </span>
            </td>
            <td class="adm-actions-cell">
              <button class="adm-icon-btn" @click="openEdit(g)" title="Editar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
              <button class="adm-icon-btn danger" @click="remove(g.id)" title="Remover">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.adm-page-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 32px; }
.adm-title { font-size: 32px; line-height: 1; text-transform: uppercase; letter-spacing: -0.02em; }
.adm-sub { color: var(--muted); font-size: 13px; margin-top: 6px; }
.adm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.adm-modal-backdrop { position: fixed; inset: 0; background: rgba(7,21,58,0.6); display: grid; place-items: center; z-index: 100; padding: 24px; }
.adm-modal { background: var(--paper); border-radius: 20px; padding: 36px; width: 100%; max-width: 560px; box-shadow: 0 32px 80px rgba(7,21,58,0.3); max-height: 90vh; overflow-y: auto; }
.adm-modal-title { font-size: 24px; text-transform: uppercase; letter-spacing: -0.02em; margin-bottom: 24px; }
.adm-form { display: flex; flex-direction: column; gap: 16px; }
.adm-form label { display: flex; flex-direction: column; gap: 8px; }
.field-label { font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); }
.adm-input, .adm-select { border: 1px solid rgba(11,19,43,0.14); border-radius: 10px; padding: 12px 14px; font-family: 'Archivo', sans-serif; font-size: 14px; color: var(--ink); background: white; }
.adm-input:focus, .adm-select:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.adm-modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 8px; }
.adm-error { background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3); border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #c62828; }
.adm-loading, .adm-empty { color: var(--muted); padding: 48px; text-align: center; font-size: 14px; }
.adm-table-wrap { background: white; border-radius: 16px; border: 1px solid rgba(11,19,43,0.08); overflow: hidden; }
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th { background: var(--navy); color: var(--paper); font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; padding: 14px 20px; text-align: left; font-weight: 500; }
.adm-table td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid rgba(11,19,43,0.06); }
.adm-table tr:last-child td { border-bottom: 0; }
.adm-table tr:hover td { background: rgba(11,19,43,0.02); }
.adm-actions-cell { display: flex; gap: 4px; align-items: center; }
.adm-icon-btn { appearance: none; border: 0; background: transparent; cursor: pointer; padding: 6px; border-radius: 8px; transition: background 0.15s; display: inline-flex; color: var(--navy); }
.adm-icon-btn svg { width: 16px; height: 16px; }
.adm-icon-btn:hover { background: rgba(11,19,43,0.06); }
.adm-icon-btn.danger { color: #9b1c14; }
.adm-icon-btn.danger:hover { background: rgba(255,59,48,0.1); }
</style>
