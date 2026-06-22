<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router   = useRouter()
const route    = useRoute()
const token    = ref('')
const password = ref('')
const confirm  = ref('')
const loading  = ref(false)
const error    = ref('')
const done     = ref(false)

const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

onMounted(() => {
  token.value = route.query.token ?? ''
  if (!token.value) error.value = 'Link inválido. Solicite um novo.'
})

async function submit() {
  error.value = ''
  if (password.value !== confirm.value) {
    error.value = 'As senhas não coincidem'
    return
  }
  loading.value = true
  try {
    const res  = await fetch(`${BASE}/api/user/reset-password`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ token: token.value, password: password.value }),
    })
    const data = await res.json()
    if (!res.ok) { error.value = data.error ?? 'Erro ao redefinir senha'; return }
    done.value = true
    setTimeout(() => router.push('/login'), 2500)
  } catch {
    error.value = 'Erro de conexão'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-brand">
        <span class="display">RE</span><span class="display gold">PLAY</span>
      </div>
      <p class="auth-sub">Redefinir senha</p>

      <div v-if="done" class="auth-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Senha redefinida! Redirecionando...
      </div>

      <form v-else @submit.prevent="submit" class="auth-form">
        <label>
          <span class="field-label">Nova senha</span>
          <input v-model="password" type="password" autocomplete="new-password" minlength="6" required :disabled="loading || !token" />
        </label>

        <label>
          <span class="field-label">Confirmar senha</span>
          <input v-model="confirm" type="password" autocomplete="new-password" minlength="6" required :disabled="loading || !token" />
        </label>

        <div v-if="error" class="auth-error">{{ error }}</div>

        <button type="submit" class="btn gold-btn" :disabled="loading || !token" style="width:100%;justify-content:center">
          <svg v-if="loading" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          {{ loading ? 'Salvando...' : 'Salvar nova senha' }}
        </button>
      </form>

      <router-link to="/login" class="back-to-login">← Voltar ao login</router-link>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh;
  background: var(--navy);
  display: grid;
  place-items: center;
  padding: 24px;
}
.auth-card {
  background: var(--paper);
  border-radius: 20px;
  padding: 48px 40px;
  width: 100%;
  max-width: 420px;
  box-shadow: 0 24px 80px rgba(7,21,58,0.4);
}
.auth-brand {
  font-size: 40px; line-height: 1;
  letter-spacing: -0.03em; text-transform: uppercase;
  margin-bottom: 4px;
}
.auth-sub {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase;
  color: var(--muted); margin: 0 0 36px;
}
.auth-form { display: flex; flex-direction: column; gap: 20px; }
.auth-form label { display: flex; flex-direction: column; gap: 6px; }
.field-label {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase;
  color: var(--muted);
}
.auth-form input {
  border: 1px solid rgba(11,19,43,0.14);
  border-radius: 10px; padding: 14px 16px;
  font-family: 'Archivo', sans-serif; font-size: 15px; color: var(--ink);
  background: white; transition: border-color 0.15s, outline 0.15s;
}
.auth-form input:focus {
  outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent;
}
.auth-error {
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #c62828;
}
.auth-success {
  display: flex; align-items: center; gap: 10px;
  background: rgba(52,199,89,0.12); border: 1px solid rgba(52,199,89,0.35);
  border-radius: 12px; padding: 16px 18px;
  color: #1a7a37; font-size: 14px; font-weight: 600;
}
.auth-success svg { width: 20px; height: 20px; color: #34c759; flex-shrink: 0; }
.back-to-login {
  display: block; text-align: center; margin-top: 24px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.12em; color: var(--muted); text-decoration: none;
  transition: color 0.15s;
}
.back-to-login:hover { color: var(--navy); }
.spin { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 480px) { .auth-card { padding: 36px 24px; } }
</style>
