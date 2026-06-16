<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchAdminSlots, createAdminSlot, deleteAdminSlot } from '../../api.js'

const router = useRouter()
const slots   = ref([])
const loading = ref(false)
const error   = ref('')
const showing = ref(false)

const form = ref({ camera_id: 'cam1', weekday: 1, start_hour: 20, duration_m: 60, label: '' })

const WEEKDAYS = ['Seg','Ter','Qua','Qui','Sex','Sáb','Dom']

async function load() {
  loading.value = true
  try {
    slots.value = await fetchAdminSlots()
  } catch (e) {
    if (e.message === 'ADMIN_UNAUTHORIZED') { router.push('/admin/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function save() {
  loading.value = true
  error.value   = ''
  try {
    await createAdminSlot(form.value)
    showing.value = false
    form.value    = { camera_id: 'cam1', weekday: 1, start_hour: 20, duration_m: 60, label: '' }
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function remove(id) {
  if (!confirm('Remover este slot?')) return
  await deleteAdminSlot(id).catch(e => { error.value = e.message })
  await load()
}

onMounted(load)
</script>

<template>
  <div>
    <div class="adm-page-header">
      <div>
        <h1 class="adm-title display">Slots de Jogo</h1>
        <p class="adm-sub">Horários recorrentes cadastrados no campo</p>
      </div>
      <button class="btn gold-btn" @click="showing = true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Novo Slot
      </button>
    </div>

    <!-- Modal -->
    <div v-if="showing" class="adm-modal-backdrop" @click.self="showing = false">
      <div class="adm-modal">
        <h2 class="adm-modal-title display">Novo Slot</h2>
        <form @submit.prevent="save" class="adm-form">
          <label>
            <span class="field-label">Câmera</span>
            <select v-model="form.camera_id" class="adm-select">
              <option value="cam1">cam1</option>
              <option value="cam2">cam2</option>
            </select>
          </label>
          <label>
            <span class="field-label">Dia da semana</span>
            <select v-model.number="form.weekday" class="adm-select">
              <option v-for="(d, i) in WEEKDAYS" :key="i" :value="i">{{ d }}</option>
            </select>
          </label>
          <label>
            <span class="field-label">Horário de início</span>
            <input v-model.number="form.start_hour" type="number" min="0" max="23" class="adm-input" />
          </label>
          <label>
            <span class="field-label">Duração (min)</span>
            <input v-model.number="form.duration_m" type="number" min="30" max="180" class="adm-input" />
          </label>
          <label>
            <span class="field-label">Rótulo (opcional)</span>
            <input v-model="form.label" type="text" placeholder="ex: Rachão da Terça" class="adm-input" />
          </label>
          <div v-if="error" class="adm-error">{{ error }}</div>
          <div class="adm-modal-actions">
            <button type="button" class="btn ghost-btn" @click="showing = false">Cancelar</button>
            <button type="submit" class="btn gold-btn" :disabled="loading">Salvar</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="loading && !slots.length" class="adm-loading">Carregando...</div>

    <div v-else-if="slots.length === 0" class="adm-empty">
      Nenhum slot cadastrado. Adicione o primeiro.
    </div>

    <div v-else class="adm-table-wrap">
      <table class="adm-table">
        <thead>
          <tr>
            <th>Câmera</th><th>Dia</th><th>Início</th><th>Duração</th><th>Rótulo</th><th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in slots" :key="s.id">
            <td><span class="mono">{{ s.camera_id }}</span></td>
            <td>{{ WEEKDAYS[s.weekday] }}</td>
            <td>{{ String(s.start_hour).padStart(2,'0') }}:00</td>
            <td>{{ s.duration_m }}min</td>
            <td>{{ s.label ?? '—' }}</td>
            <td>
              <button class="adm-icon-btn danger" @click="remove(s.id)" title="Remover">
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

.adm-modal-backdrop {
  position: fixed; inset: 0; background: rgba(7,21,58,0.6);
  display: grid; place-items: center; z-index: 100; padding: 24px;
}
.adm-modal {
  background: var(--paper); border-radius: 20px; padding: 36px;
  width: 100%; max-width: 480px;
  box-shadow: 0 32px 80px rgba(7,21,58,0.3);
}
.adm-modal-title { font-size: 24px; text-transform: uppercase; letter-spacing: -0.02em; margin-bottom: 24px; }
.adm-form { display: flex; flex-direction: column; gap: 16px; }
.adm-form label { display: flex; flex-direction: column; gap: 8px; }
.field-label { font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); }
.adm-input, .adm-select {
  border: 1px solid rgba(11,19,43,0.14); border-radius: 10px;
  padding: 12px 14px; font-family: 'Archivo', sans-serif;
  font-size: 14px; color: var(--ink); background: white;
}
.adm-input:focus, .adm-select:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.adm-modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 8px; }
.adm-error { background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3); border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #c62828; }

.adm-loading, .adm-empty { color: var(--muted); padding: 48px; text-align: center; font-size: 14px; }

.adm-table-wrap { background: white; border-radius: 16px; border: 1px solid rgba(11,19,43,0.08); overflow: hidden; }
.adm-table { width: 100%; border-collapse: collapse; }
.adm-table th {
  background: var(--navy); color: var(--paper);
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase;
  padding: 14px 20px; text-align: left; font-weight: 500;
}
.adm-table td { padding: 14px 20px; font-size: 14px; border-bottom: 1px solid rgba(11,19,43,0.06); }
.adm-table tr:last-child td { border-bottom: 0; }
.adm-table tr:hover td { background: rgba(11,19,43,0.02); }

.adm-icon-btn {
  appearance: none; border: 0; background: transparent;
  cursor: pointer; padding: 6px; border-radius: 8px;
  transition: background 0.15s;
  display: inline-flex;
}
.adm-icon-btn svg { width: 16px; height: 16px; }
.adm-icon-btn.danger { color: #9b1c14; }
.adm-icon-btn.danger:hover { background: rgba(255,59,48,0.1); }
</style>
