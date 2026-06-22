<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'

const router = useRouter()
const route  = useRoute()

const me          = ref(null)
const members     = ref([])
const inviteUrl   = ref('')
const publicUrl   = ref('')
const loadingMe   = ref(true)
const loadingInvite = ref(false)
const toast       = ref('')
const paymentPending = ref(false)
const checkingPayment = ref(false)

const BASE  = (import.meta.env.VITE_API_URL ?? '').replace(/\/$/, '')
const token = () => localStorage.getItem('user_token') ?? ''

function authHeaders() {
  return { Authorization: `Bearer ${token()}`, 'Content-Type': 'application/json' }
}

onMounted(async () => {
  if (!token()) { router.push('/login'); return }

  // Verifica se voltou do checkout do MP
  const subPayment = route.query.sub_payment
  if (subPayment) {
    checkingPayment.value = true
    await pollPayment(subPayment)
    checkingPayment.value = false
  }

  await loadMe()
})

async function loadMe() {
  loadingMe.value = true
  try {
    const res  = await fetch(`${BASE}/api/user/me`, { headers: authHeaders() })
    if (res.status === 401) { router.push('/login'); return }
    const data = await res.json()
    me.value   = data

    if (data.group) {
      await loadMembers()
      await loadPublicLink()
    }
  } catch {
    // silently ignore
  } finally {
    loadingMe.value = false
  }
}

async function loadMembers() {
  const res  = await fetch(`${BASE}/api/user/group/members`, { headers: authHeaders() })
  if (res.ok) members.value = await res.json()
}

async function loadPublicLink() {
  const res  = await fetch(`${BASE}/api/user/group/public-link`, { headers: authHeaders() })
  if (res.ok) {
    const data = await res.json()
    publicUrl.value = data.public_url
    // Sincroniza group_token no localStorage para a GroupVideosView existente
    localStorage.setItem('group_token', data.token)
  }
}

async function generateInvite() {
  loadingInvite.value = true
  try {
    const res  = await fetch(`${BASE}/api/user/group/invite-link`, { headers: authHeaders() })
    const data = await res.json()
    if (res.ok) inviteUrl.value = data.invite_url
  } finally {
    loadingInvite.value = false
  }
}

function copyToClipboard(text, label) {
  navigator.clipboard.writeText(text).then(() => showToast(`${label} copiado!`))
}

function showToast(msg) {
  toast.value = msg
  setTimeout(() => { toast.value = '' }, 2500)
}

async function pollPayment(paymentId) {
  paymentPending.value = true
  for (let i = 0; i < 12; i++) {
    await new Promise(r => setTimeout(r, 3000))
    try {
      const res  = await fetch(`${BASE}/api/payments/sub/${paymentId}/status`)
      const data = await res.json()
      if (data.status === 'approved') { paymentPending.value = false; return }
      if (data.status === 'rejected' || data.status === 'cancelled') break
    } catch { break }
  }
  paymentPending.value = false
}

const statusColor = computed(() => {
  if (!me.value?.group) return ''
  return { active: '#1a7a37', expiring: '#c8991a', expired: '#c62828' }[me.value.group.status] ?? ''
})
const statusLabel = computed(() => {
  if (!me.value?.group) return ''
  return { active: 'Ativa', expiring: 'Expirando', expired: 'Expirada' }[me.value.group.status] ?? ''
})
const isCaptain = computed(() => me.value?.group?.role === 'captain')
</script>

