<script setup>
import { computed } from 'vue'

defineProps({
  brandSub: { type: String, default: 'Campo Sintético' },
  city:     { type: String, default: 'Chapecó/SC' },
  phone:    { type: String, default: '(49) 98434-0535' },
})

const userLoggedIn  = computed(() => !!localStorage.getItem('user_token'))
const groupLoggedIn = computed(() => !!localStorage.getItem('group_token'))
const destLink      = computed(() => {
  if (userLoggedIn.value)  return '/grupo'
  if (groupLoggedIn.value) return '/group'
  return '/login'
})
const destLabel = computed(() => {
  if (userLoggedIn.value || groupLoggedIn.value) return 'Meus replays'
  return 'Entrar'
})
</script>

<template>
  <header class="topbar">
    <div class="topbar-inner">
      <RouterLink to="/" class="brand">
        <img src="/assets/logo.png" alt="Logo" class="brand-logo" />
        <div class="brand-text">
          <div class="brand-name">CAMPO SOCIETY<span class="accent">·</span>VIANA</div>
          <div class="brand-sub">{{ brandSub }}</div>
        </div>
      </RouterLink>
      <div class="topbar-spacer" />
      <slot />
      <span class="top-contact">{{ city }}&nbsp;&nbsp;·&nbsp;&nbsp;{{ phone }}</span>
      <RouterLink :to="destLink" class="topbar-cta">{{ destLabel }}</RouterLink>
      <span class="live-pill">
        <span class="live-dot" />
        <span class="live-label">CLIPES VÁLIDOS POR 24H</span>
      </span>
    </div>
  </header>
</template>
