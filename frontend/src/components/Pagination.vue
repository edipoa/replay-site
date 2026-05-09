<script setup>
const props = defineProps({
  page: { type: Number, required: true },
  totalPages: { type: Number, required: true },
})
const emit = defineEmits(['change'])

function go(p) {
  if (p >= 1 && p <= props.totalPages) emit('change', p)
}
</script>

<template>
  <div v-if="totalPages > 1" class="pagination">
    <button class="page-btn arrow" :disabled="page === 1" @click="go(page - 1)">← Anterior</button>
    <button
      v-for="p in totalPages"
      :key="p"
      class="page-btn"
      :class="{ active: p === page }"
      @click="go(p)"
    >
      {{ p }}
    </button>
    <button class="page-btn arrow" :disabled="page === totalPages" @click="go(page + 1)">Próximo →</button>
  </div>
</template>
