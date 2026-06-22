<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'

const router  = useRouter()
const slots   = ref([])
const loading = ref(true)
const paying  = ref(false)
const error   = ref('')
const selected = ref(null)
const teamName = ref('')
const method   = ref('credit_card')

const BASE  = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')
const token = localStorage.getItem('user_token') ?? ''

const days = ['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom']

onMounted(async () => {
  if (!token) { router.push('/login'); return }
  try {
    const res = await fetch(`${BASE}/api/user/slots`)
    if (!res.ok) throw new Error()
    slots.value = await res.json()
  } catch {
    error.value = 'Falha ao carregar horários disponíveis'
  } finally {
    loading.value = false
  }
  // Preenche nome com o salvo
  teamName.value = localStorage.getItem('user_name') ?? ''
})

const canSubmit = computed(() => selected.value && teamName.value.trim().length >= 2 && !paying.value)

function slotLabel(s) {
  if (s.display) return s.display
  const day  = days[s.weekday] ?? ''
  const time = String(s.start_hour).padStart(2, '0') + ':' + String(s.start_minute).padStart(2, '0')
  return `${day} ${time}`
}

async function subscribe() {
  if (!canSubmit.value) return
  error.value = ''
  paying.value = true
  try {
    const res  = await fetch(`${BASE}/api/user/subscribe`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${token}` },
      body:    JSON.stringify({ slot_id: selected.value, team_name: teamName.value.trim(), method: method.value }),
    })
    const data = await res.json()
    if (!res.ok) { error.value = data.error ?? 'Erro ao iniciar assinatura'; return }

    localStorage.setItem('group_token', data.group_token)

    if (method.value === 'credit_card' && data.checkout_url) {
      window.location.href = data.checkout_url
    } else {
      // PIX mock ou retorno
      router.push('/grupo')
    }
  } catch {
    error.value = 'Erro de conexão'
  } finally {
    paying.value = false
  }
}
</script>

<template>
  <div class="sub-page">
    <div class="sub-card">
      <div class="sub-header">
        <div class="auth-brand">
          <span class="display">RE</span><span class="display gold">PLAY</span>
        </div>
        <p class="auth-sub">Escolha seu horário</p>
      </div>

      <div v-if="loading" class="sub-loading">
        <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
        Carregando horários...
      </div>

      <template v-else-if="slots.length === 0 && !error">
        <div class="sub-empty">
          <p class="display">Sem horários disponíveis</p>
          <p>Entre em contato com o campo para mais informações.</p>
        </div>
      </template>

      <template v-else>
        <div class="form-section">
          <span class="field-label">Nome do time</span>
          <input
            v-model="teamName"
            type="text"
            placeholder="Ex: Flamenguinhos FC"
            class="text-input"
            :disabled="paying"
          />
        </div>

        <div class="form-section">
          <span class="field-label">Horário</span>
          <div class="slots-grid">
            <button
              v-for="s in slots"
              :key="s.id"
              type="button"
              class="slot-btn"
              :class="{ active: selected === s.id }"
              @click="selected = s.id"
              :disabled="paying"
            >
              <span class="slot-name">{{ slotLabel(s) }}</span>
              <span class="slot-dur">{{ s.duration_m }}min</span>
            </button>
          </div>
        </div>

        <div class="form-section">
          <span class="field-label">Forma de pagamento</span>
          <div class="method-row">
            <button
              type="button"
              class="method-btn"
              :class="{ active: method === 'credit_card' }"
              @click="method = 'credit_card'"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
              Cartão
            </button>
            <button
              type="button"
              class="method-btn"
              :class="{ active: method === 'pix' }"
              @click="method = 'pix'"
            >
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
              PIX
            </button>
          </div>
        </div>

        <div v-if="error" class="auth-error">{{ error }}</div>

        <button
          class="btn gold-btn"
          style="width:100%;justify-content:center;margin-top:8px"
          :disabled="!canSubmit"
          @click="subscribe"
        >
          <svg v-if="paying" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          {{ paying ? 'Processando...' : 'Assinar agora' }}
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.sub-page {
  min-height: 100vh;
  background: var(--navy);
  display: grid;
  place-items: center;
  padding: 24px;
}
.sub-card {
  background: var(--paper);
  border-radius: 20px;
  padding: 48px 40px;
  width: 100%;
  max-width: 480px;
  box-shadow: 0 24px 80px rgba(7,21,58,0.4);
}
.sub-header { margin-bottom: 32px; }
.auth-brand {
  font-size: 40px; line-height: 1;
  letter-spacing: -0.03em; text-transform: uppercase;
  margin-bottom: 4px;
}
.auth-sub {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase;
  color: var(--muted); margin: 0;
}
.sub-loading {
  display: flex; align-items: center; gap: 12px;
  color: var(--muted);
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px;
}
.sub-empty { text-align: center; color: var(--muted); padding: 24px 0; }
.form-section { margin-bottom: 24px; display: flex; flex-direction: column; gap: 10px; }
.field-label {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase;
  color: var(--muted);
}
.text-input {
  border: 1px solid rgba(11,19,43,0.14);
  border-radius: 10px; padding: 14px 16px;
  font-family: 'Archivo', sans-serif; font-size: 15px; color: var(--ink);
  background: white; transition: border-color 0.15s, outline 0.15s;
  width: 100%;
}
.text-input:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.slots-grid { display: flex; flex-direction: column; gap: 8px; }
.slot-btn {
  appearance: none; border: 1px solid rgba(11,19,43,0.14);
  border-radius: 10px; padding: 14px 16px;
  background: white; cursor: pointer;
  display: flex; align-items: center; justify-content: space-between;
  transition: all 0.15s; text-align: left;
}
.slot-btn:hover { border-color: var(--navy); }
.slot-btn.active { background: var(--navy); border-color: var(--navy); }
.slot-btn.active .slot-name { color: var(--paper); }
.slot-btn.active .slot-dur  { color: var(--gold); }
.slot-name { font-family: 'Archivo Black', sans-serif; font-size: 15px; color: var(--ink); }
.slot-dur  {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; color: var(--muted); letter-spacing: 0.08em;
}
.method-row { display: flex; gap: 10px; }
.method-btn {
  flex: 1; appearance: none;
  border: 1px solid rgba(11,19,43,0.14); border-radius: 10px;
  padding: 14px; background: white; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  font-family: 'Archivo', sans-serif; font-size: 14px; font-weight: 600;
  color: var(--ink); transition: all 0.15s;
}
.method-btn svg { width: 18px; height: 18px; }
.method-btn:hover { border-color: var(--navy); }
.method-btn.active { background: var(--navy); border-color: var(--navy); color: var(--paper); }
.auth-error {
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #c62828;
}
.spin { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 480px) { .sub-card { padding: 36px 20px; } }
</style>
