<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route  = useRoute()

const inviteToken = route.params.token
const groupInfo   = ref(null)
const notFound    = ref(false)
const loading     = ref(true)
const joining     = ref(false)
const error       = ref('')

const name     = ref('')
const email    = ref('')
const password = ref('')

const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

onMounted(async () => {
  try {
    const res  = await fetch(`${BASE}/api/invites/${inviteToken}`)
    if (res.status === 404) { notFound.value = true; return }
    if (!res.ok) throw new Error()
    groupInfo.value = await res.json()
  } catch {
    notFound.value = true
  } finally {
    loading.value = false
  }
})

async function join() {
  error.value  = ''
  joining.value = true
  try {
    const res  = await fetch(`${BASE}/api/invites/${inviteToken}`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ name: name.value, email: email.value, password: password.value }),
    })
    const data = await res.json()
    if (!res.ok) { error.value = data.error ?? 'Erro ao entrar no grupo'; return }

    localStorage.setItem('user_token', data.token)
    localStorage.setItem('user_name',  data.name ?? name.value)
    router.push('/group')
  } catch {
    error.value = 'Erro de conexão'
  } finally {
    joining.value = false
  }
}
</script>

<template>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-brand">
        <span class="display">RE</span><span class="display gold">PLAY</span>
      </div>

      <div v-if="loading" style="text-align:center;padding:24px 0;color:var(--muted)">
        <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
      </div>

      <div v-else-if="notFound" style="text-align:center;padding:24px 0">
        <p class="auth-sub" style="margin-bottom:8px">Convite inválido</p>
        <p style="color:var(--muted);font-size:14px;margin:0 0 24px">Este link expirou ou já foi utilizado.</p>
        <router-link to="/" class="btn gold-btn" style="display:inline-flex">Ir para o início</router-link>
      </div>

      <template v-else-if="groupInfo">
        <p class="auth-sub">Você foi convidado</p>
        <div class="invite-group-info">
          <span class="mono" style="font-size:11px;color:var(--muted)">Grupo</span>
          <span class="invite-group-name">{{ groupInfo.group_name }}</span>
          <span class="mono" style="font-size:11px;color:var(--muted)">{{ groupInfo.slot_label }}</span>
        </div>

        <form @submit.prevent="join" class="auth-form">
          <label>
            <span class="field-label">Seu nome</span>
            <input v-model="name" type="text" autocomplete="name" required :disabled="joining" />
          </label>
          <label>
            <span class="field-label">E-mail</span>
            <input v-model="email" type="email" autocomplete="email" required :disabled="joining" />
          </label>
          <label>
            <span class="field-label">Senha</span>
            <input v-model="password" type="password" autocomplete="new-password" minlength="6" required :disabled="joining" />
          </label>

          <div v-if="error" class="auth-error">{{ error }}</div>

          <button type="submit" class="btn gold-btn" :disabled="joining" style="width:100%;justify-content:center">
            <svg v-if="joining" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
            </svg>
            {{ joining ? 'Entrando...' : 'Entrar no grupo' }}
          </button>
        </form>

        <p class="auth-footer-links">
          Já tem conta?
          <router-link to="/login">Entrar</router-link>
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.auth-page {
  min-height: 100vh; background: var(--navy);
  display: grid; place-items: center; padding: 24px;
}
.auth-card {
  background: var(--paper); border-radius: 20px; padding: 48px 40px;
  width: 100%; max-width: 420px;
  box-shadow: 0 24px 80px rgba(7,21,58,0.4);
}
.auth-brand { font-size: 40px; line-height: 1; letter-spacing: -0.03em; text-transform: uppercase; margin-bottom: 4px; }
.auth-sub {
  font-family: 'JetBrains Mono', monospace; font-size: 12px;
  letter-spacing: 0.18em; text-transform: uppercase; color: var(--muted); margin: 0 0 24px;
}
.invite-group-info {
  display: flex; flex-direction: column; gap: 4px;
  background: white; border: 1px solid rgba(11,19,43,0.1);
  border-radius: 12px; padding: 16px 18px; margin-bottom: 28px;
}
.invite-group-name { font-family: 'Archivo Black', sans-serif; font-size: 18px; color: var(--ink); text-transform: uppercase; }
.auth-form { display: flex; flex-direction: column; gap: 18px; }
.auth-form label { display: flex; flex-direction: column; gap: 6px; }
.field-label {
  font-family: 'JetBrains Mono', monospace; font-size: 11px;
  letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted);
}
.auth-form input {
  border: 1px solid rgba(11,19,43,0.14); border-radius: 10px; padding: 14px 16px;
  font-family: 'Archivo', sans-serif; font-size: 15px; color: var(--ink);
  background: white; transition: border-color 0.15s, outline 0.15s;
}
.auth-form input:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.auth-error { background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3); border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #c62828; }
.auth-footer-links {
  text-align: center; margin-top: 20px;
  font-family: 'JetBrains Mono', monospace; font-size: 11px; letter-spacing: 0.1em; color: var(--muted);
}
.auth-footer-links a { color: var(--navy); font-weight: 700; text-decoration: none; margin-left: 4px; }
.auth-footer-links a:hover { color: var(--gold); }
.spin { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
@media (max-width: 480px) { .auth-card { padding: 36px 20px; } }
</style>
