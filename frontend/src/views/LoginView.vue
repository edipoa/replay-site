<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth, useUserAuth } from '../composables/useAuth.js'

const router = useRouter()
const { login, loading: loadingGroup, error: errorGroup } = useAuth()
const { loginWithCredentials, loading: loadingUser, error: errorUser } = useUserAuth()

const tab      = ref('user')   // 'user' | 'group'
const loginField = ref('')
const password   = ref('')
const email      = ref('')
const passwordU  = ref('')

// Forgot password
const forgotMode  = ref(false)
const forgotEmail = ref('')
const forgotDone  = ref(false)
const forgotLoading = ref(false)

const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

async function submitGroup() {
  const result = await login(loginField.value, password.value)
  if (result.ok) {
    if (result.subscriptionExpired) { router.push('/group?renew=1'); return }
    const dest = localStorage.getItem('after_login')
    localStorage.removeItem('after_login')
    router.push(dest || '/group')
  }
}

async function submitUser() {
  const result = await loginWithCredentials(email.value, passwordU.value)
  if (result.ok) {
    if (!result.hasGroup) { router.push('/assinar'); return }
    if (!result.hasActiveGroup) { router.push('/grupo'); return }
    router.push('/grupo')
  }
}

async function sendForgot() {
  forgotLoading.value = true
  try {
    await fetch(`${BASE}/api/user/forgot-password`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email: forgotEmail.value }),
    })
    forgotDone.value = true
  } finally {
    forgotLoading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-brand">
        <span class="display">RE</span><span class="display gold">PLAY</span>
      </div>

      <!-- Tabs -->
      <div class="tabs">
        <button class="tab-btn" :class="{ active: tab === 'user' }"  @click="tab = 'user'; forgotMode = false">Minha conta</button>
        <button class="tab-btn" :class="{ active: tab === 'group' }" @click="tab = 'group'; forgotMode = false">Acesso do time</button>
      </div>

      <!-- Tab: Minha conta (self-service) -->
      <template v-if="tab === 'user'">
        <template v-if="forgotMode">
          <p class="login-sub">Recuperar senha</p>

          <div v-if="forgotDone" class="auth-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            Se o e-mail estiver cadastrado, você receberá um link em breve.
          </div>

          <form v-else @submit.prevent="sendForgot" class="login-form">
            <label>
              <span class="field-label">E-mail</span>
              <input v-model="forgotEmail" type="email" autocomplete="email" required :disabled="forgotLoading" />
            </label>
            <button type="submit" class="btn gold-btn" :disabled="forgotLoading" style="width:100%;justify-content:center">
              <svg v-if="forgotLoading" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              {{ forgotLoading ? 'Enviando...' : 'Enviar link' }}
            </button>
          </form>

          <button class="back-to-home" @click="forgotMode = false">← Voltar ao login</button>
        </template>

        <template v-else>
          <p class="login-sub">Acesso do capitão e jogadores</p>
          <form @submit.prevent="submitUser" class="login-form">
            <label>
              <span class="field-label">E-mail</span>
              <input v-model="email" type="email" autocomplete="email" required :disabled="loadingUser" />
            </label>
            <label>
              <span class="field-label">Senha</span>
              <input v-model="passwordU" type="password" autocomplete="current-password" required :disabled="loadingUser" />
            </label>
            <div v-if="errorUser" class="login-error">{{ errorUser }}</div>
            <button type="submit" class="btn gold-btn" :disabled="loadingUser" style="width:100%;justify-content:center">
              <svg v-if="loadingUser" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              {{ loadingUser ? 'Entrando...' : 'Entrar' }}
            </button>
          </form>

          <div class="login-footer-links">
            <button class="link-btn" @click="forgotMode = true">Esqueci minha senha</button>
            <span class="sep">·</span>
            <router-link to="/cadastro" class="link-btn">Criar conta</router-link>
          </div>
        </template>
      </template>

      <!-- Tab: Acesso do time (legado, login/senha compartilhado) -->
      <template v-if="tab === 'group'">
        <p class="login-sub">Login do time</p>
        <form @submit.prevent="submitGroup" class="login-form">
          <label>
            <span class="field-label">Login</span>
            <input v-model="loginField" type="text" autocomplete="username" required :disabled="loadingGroup" />
          </label>
          <label>
            <span class="field-label">Senha</span>
            <input v-model="password" type="password" autocomplete="current-password" required :disabled="loadingGroup" />
          </label>
          <div v-if="errorGroup" class="login-error">{{ errorGroup }}</div>
          <button type="submit" class="btn gold-btn" :disabled="loadingGroup" style="width:100%;justify-content:center">
            <svg v-if="loadingGroup" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            {{ loadingGroup ? 'Entrando...' : 'Entrar' }}
          </button>
        </form>
      </template>

      <a href="/" class="back-to-home">← Voltar ao início</a>
    </div>
  </div>
</template>

<style scoped>
.login-page {
  min-height: 100vh; background: var(--navy);
  display: grid; place-items: center; padding: 24px;
}
.login-card {
  background: var(--paper); border-radius: 20px; padding: 48px 40px;
  width: 100%; max-width: 400px;
  box-shadow: 0 24px 80px rgba(7,21,58,0.4);
}
.login-brand { font-size: 40px; line-height: 1; letter-spacing: -0.03em; text-transform: uppercase; margin-bottom: 20px; }
.tabs { display: flex; background: white; border: 1px solid rgba(11,19,43,0.1); border-radius: 10px; padding: 4px; gap: 4px; margin-bottom: 24px; }
.tab-btn {
  flex: 1; appearance: none; border: 0; background: transparent;
  padding: 9px 12px; border-radius: 7px; cursor: pointer;
  font-family: 'Archivo', sans-serif; font-size: 13px; font-weight: 600;
  color: var(--muted); transition: all 0.15s;
}
.tab-btn.active { background: var(--navy); color: var(--paper); }
.tab-btn:hover:not(.active) { background: rgba(11,19,43,0.05); color: var(--ink); }
.login-sub {
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted);
  margin: 0 0 24px;
}
.login-form { display: flex; flex-direction: column; gap: 18px; }
.login-form label { display: flex; flex-direction: column; gap: 8px; }
.field-label {
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted);
}
.login-form input {
  border: 1px solid rgba(11,19,43,0.14); border-radius: 10px;
  padding: 14px 16px; font-family: 'Archivo', sans-serif; font-size: 15px;
  color: var(--ink); background: white; transition: border-color 0.15s, outline 0.15s;
}
.login-form input:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.login-error {
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #c62828;
}
.auth-success {
  display: flex; align-items: flex-start; gap: 10px;
  background: rgba(52,199,89,0.12); border: 1px solid rgba(52,199,89,0.35);
  border-radius: 12px; padding: 16px 18px; margin-bottom: 16px;
  color: #1a7a37; font-size: 13px; line-height: 1.5;
}
.auth-success svg { width: 18px; height: 18px; color: #34c759; flex-shrink: 0; margin-top: 1px; }
.login-footer-links {
  display: flex; align-items: center; justify-content: center;
  gap: 8px; margin-top: 16px;
}
.link-btn {
  appearance: none; border: 0; background: transparent; cursor: pointer; padding: 0;
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.1em;
  color: var(--muted); text-decoration: none;
  transition: color 0.15s;
}
.link-btn:hover { color: var(--navy); }
.sep { color: var(--muted); font-size: 11px; }
.back-to-home {
  display: block; text-align: center; margin-top: 24px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.12em;
  color: var(--muted); text-decoration: none; transition: color 0.15s;
}
.back-to-home:hover { color: var(--navy); }
.spin { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 480px) { .login-card { padding: 36px 24px; } }
</style>
