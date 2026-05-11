<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import TopBar from '../components/TopBar.vue'
import VideoCard from '../components/VideoCard.vue'
import Pagination from '../components/Pagination.vue'
import AppFooter from '../components/AppFooter.vue'
import { fetchVideos } from '../api.js'

const PAGE_SIZE = 12

const period     = ref('all')
const sort       = ref('recent')
const dateFilter = ref('')
const page       = ref(1)
const videos     = ref([])
const loading    = ref(false)
const error      = ref(null)

async function load() {
  loading.value = true
  error.value   = null
  try {
    videos.value = await fetchVideos({
      period: period.value === 'all' ? '24h' : period.value,
      date:   dateFilter.value,
      sort:   sort.value,
    })
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

const totalPages = computed(() => Math.max(1, Math.ceil(videos.value.length / PAGE_SIZE)))
const pageVideos = computed(() => {
  const start = (page.value - 1) * PAGE_SIZE
  return videos.value.slice(start, start + PAGE_SIZE)
})

function setPeriod(p) { period.value = p; page.value = 1 }
function setSort(s)   { sort.value = s; page.value = 1 }
function onDate(e)    { dateFilter.value = e.target.value; page.value = 1 }
function goPage(p)    { page.value = p; window.scrollTo({ top: 0, behavior: 'smooth' }) }

watch([period, sort, dateFilter], load)
onMounted(load)
</script>

<template>
  <div class="page-layout">
    <TopBar />

    <!-- Hero -->
    <section class="hero">
      <div class="hero-inner">
        <div>
          <div class="hero-eyebrow"><span class="bar" />Os lances do dia</div>
          <h1>
            OS LANCES<br />
            <span class="gold">DO CAMPO.</span><br />
            <span class="outline">NA SUA MÃO.</span>
          </h1>
        </div>
        <div class="hero-side">
          <p class="hero-tag">
            <strong>Cada gol, defesa e jogada</strong> filmados ao vivo no campo.
            <strong style="color:var(--gold)">Os clipes ficam no ar por 24 horas</strong>
            — baixe ou compartilhe no Zap antes que expire.
          </p>
          <div class="hero-warn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            </svg>
            <div><strong>Janela de 24h</strong> · Após esse prazo o vídeo é removido automaticamente.</div>
          </div>
        </div>
      </div>
    </section>

    <!-- Controls (sticky) -->
    <div class="controls-wrap">
      <div class="controls">
        <div class="ctrl-group">
          <span class="ctrl-label">Período</span>
          <div class="seg">
            <button :class="{ active: period === 'all' }" @click="setPeriod('all')">Todos (24h)</button>
            <button :class="{ active: period === '12h' }" @click="setPeriod('12h')">12h</button>
            <button :class="{ active: period === '6h' }"  @click="setPeriod('6h')">6h</button>
            <button :class="{ active: period === '1h' }"  @click="setPeriod('1h')">1h</button>
          </div>
        </div>
        <div class="ctrl-group">
          <span class="ctrl-label">Data</span>
          <input type="date" class="date-input" :value="dateFilter" @change="onDate" />
        </div>
        <div class="ctrl-group">
          <span class="ctrl-label">Ordenar</span>
          <div class="seg">
            <button :class="{ active: sort === 'recent' }"   @click="setSort('recent')">Recentes</button>
            <button :class="{ active: sort === 'expiring' }" @click="setSort('expiring')">Expirando</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Grid -->
    <main class="main-wrap">
      <div class="grid">
        <!-- Skeleton loading -->
        <template v-if="loading">
          <div v-for="i in 8" :key="i" class="clip" style="pointer-events:none">
            <div class="thumb-wrap">
              <div class="thumb thumb-grass" style="opacity:.35" />
            </div>
          </div>
        </template>

        <!-- Error -->
        <div v-else-if="error" class="empty">
          <div class="display">Erro ao carregar</div>
          <div>{{ error }}</div>
        </div>

        <!-- Empty -->
        <div v-else-if="pageVideos.length === 0" class="empty">
          <div class="display">Nenhum clipe ativo</div>
          <div>Os lances ficam disponíveis por 24h após a gravação.</div>
        </div>

        <!-- Cards -->
        <VideoCard
          v-else
          v-for="video in pageVideos"
          :key="video.id"
          :video="video"
        />
      </div>

      <Pagination :page="page" :total-pages="totalPages" @change="goPage" />
    </main>

    <AppFooter />
  </div>
</template>
