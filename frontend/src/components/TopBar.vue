<script setup>
defineProps({
  brandSub: { type: String, default: 'Campo Sintético' },
  city:     { type: String, default: 'Chapecó/SC' },
  phone:    { type: String, default: '(49) 98434-0535' },
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
      <span class="live-pill">
        <span class="live-dot" />
        CLIPES VÁLIDOS POR 24H
      </span>
    </div>
  </header>
</template>
