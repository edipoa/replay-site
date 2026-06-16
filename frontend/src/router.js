import { createRouter, createWebHistory } from 'vue-router'
import ListView         from './views/ListView.vue'
import PlayerView       from './views/PlayerView.vue'
import LoginView        from './views/LoginView.vue'
import GroupVideosView  from './views/GroupVideosView.vue'
import GameView         from './views/GameView.vue'
import DisplayView      from './views/DisplayView.vue'
import AdminLoginView   from './views/admin/AdminLoginView.vue'
import AdminLayout      from './views/admin/AdminLayout.vue'
import AdminSlotsView   from './views/admin/AdminSlotsView.vue'
import AdminGroupsView  from './views/admin/AdminGroupsView.vue'
import AdminGamesView   from './views/admin/AdminGamesView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    // Públicas
    { path: '/',           component: ListView },
    { path: '/player/:id', component: PlayerView },
    { path: '/display',    component: DisplayView },
    { path: '/jogo/:qrToken', component: GameView },

    // Grupo (login)
    { path: '/login',  component: LoginView },
    { path: '/group',  component: GroupVideosView },

    // Admin
    { path: '/admin/login', component: AdminLoginView },
    {
      path: '/admin',
      component: AdminLayout,
      redirect: '/admin/slots',
      children: [
        { path: 'slots',  component: AdminSlotsView },
        { path: 'groups', component: AdminGroupsView },
        { path: 'games',  component: AdminGamesView },
      ],
    },
  ],
})
