<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const router  = useRouter()
const name     = ref('')
const email    = ref('')
const password = ref('')
const loading  = ref(false)
const error    = ref('')

const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    const res = await fetch(`${BASE}/api/user/register`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ name: name.value, email: email.value, password: password.value }),
    })
    const data = await res.json()
    if (!res.ok) { error.value = data.error ?? 'Erro ao criar conta'; return }

    localStorage.setItem('user_token', data.token)
    localStorage.setItem('user_name',  data.name)
    localStorage.setItem('user_email', data.email)
    router.push('/assinar')
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
      <p class="auth-sub">Criar conta</p>

      <form @submit.prevent="submit" class="auth-form">
        <label>
          <span class="field-label">Nome do time / capitão</span>
          <input v-model="name" type="text" autocomplete="name" placeholder="Ex: Flamenguinhos FC" required :disabled="loading" />
        </label>

        <label>
          <span class="field-label">E-mail</span>
          <input v-model="email" type="email" autocomplete="email" required :disabled="loading" />
        </label>

        <label>
          <span class="field-label">Senha</span>
          <input v-model="password" type="password" autocomplete="new-password" minlength="6" required :disabled="loading" />
          <span class="field-hint">Mínimo 6 caracteres</span>
        </label>

        <div v-if="error" class="auth-error">{{ error }}</div>

        <button type="submit" class="btn gold-btn" :disabled="loading" style="width:100%;justify-content:center">
          <svg v-if="loading" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          {{ loading ? 'Criando conta...' : 'Criar conta' }}
        </button>
      </form>

      <p class="auth-footer-links">
        Já tem conta?
        <router-link to="/login">Entrar</router-link>
      </p>
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
  font-size: 40px;
  line-height: 1;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  margin-bottom: 4px;
}
.auth-sub {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--muted);
  margin: 0 0 36px;
}
.auth-form { display: flex; flex-direction: column; gap: 20px; }
.auth-form label { display: flex; flex-direction: column; gap: 6px; }
.field-label {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--muted);
}
.field-hint {
  font-size: 11px;
  color: var(--muted);
}
.auth-form input {
  border: 1px solid rgba(11,19,43,0.14);
  border-radius: 10px;
  padding: 14px 16px;
  font-family: 'Archivo', sans-serif;
  font-size: 15px;
  color: var(--ink);
  background: white;
  transition: border-color 0.15s, outline 0.15s;
}
.auth-form input:focus {
  outline: 2px solid var(--gold);
  outline-offset: -1px;
  border-color: transparent;
}
.auth-error {
  background: rgba(255,59,48,0.1);
  border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 13px;
  color: #c62828;
}
.auth-footer-links {
  text-align: center;
  margin-top: 24px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.1em;
  color: var(--muted);
}
.auth-footer-links a {
  color: var(--navy);
  font-weight: 700;
  text-decoration: none;
  margin-left: 4px;
}
.auth-footer-links a:hover { color: var(--gold); }
.spin {
  width: 16px; height: 16px;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 480px) {
  .auth-card { padding: 36px 24px; }
}
</style>
