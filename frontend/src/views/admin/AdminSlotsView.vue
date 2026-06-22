<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { fetchAdminSlots, createAdminSlot, updateAdminSlot, deleteAdminSlot } from '../../api.js'

const router  = useRouter()
const slots   = ref([])
const loading = ref(false)
const error   = ref('')

// Modal de criação
const showCreate = ref(false)
const createForm = ref({ weekday: 1, start_hour: 20, start_minute: 0, duration_m: 60, label: '' })

// Modal de edição
const showEdit = ref(false)
const editForm = ref({})

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

async function create() {
  loading.value = true
  error.value   = ''
  try {
    await createAdminSlot(createForm.value)
    showCreate.value = false
    createForm.value = { weekday: 1, start_hour: 20, start_minute: 0, duration_m: 60, label: '' }
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

function openEdit(slot) {
  editForm.value  = { ...slot }
  showEdit.value  = true
  error.value     = ''
}

async function saveEdit() {
  loading.value = true
  error.value   = ''
  try {
    await updateAdminSlot(editForm.value.id, {
      weekday:      editForm.value.weekday,
      start_hour:   editForm.value.start_hour,
      start_minute: editForm.value.start_minute,
      duration_m:   editForm.value.duration_m,
      label:        editForm.value.label,
    })
    showEdit.value = false
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
      <button class="btn gold-btn" @click="showCreate = true; error = ''">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Novo Slot
      </button>
    </div>

    <!-- Modal: Criar -->
    <div v-if="showCreate" class="adm-modal-backdrop" @click.self="showCreate = false">
      <div class="adm-modal">
        <h2 class="adm-modal-title display">Novo Slot</h2>
        <form @submit.prevent="create" class="adm-form">
          <label>
            <span class="field-label">Dia da semana</span>
            <select v-model.number="createForm.weekday" class="adm-select">
              <option v-for="(d, i) in WEEKDAYS" :key="i" :value="i">{{ d }}</option>
            </select>
          </label>
          <div class="adm-form-row">
            <label>
              <span class="field-label">Hora de início</span>
              <input v-model.number="createForm.start_hour" type="number" min="0" max="23" class="adm-input" />
            </label>
            <label>
              <span class="field-label">Minuto</span>
              <select v-model.number="createForm.start_minute" class="adm-select">
                <option :value="0">:00</option>
                <option :value="15">:15</option>
                <option :value="30">:30</option>
                <option :value="45">:45</option>
              </select>
            </label>
          </div>
          <label>
            <span class="field-label">Duração (min)</span>
            <input v-model.number="createForm.duration_m" type="number" min="30" max="180" class="adm-input" />
          </label>
          <label>
            <span class="field-label">Rótulo (opcional)</span>
            <input v-model="createForm.label" type="text" placeholder="ex: Rachão da Terça" class="adm-input" />
          </label>
          <div v-if="error" class="adm-error">{{ error }}</div>
          <div class="adm-modal-actions">
            <button type="button" class="btn ghost-btn" @click="showCreate = false">Cancelar</button>
            <button type="submit" class="btn gold-btn" :disabled="loading">Salvar</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal: Editar -->
    <div v-if="showEdit" class="adm-modal-backdrop" @click.self="showEdit = false">
      <div class="adm-modal">
        <h2 class="adm-modal-title display">Editar Slot</h2>
        <form @submit.prevent="saveEdit" class="adm-form">
          <label>
            <span class="field-label">Dia da semana</span>
            <select v-model.number="editForm.weekday" class="adm-select">
              <option v-for="(d, i) in WEEKDAYS" :key="i" :value="i">{{ d }}</option>
            </select>
          </label>
          <div class="adm-form-row">
            <label>
              <span class="field-label">Hora de início</span>
              <input v-model.number="editForm.start_hour" type="number" min="0" max="23" class="adm-input" />
            </label>
            <label>
              <span class="field-label">Minuto</span>
              <select v-model.number="editForm.start_minute" class="adm-select">
                <option :value="0">:00</option>
                <option :value="15">:15</option>
                <option :value="30">:30</option>
                <option :value="45">:45</option>
              </select>
            </label>
          </div>
          <label>
            <span class="field-label">Duração (min)</span>
            <input v-model.number="editForm.duration_m" type="number" min="30" max="180" class="adm-input" />
          </label>
          <label>
            <span class="field-label">Rótulo</span>
            <input v-model="editForm.label" type="text" placeholder="ex: Rachão da Terça" class="adm-input" />
          </label>
          <div v-if="error" class="adm-error">{{ error }}</div>
          <div class="adm-modal-actions">
            <button type="button" class="btn ghost-btn" @click="showEdit = false">Cancelar</button>
            <button type="submit" class="btn gold-btn" :disabled="loading">Salvar</button>
          </div>
        </form>
      </div>
    </div>

    <div v-if="loading && !slots.length" class="adm-loading">Carregando...</div>

    <div v-else-if="slots.length === 0" class="adm-empty">
      Nenhum slot cadastrado. Adicione o primeiro.
    </div>

    <template v-else>
      <!-- Tabela (desktop) -->
      <div class="adm-table-wrap">
        <table class="adm-table">
          <thead>
            <tr>
              <th>Dia</th><th>Início</th><th>Duração</th><th>Rótulo</th><th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="s in slots" :key="s.id">
              <td>{{ WEEKDAYS[s.weekday] }}</td>
              <td>{{ String(s.start_hour).padStart(2,'0') }}:{{ String(s.start_minute ?? 0).padStart(2,'0') }}</td>
              <td>{{ s.duration_m }}min</td>
              <td>{{ s.label ?? '—' }}</td>
              <td class="adm-actions-cell">
                <button class="adm-icon-btn" @click="openEdit(s)" title="Editar">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
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

      <!-- Cards (mobile) -->
      <div class="adm-cards">
        <div v-for="s in slots" :key="s.id" class="adm-card">
          <div class="adm-card-row">
            <div class="adm-card-left">
              <span class="adm-card-day">{{ WEEKDAYS[s.weekday] }}</span>
              <span class="adm-card-time">{{ String(s.start_hour).padStart(2,'0') }}:{{ String(s.start_minute ?? 0).padStart(2,'0') }}</span>
            </div>
            <div class="adm-card-right">
              <span class="adm-card-dur">{{ s.duration_m }}min</span>
              <button class="adm-icon-btn" @click="openEdit(s)" title="Editar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
              <button class="adm-icon-btn danger" @click="remove(s.id)" title="Remover">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                  <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                </svg>
              </button>
            </div>
          </div>
          <div v-if="s.label" class="adm-card-label">{{ s.label }}</div>
        </div>
      </div>
    </template>
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
.adm-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
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
.adm-actions-cell { display: flex; gap: 4px; align-items: center; }

.adm-icon-btn {
  appearance: none; border: 0; background: transparent;
  cursor: pointer; padding: 6px; border-radius: 8px;
  transition: background 0.15s; display: inline-flex; color: var(--muted);
}
.adm-icon-btn:hover { background: rgba(11,19,43,0.06); color: var(--navy); }
.adm-icon-btn svg { width: 16px; height: 16px; }
.adm-icon-btn.danger { color: #9b1c14; }
.adm-icon-btn.danger:hover { background: rgba(255,59,48,0.1); color: #9b1c14; }

/* Cards — ocultos no desktop */
.adm-cards { display: none; }

@media (max-width: 768px) {
  .adm-page-header { flex-direction: column; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
  .adm-title { font-size: 24px; }
  .adm-modal { padding: 24px; }

  .adm-table-wrap { display: none; }

  .adm-cards { display: flex; flex-direction: column; gap: 10px; }

  .adm-card {
    background: white; border-radius: 14px;
    border: 1px solid rgba(11,19,43,0.08);
    padding: 16px 18px;
  }
  .adm-card-row { display: flex; align-items: center; justify-content: space-between; }
  .adm-card-left { display: flex; align-items: baseline; gap: 10px; }
  .adm-card-right { display: flex; align-items: center; gap: 6px; }

  .adm-card-day {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase;
    color: var(--muted);
  }
  .adm-card-time {
    font-family: 'Archivo Black', sans-serif;
    font-size: 22px; color: var(--navy); letter-spacing: -0.02em;
  }
  .adm-card-dur {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px; letter-spacing: 0.1em;
    background: rgba(11,19,43,0.06); color: var(--navy);
    padding: 3px 8px; border-radius: 99px;
  }
  .adm-card-label {
    margin-top: 6px;
    font-size: 13px; color: var(--muted);
  }
}
</style>
