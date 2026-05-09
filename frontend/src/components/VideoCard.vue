<script setup>
import { computed } from 'vue'
import { clockTime, formatDuration, shortDate, relativeTime, timeRemaining, thumbClass } from '../utils.js'
import { thumbnailUrl } from '../api.js'

const props = defineProps({
  video: { type: Object, required: true },
})

const rem = computed(() => timeRemaining(props.video.expires_at))

const expClass = computed(() => {
  if (rem.value.urgency >= 0.92) return 'urgent'
  if (rem.value.urgency >= 0.75) return 'warn'
  return ''
})
</script>

<template>
  <RouterLink :to="`/player/${video.id}`" class="clip">
    <div class="thumb-wrap">
      <div class="thumb" :class="video.has_thumbnail ? '' : thumbClass(video.seq)">
        <img
          v-if="video.has_thumbnail"
          :src="thumbnailUrl(video.id)"
          loading="lazy"
          alt=""
        />
        <div class="thumb-overlay" />
        <div class="play-pill">
          <svg viewBox="0 0 24 24" fill="currentColor">
            <path d="M8 5v14l11-7z" />
          </svg>
        </div>
        <div class="thumb-time">{{ clockTime(video.triggered_at) }}</div>
        <div class="thumb-br">{{ formatDuration(video.duration_s) }}</div>
      </div>
    </div>

    <div class="clip-meta-bottom">
      <div class="clip-num-row">
        <div class="clip-num">{{ video.display_id.toUpperCase() }}</div>
        <span class="exp-badge" :class="expClass">
          <span class="exp-dot" />{{ rem.text }}
        </span>
      </div>
      <h3 class="clip-title">Clipe das {{ clockTime(video.triggered_at) }}</h3>
      <div class="clip-meta">
        <span>{{ relativeTime(video.triggered_at) }}</span>
        <span class="dot" />
        <span>{{ shortDate(video.triggered_at) }}</span>
        <span class="dot" />
        <span>{{ formatDuration(video.duration_s) }}</span>
      </div>
    </div>
  </RouterLink>
</template>
