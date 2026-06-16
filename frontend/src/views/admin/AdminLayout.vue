<script setup>
import { useRouter, useRoute } from 'vue-router'
import { adminLogout } from '../../api.js'

const router = useRouter()
const route  = useRoute()

async function logout() {
  await adminLogout().catch(() => {})
  localStorage.removeItem('admin_token')
  router.push('/admin/login')
}

function isActive(path) {
  return route.path.startsWith(path)
}
</script>

<template>
  <div class="adm-shell">
    <!-- Sidebar -->
    <aside class="adm-sidebar">
      <div class="adm-logo">
        <span class="display" style="font-size:22px;text-transform:uppercase;letter-spacing:-0.02em">
          RE<span class="gold">PLAY</span>
        </span>
        <span class="adm-tag mono">Admin</span>
      </div>

      <nav class="adm-nav">
        <RouterLink to="/admin/slots"  :class="['adm-link', { active: isActive('/admin/slots') }]">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
          </svg>
          Slots
        </RouterLink>

        <RouterLink to="/admin/groups" :class="['adm-link', { active: isActive('/admin/groups') }]">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
          </svg>
          Grupos
        </RouterLink>

        <RouterLink to="/admin/games"  :class="['adm-link', { active: isActive('/admin/games') }]">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M15 10l4.553-2.069A1 1 0 0 1 21 8.87V15.13a1 1 0 0 1-1.447.9L15 14M3 8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8z"/>
          </svg>
          Jogos
        </RouterLink>
      </nav>

      <button class="adm-logout" @click="logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
        </svg>
        Sair
      </button>
    </aside>

    <!-- Content -->
    <main class="adm-content">
      <RouterView />
    </main>
  </div>
</template>

<style scoped>
.adm-shell {
  display: flex; min-height: 100vh;
  background: var(--paper-warm);
}
.adm-sidebar {
  width: 220px; flex-shrink: 0;
  background: var(--navy-deep);
  display: flex; flex-direction: column;
  padding: 32px 20px;
  position: sticky; top: 0; height: 100vh;
}
.adm-logo {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 40px; padding-bottom: 24px;
  border-bottom: 1px solid rgba(255,255,255,0.08);
}
.adm-tag {
  font-size: 10px; letter-spacing: 0.2em; text-transform: uppercase;
  background: rgba(232,184,66,0.15); color: var(--gold);
  padding: 3px 8px; border-radius: 999px;
}
.adm-nav { display: flex; flex-direction: column; gap: 4px; flex: 1; }
.adm-link {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; border-radius: 10px;
  color: rgba(247,244,237,0.6);
  text-decoration: none;
  font-family: 'Archivo', sans-serif;
  font-size: 14px; font-weight: 600;
  transition: background 0.15s, color 0.15s;
  cursor: pointer;
}
.adm-link svg { width: 18px; height: 18px; flex-shrink: 0; }
.adm-link:hover { background: rgba(255,255,255,0.06); color: var(--paper); }
.adm-link.active { background: rgba(232,184,66,0.12); color: var(--gold); }
.adm-logout {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 14px; border-radius: 10px;
  background: transparent; border: 0; cursor: pointer;
  color: rgba(247,244,237,0.4);
  font-family: 'Archivo', sans-serif;
  font-size: 14px; font-weight: 600;
  transition: background 0.15s, color 0.15s;
  width: 100%;
}
.adm-logout svg { width: 18px; height: 18px; }
.adm-logout:hover { background: rgba(255,59,48,0.1); color: #ff6b6b; }

.adm-content { flex: 1; padding: 40px; overflow-y: auto; }
@media (max-width: 768px) {
  .adm-shell { flex-direction: column; }
  .adm-sidebar { width: 100%; height: auto; position: static; flex-direction: row; flex-wrap: wrap; }
}
</style>
