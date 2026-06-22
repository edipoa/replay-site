import { ref } from 'vue'
import { groupLogin as apiGroupLogin, groupLogout as apiGroupLogout } from '../api.js'

const GROUP_KEY = 'group_token'
const USER_KEY  = 'user_token'

// ── Auth de grupo (existente) ─────────────────────────────────────────────────

export function useAuth() {
  const token     = ref(localStorage.getItem(GROUP_KEY) ?? '')
  const groupName = ref(localStorage.getItem('group_name') ?? '')
  const loading   = ref(false)
  const error     = ref('')

  async function login(login, password) {
    loading.value = true
    error.value   = ''
    try {
      const data = await apiGroupLogin(login, password)
      token.value     = data.token
      groupName.value = data.group_name
      localStorage.setItem(GROUP_KEY, data.token)
      localStorage.setItem('group_name', data.group_name)
      return { ok: true, subscriptionExpired: !!data.subscription_expired }
    } catch (e) {
      error.value = e.message
      return { ok: false }
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await apiGroupLogout(token.value).catch(() => {})
    token.value     = ''
    groupName.value = ''
    localStorage.removeItem(GROUP_KEY)
    localStorage.removeItem('group_name')
  }

  return { token, groupName, loading, error, login, logout }
}

// ── Auth de usuário (self-service) ────────────────────────────────────────────

export function useUserAuth() {
  const BASE = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')

  const userToken = ref(localStorage.getItem(USER_KEY) ?? '')
  const userName  = ref(localStorage.getItem('user_name')  ?? '')
  const userEmail = ref(localStorage.getItem('user_email') ?? '')
  const loading   = ref(false)
  const error     = ref('')

  function authHeaders() {
    const t = userToken.value || localStorage.getItem(USER_KEY)
    return t ? { Authorization: `Bearer ${t}`, 'Content-Type': 'application/json' } : { 'Content-Type': 'application/json' }
  }

  async function loginWithCredentials(email, password) {
    loading.value = true
    error.value   = ''
    try {
      const res  = await fetch(`${BASE}/api/user/login`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ email, password }),
      })
      const data = await res.json()
      if (!res.ok) { error.value = data.error ?? 'Credenciais inválidas'; return { ok: false } }

      userToken.value  = data.token
      userName.value   = data.name
      userEmail.value  = data.email
      localStorage.setItem(USER_KEY,     data.token)
      localStorage.setItem('user_name',  data.name)
      localStorage.setItem('user_email', data.email)

      return { ok: true, hasActiveGroup: !!data.has_active_group, hasGroup: !!data.has_group }
    } catch {
      error.value = 'Erro de conexão'
      return { ok: false }
    } finally {
      loading.value = false
    }
  }

  async function logoutUser() {
    const t = localStorage.getItem(USER_KEY)
    if (t) {
      await fetch(`${BASE}/api/user/logout`, {
        method:  'POST',
        headers: { Authorization: `Bearer ${t}` },
      }).catch(() => {})
    }
    userToken.value  = ''
    userName.value   = ''
    userEmail.value  = ''
    localStorage.removeItem(USER_KEY)
    localStorage.removeItem('user_name')
    localStorage.removeItem('user_email')
  }

  function isUserLoggedIn() {
    return !!(localStorage.getItem(USER_KEY))
  }

  return { userToken, userName, userEmail, loading, error, loginWithCredentials, logoutUser, isUserLoggedIn, authHeaders }
}