<template>
  <div class="dash-page">
    <div class="dash-inner">

      <!-- Header -->
      <div class="dash-header">
        <div class="dash-brand">
          <span class="display">RE</span><span class="display gold">PLAY</span>
        </div>
        <div class="dash-user" v-if="me">
          <span class="mono" style="font-size:12px;color:var(--muted)">{{ me.email }}</span>
          <button class="btn ghost-btn" style="padding:8px 14px;font-size:12px" @click="router.push('/group')">
            Ver vídeos
          </button>
        </div>
      </div>

      <!-- Pagamento pendente -->
      <div v-if="checkingPayment || paymentPending" class="status-banner pending">
        <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
        Aguardando confirmação do pagamento...
      </div>

      <!-- Loading -->
      <div v-if="loadingMe" class="loading-state">
        <svg class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
        </svg>
      </div>

      <template v-else-if="me">

        <!-- Sem grupo: vai assinar -->
        <div v-if="!me.group" class="no-group-card">
          <p class="display" style="font-size:22px;margin:0 0 8px">Nenhuma assinatura ativa</p>
          <p style="color:var(--muted);margin:0 0 24px;font-size:14px">Escolha um horário e comece a assistir seus replays.</p>
          <button class="btn gold-btn" @click="router.push('/assinar')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
            Assinar agora
          </button>
        </div>

        <template v-else>

          <!-- Status card -->
          <div class="group-card">
            <div class="group-card-top">
              <div>
                <p class="group-name">{{ me.group.name }}</p>
                <p class="group-slot mono">{{ me.group.slot_label }}</p>
              </div>
              <div class="status-pill" :style="{ background: statusColor + '20', color: statusColor, borderColor: statusColor + '40' }">
                {{ statusLabel }}
              </div>
            </div>
            <div class="group-card-meta">
              <div class="meta-item">
                <span class="meta-label">Expira em</span>
                <span class="meta-val">{{ me.group.days_left }} dias</span>
              </div>
              <div class="meta-item">
                <span class="meta-label">Acesso</span>
                <span class="meta-val">{{ me.group.role === 'captain' ? 'Capitão' : 'Jogador' }}</span>
              </div>
            </div>
          </div>

          <!-- Link público -->
          <div class="section-card">
            <div class="section-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              Link público
            </div>
            <p class="section-desc">Qualquer pessoa com este link acessa os vídeos sem precisar criar conta.</p>
            <div v-if="publicUrl" class="link-box">
              <span class="link-text mono">{{ publicUrl }}</span>
              <button class="copy-btn" @click="copyToClipboard(publicUrl, 'Link público')" title="Copiar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>
            </div>
          </div>

          <!-- Convite (só capitão) -->
          <div v-if="isCaptain" class="section-card">
            <div class="section-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
              Convidar jogadores
            </div>
            <p class="section-desc">Os jogadores criam conta própria vinculada ao seu grupo.</p>

            <div v-if="inviteUrl" class="link-box">
              <span class="link-text mono">{{ inviteUrl }}</span>
              <button class="copy-btn" @click="copyToClipboard(inviteUrl, 'Link de convite')" title="Copiar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              </button>
            </div>

            <button
              class="btn ghost-btn"
              style="margin-top:10px"
              :disabled="loadingInvite"
              @click="generateInvite"
            >
              <svg v-if="loadingInvite" class="spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
              </svg>
              <svg v-else viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
              {{ inviteUrl ? 'Gerar novo link' : 'Gerar link de convite' }}
            </button>
          </div>

          <!-- Membros -->
          <div class="section-card" v-if="members.length > 0">
            <div class="section-title">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              Membros ({{ members.length }})
            </div>
            <div class="members-list">
              <div v-for="m in members" :key="m.id" class="member-row">
                <div class="member-avatar">{{ m.name.charAt(0).toUpperCase() }}</div>
                <div class="member-info">
                  <span class="member-name">{{ m.name }}</span>
                  <span class="member-email mono">{{ m.email }}</span>
                </div>
                <span class="role-badge" :class="m.role">{{ m.role === 'captain' ? 'Capitão' : 'Jogador' }}</span>
              </div>
            </div>
          </div>

        </template>
      </template>

      <!-- Toast -->
      <div class="toast" :class="{ show: toast }">{{ toast }}</div>
    </div>
  </div>
</template>

<style scoped>
.dash-page {
  min-height: 100vh;
  background: var(--navy);
  padding: 24px;
}
.dash-inner {
  max-width: 560px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 16px;
}
.dash-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 24px 0 8px;
}
.dash-brand { font-size: 32px; line-height: 1; letter-spacing: -0.03em; text-transform: uppercase; color: var(--paper); }
.dash-brand .gold { color: var(--gold); }
.dash-user { display: flex; align-items: center; gap: 12px; }
.dash-user .mono { color: rgba(247,244,237,0.6) !important; }

