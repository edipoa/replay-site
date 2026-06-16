<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { adminLogin } from '../../api.js'

const router   = useRouter()
const login    = ref('')
const password = ref('')
const loading  = ref(false)
const error    = ref('')

async function submit() {
  loading.value = true
  error.value   = ''
  try {
    const data = await adminLogin(login.value, password.value)
    localStorage.setItem('admin_token', data.token)
    router.push('/admin/slots')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="al-page">
    <div class="al-card">
      <div class="al-brand">
        <span class="display">RE<span class="gold">PLAY</span></span>
        <span class="al-tag mono">Admin</span>
      </div>

      <form @submit.prevent="submit" class="al-form">
        <label>
          <span class="field-label">Login</span>
          <input v-model="login" type="text" autocomplete="username" required :disabled="loading" />
        </label>
        <label>
          <span class="field-label">Senha</span>
          <input v-model="password" type="password" autocomplete="current-password" required :disabled="loading" />
        </label>

        <div v-if="error" class="al-error">{{ error }}</div>

        <button type="submit" class="btn gold-btn" :disabled="loading" style="width:100%;justify-content:center">
          {{ loading ? 'Entrando...' : 'Entrar' }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
.al-page {
  min-height: 100vh; background: var(--navy);
  display: grid; place-items: center; padding: 24px;
}
.al-card {
  background: var(--paper); border-radius: 20px;
  padding: 48px 40px; width: 100%; max-width: 380px;
  box-shadow: 0 24px 80px rgba(7,21,58,0.4);
}
.al-brand { display: flex; align-items: center; gap: 14px; margin-bottom: 36px; }
.al-brand .display { font-size: 36px; letter-spacing: -0.03em; text-transform: uppercase; }
.al-tag {
  font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase;
  background: var(--navy); color: var(--gold);
  padding: 4px 10px; border-radius: 999px;
}
.al-form { display: flex; flex-direction: column; gap: 18px; }
.al-form label { display: flex; flex-direction: column; gap: 8px; }
.field-label {
  font-family: 'JetBrains Mono', monospace;
  font-size: 11px; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted);
}
.al-form input {
  border: 1px solid rgba(11,19,43,0.14); border-radius: 10px;
  padding: 13px 16px; font-family: 'Archivo', sans-serif;
  font-size: 15px; color: var(--ink); background: white;
  transition: outline 0.15s;
}
.al-form input:focus { outline: 2px solid var(--gold); outline-offset: -1px; border-color: transparent; }
.al-error {
  background: rgba(255,59,48,0.1); border: 1px solid rgba(255,59,48,0.3);
  border-radius: 8px; padding: 11px 14px; font-size: 13px; color: #c62828;
}
</style>
