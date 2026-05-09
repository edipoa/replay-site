import { createRouter, createWebHistory } from 'vue-router'
import ListView from './views/ListView.vue'
import PlayerView from './views/PlayerView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/',           component: ListView },
    { path: '/player/:id', component: PlayerView },
  ],
})
