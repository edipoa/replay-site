<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../composables/useAuth.js'

const router = useRouter()
const { login, loading, error } = useAuth()

const loginField = ref('')
const password   = ref('')

async function submit() {
  const ok = await login(loginField.value, password.value)
  if (ok) router.push('/group')
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-brand">
        <span class="display">RE</span><span class="display gold">PLAY</span>
      </div>
      <p class="login-sub">Acesso do time</p>

      <form @submit.prevent="submit" class="login-form">
        <label>
          <span class="field-label">Login</span>
          <input
            v-model="loginField"
            type="text"
            autocomplete="username"
            required
            :disabled="loading"
          />
        </label>

        <label>
          <span class="field-label">Senha</span>
          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            required
            :disabled="loading"
          />
        </label>

        <div v-if="error" class="login-error">{{ error }}</div>

        <button type="submit" class="btn gold-btn" :disabled="loading" style="width:100%;justify-content:center">
          <svg v-if="loading" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
          </svg>
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>

      <a href="/" class="back-to-home">← Voltar ao início</a>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh;
  background: var(--navy);
  display: grid;
  place-items: center;
  padding: 24px;
}
.login-card {
  background: var(--paper);
  border-radius: 20px;
  padding: 48px 40px;
  width: 100%;
  max-width: 400px;
  box-shadow: 0 24px 80px rgba(7,21,58,0.4);
}
.login-brand {
  font-size: 40px;
  line-height: 1;
  letter-spacing: -0.03em;
  text-transform: uppercase;
  margin-bottom: 4px;
}
.login-sub {
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--muted);
  margin: 0 0 36px;
}
.login-form { display: flex; flex-direction: column; gap: 20px; }
.login-form label { display: flex; flex-direction: column; gap: 8px; }
.field-label {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--muted);
}
.login-form input {
  border: 1px solid rgba(11,19,43,0.14);
  border-radius: 10px;
  padding: 14px 16px;
  font-family: 'Archivo', sans-serif;
  font-size: 15px;
  color: var(--ink);
  background: white;
  transition: border-color 0.15s, outline 0.15s;
}
.login-form input:focus {
  outline: 2px solid var(--gold);
  outline-offset: -1px;
  border-color: transparent;
}
.login-error {
  background: rgba(255,59,48,0.1);
  border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px;
  padding: 12px 14px;
  font-size: 13px;
  color: #c62828;
}
.back-to-home {
  display: block;
  text-align: center;
  margin-top: 24px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px;
  letter-spacing: 0.12em;
  color: var(--muted);
  text-decoration: none;
  transition: color 0.15s;
}
.back-to-home:hover { color: var(--navy); }
.spin {
  width: 16px; height: 16px;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
