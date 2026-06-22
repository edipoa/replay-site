<script setup>
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { recoverAccess } from '../api.js'

const route  = useRoute()
const router = useRouter()
const error  = ref(false)

onMounted(async () => {
  try {
    const data = await recoverAccess(route.params.token)

    if (data.type === 'full') {
      localStorage.setItem(`game_token_${data.qr_token}`, data.token)
    } else {
      Object.entries(data.tokens).forEach(([clipId, tok]) => {
        localStorage.setItem(`video_token_${clipId}`, tok)
      })
    }

    router.replace(`/jogo/${data.qr_token}`)
  } catch {
    error.value = true
  }
})
</script>

<template>
  <div class="recover-wrap">
    <div v-if="!error" class="recover-card">
      <div class="recover-spinner"></div>
      <p class="recover-msg">Restaurando seu acesso…</p>
    </div>
    <div v-else class="recover-card">
      <div class="recover-icon">⚠</div>
      <h2 class="recover-title">Link inválido ou expirado</h2>
      <p class="recover-sub">Os clipes ficam disponíveis por 24h após a compra. Após esse prazo os vídeos são removidos automaticamente.</p>
      <a href="/" class="recover-btn">← Voltar ao início</a>
    </div>
  </div>
</template>

<style scoped>
.recover-wrap {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--cream, #f5f0e8);
  padding: 24px;
}
.recover-card {
  background: white;
  border-radius: 16px;
  padding: 48px 32px;
  max-width: 400px;
  width: 100%;
  text-align: center;
  box-shadow: 0 4px 32px rgba(11,19,43,0.08);
}
.recover-spinner {
  width: 40px; height: 40px;
  border: 3px solid rgba(11,19,43,0.12);
  border-top-color: var(--navy, #0b132b);
  border-radius: 50%;
  margin: 0 auto 20px;
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.recover-msg {
  font-family: 'JetBrains Mono', monospace;
  font-size: 13px;
  color: var(--muted, #6b7280);
  letter-spacing: .04em;
}
.recover-icon {
  font-size: 36px;
  margin-bottom: 16px;
}
.recover-title {
  font-family: 'Archivo Black', sans-serif;
  font-size: 20px;
  color: var(--navy, #0b132b);
  text-transform: uppercase;
  margin: 0 0 12px;
}
.recover-sub {
  font-size: 14px;
  color: var(--muted, #6b7280);
  line-height: 1.6;
  margin: 0 0 28px;
}
.recover-btn {
  display: inline-block;
  background: var(--navy, #0b132b);
  color: white;
  text-decoration: none;
  padding: 12px 24px;
  border-radius: 8px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
  letter-spacing: .05em;
}
</style>
