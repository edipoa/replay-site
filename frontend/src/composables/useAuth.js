import { ref } from 'vue'
import { groupLogin as apiLogin, groupLogout as apiLogout } from '../api.js'

const KEY = 'group_token'

export function useAuth() {
  const token     = ref(localStorage.getItem(KEY) ?? '')
  const groupName = ref(localStorage.getItem('group_name') ?? '')
  const loading   = ref(false)
  const error     = ref('')

  async function login(login, password) {
    loading.value = true
    error.value   = ''
    try {
      const data = await apiLogin(login, password)
      token.value     = data.token
      groupName.value = data.group_name
      localStorage.setItem(KEY, data.token)
      localStorage.setItem('group_name', data.group_name)
      return true
    } catch (e) {
      error.value = e.message
      return false
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    await apiLogout(token.value).catch(() => {})
    token.value     = ''
    groupName.value = ''
    localStorage.removeItem(KEY)
    localStorage.removeItem('group_name')
  }

  return { token, groupName, loading, error, login, logout }
}
