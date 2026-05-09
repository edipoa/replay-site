<script setup>
const props = defineProps({
  modelValue: {
    type: Object,
    required: true,
  },
})
const emit = defineEmits(['update:modelValue'])

function set(patch) {
  emit('update:modelValue', { ...props.modelValue, ...patch })
}

function setPeriod(period) {
  set({ period, date: '' })
}

function setDate(e) {
  set({ date: e.target.value })
}

function setSort(sort) {
  set({ sort })
}

const PERIODS = [
  { label: 'Todos (24h)', value: '24h' },
  { label: '12h', value: '12h' },
  { label: '6h', value: '6h' },
  { label: '1h', value: '1h' },
]
</script>

<template>
  <div class="flex items-center gap-8 px-6 py-4">
    <!-- Período -->
    <div class="flex items-center gap-2">
      <span class="text-[10px] font-semibold tracking-widest text-gray-400 uppercase mr-1">Período</span>
      <button
        v-for="p in PERIODS"
        :key="p.value"
        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors"
        :class="modelValue.date === '' && modelValue.period === p.value
          ? 'bg-navy text-white'
          : 'bg-white text-gray-700 border border-gray-200 hover:border-gray-400'"
        @click="setPeriod(p.value)"
      >
        {{ p.label }}
      </button>
    </div>

    <!-- Data -->
    <div class="flex items-center gap-2">
      <span class="text-[10px] font-semibold tracking-widest text-gray-400 uppercase mr-1">Data</span>
      <label class="relative flex items-center bg-white border border-gray-200 rounded-full px-4 py-1.5 gap-2 cursor-pointer hover:border-gray-400 transition-colors">
        <span class="text-sm text-gray-500 min-w-[90px]">
          {{ modelValue.date
            ? new Date(modelValue.date + 'T12:00:00').toLocaleDateString('pt-BR')
            : 'dd/mm/aaaa' }}
        </span>
        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8" y1="2" x2="8" y2="6"/>
          <line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <input
          type="date"
          class="absolute inset-0 opacity-0 cursor-pointer w-full"
          :value="modelValue.date"
          @change="setDate"
        />
      </label>
    </div>

    <!-- Ordenar -->
    <div class="flex items-center gap-2">
      <span class="text-[10px] font-semibold tracking-widest text-gray-400 uppercase mr-1">Ordenar</span>
      <button
        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors"
        :class="modelValue.sort === 'recent'
          ? 'bg-navy text-white'
          : 'bg-white text-gray-700 border border-gray-200 hover:border-gray-400'"
        @click="setSort('recent')"
      >
        Recentes
      </button>
      <button
        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-colors"
        :class="modelValue.sort === 'expiring'
          ? 'bg-navy text-white'
          : 'bg-white text-gray-700 border border-gray-200 hover:border-gray-400'"
        @click="setSort('expiring')"
      >
        Expirando
      </button>
    </div>
  </div>

  <div class="border-b border-gray-200 mx-6" />
</template>
