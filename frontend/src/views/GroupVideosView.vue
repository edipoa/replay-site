<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import TopBar    from '../components/TopBar.vue'
import VideoCard from '../components/VideoCard.vue'
import Pagination from '../components/Pagination.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchGroupVideos } from '../api.js'
import { useAuth } from '../composables/useAuth.js'

const PAGE_SIZE = 12
const router    = useRouter()
const { token, groupName, logout } = useAuth()

const videos  = ref([])
const loading = ref(false)
const error   = ref(null)
const page    = ref(1)

async function load() {
  if (!token.value) { router.push('/login'); return }
  loading.value = true
  error.value   = null
  try {
    videos.value = await fetchGroupVideos(token.value)
  } catch (e) {
    if (e.message === 'UNAUTHORIZED') { router.push('/login'); return }
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function handleLogout() {
  await logout()
  router.push('/login')
}

const totalPages = computed(() => Math.max(1, Math.ceil(videos.value.length / PAGE_SIZE)))
const pageVideos = computed(() => {
  const start = (page.value - 1) * PAGE_SIZE
  return videos.value.slice(start, start + PAGE_SIZE)
})

function goPage(p) { page.value = p; window.scrollTo({ top: 0, behavior: 'smooth' }) }

onMounted(load)
</script>

<template>
  <div class="page-layout">
    <TopBar />

    <section class="hero">
      <div class="hero-inner">
        <div>
          <div class="hero-eyebrow"><span class="bar" />Meu time</div>
          <h1>
            SEUS<br />
            <span class="gold">LANCES.</span><br />
            <span class="outline">SEU TIME.</span>
          </h1>
        </div>
        <div class="hero-side">
          <p class="hero-tag">
            Bem-vindo, <strong>{{ groupName }}</strong>.<br />
            Estes são os replays do seu horário fixo.
          </p>
          <button class="btn ghost-btn" style="width:fit-content" @click="handleLogout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>
            </svg>
            Sair
          </button>
        </div>
      </div>
    </section>

    <main class="main-wrap">
      <div class="grid">
        <template v-if="loading">
          <div v-for="i in 8" :key="i" class="clip" style="pointer-events:none">
            <div class="thumb-wrap">
              <div class="thumb thumb-grass" style="opacity:.35" />
            </div>
          </div>
        </template>

        <div v-else-if="error" class="empty">
          <div class="display">Erro ao carregar</div>
          <div>{{ error }}</div>
        </div>

        <div v-else-if="pageVideos.length === 0" class="empty">
          <div class="display">Nenhum clipe ativo</div>
          <div>Os lances ficam disponíveis por 24h após a gravação.</div>
        </div>

        <VideoCard v-else v-for="v in pageVideos" :key="v.id" :video="v" />
      </div>

      <Pagination :page="page" :total-pages="totalPages" @change="goPage" />
    </main>

    <AppFooter />
  </div>
</template>