.status-banner {
  display: flex; align-items: center; gap: 10px;
  padding: 14px 18px; border-radius: 12px;
  font-size: 13px; font-weight: 600;
}
.status-banner.pending {
  background: rgba(232,184,66,0.15);
  border: 1px solid rgba(232,184,66,0.35);
  color: var(--gold);
}
.loading-state {
  display: flex; justify-content: center; padding: 60px 0;
  color: rgba(247,244,237,0.5);
}
.no-group-card {
  background: var(--paper); border-radius: 16px; padding: 32px;
  text-align: center;
}
.group-card {
  background: var(--paper); border-radius: 16px; padding: 24px;
}
.group-card-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; }
.group-name { font-family: 'Archivo Black', sans-serif; font-size: 22px; color: var(--ink); margin: 0 0 4px; text-transform: uppercase; }
.group-slot { font-size: 12px; letter-spacing: 0.1em; color: var(--muted); }
.status-pill {
  padding: 5px 12px; border-radius: 999px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 10px; letter-spacing: 0.12em; text-transform: uppercase;
  font-weight: 700; border: 1px solid;
}
.group-card-meta { display: flex; gap: 24px; }
.meta-item { display: flex; flex-direction: column; gap: 4px; }
.meta-label { font-family: 'JetBrains Mono', monospace; font-size: 10px; letter-spacing: 0.14em; text-transform: uppercase; color: var(--muted); }
.meta-val { font-family: 'Archivo Black', sans-serif; font-size: 18px; color: var(--ink); }

.section-card {
  background: var(--paper); border-radius: 16px; padding: 24px;
}
.section-title {
  display: flex; align-items: center; gap: 10px;
  font-family: 'Archivo Black', sans-serif; font-size: 14px;
  text-transform: uppercase; letter-spacing: 0.06em; color: var(--ink);
  margin-bottom: 8px;
}
.section-title svg { width: 18px; height: 18px; }
.section-desc { font-size: 13px; color: var(--muted); margin: 0 0 14px; line-height: 1.5; }

.link-box {
  display: flex; align-items: center; gap: 8px;
  background: white; border: 1px solid rgba(11,19,43,0.1);
  border-radius: 10px; padding: 12px 14px;
}
.link-text { font-size: 11px; flex: 1; color: var(--muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.copy-btn {
  appearance: none; border: 0; background: transparent; cursor: pointer;
  color: var(--muted); padding: 4px; border-radius: 6px;
  transition: color 0.15s, background 0.15s;
}
.copy-btn:hover { color: var(--navy); background: rgba(11,19,43,0.06); }
.copy-btn svg { width: 16px; height: 16px; display: block; }

.members-list { display: flex; flex-direction: column; gap: 8px; }
.member-row {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 12px; border-radius: 10px;
  background: white; border: 1px solid rgba(11,19,43,0.06);
}
.member-avatar {
  width: 36px; height: 36px; border-radius: 50%;
  background: var(--navy); color: var(--gold);
  display: grid; place-items: center;
  font-family: 'Archivo Black', sans-serif; font-size: 14px;
  flex-shrink: 0;
}
.member-info { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.member-name { font-size: 13px; font-weight: 700; color: var(--ink); }
.member-email { font-size: 10px; color: var(--muted); letter-spacing: 0.04em; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.role-badge {
  font-family: 'JetBrains Mono', monospace; font-size: 10px;
  letter-spacing: 0.1em; text-transform: uppercase; font-weight: 700;
  padding: 3px 8px; border-radius: 999px;
}
.role-badge.captain { background: rgba(232,184,66,0.15); color: #8A6512; border: 1px solid rgba(232,184,66,0.35); }
.role-badge.player  { background: rgba(11,19,43,0.06); color: var(--muted); border: 1px solid rgba(11,19,43,0.1); }

.toast {
  position: fixed; bottom: 32px; left: 50%;
  transform: translateX(-50%) translateY(20px);
  background: var(--navy-deep); color: var(--paper);
  padding: 12px 20px; border-radius: 999px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px; letter-spacing: 0.1em; text-transform: uppercase;
  box-shadow: 0 12px 32px rgba(7,21,58,0.3);
  opacity: 0; pointer-events: none; transition: all 0.25s; z-index: 200;
}
.toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

.spin { width: 16px; height: 16px; animation: spin 0.8s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }
</style>
