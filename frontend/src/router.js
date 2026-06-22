import { createRouter, createWebHistory } from 'vue-router'
import ListView              from './views/ListView.vue'
import PlayerView            from './views/PlayerView.vue'
import LoginView             from './views/LoginView.vue'
import RegisterView          from './views/RegisterView.vue'
import ResetPasswordView     from './views/ResetPasswordView.vue'
import SubscribeView         from './views/SubscribeView.vue'
import GroupDashboardView    from './views/GroupDashboardView.vue'
import InviteView            from './views/InviteView.vue'
import GroupVideosView       from './views/GroupVideosView.vue'
import GameView              from './views/GameView.vue'
import DisplayView           from './views/DisplayView.vue'
import ShareView             from './views/ShareView.vue'
import RecoverView           from './views/RecoverView.vue'
import AdminLoginView          from './views/admin/AdminLoginView.vue'
import AdminLayout             from './views/admin/AdminLayout.vue'
import AdminDashboardView      from './views/admin/AdminDashboardView.vue'
import AdminSlotsView          from './views/admin/AdminSlotsView.vue'
import AdminGroupsView         from './views/admin/AdminGroupsView.vue'
import AdminGamesView          from './views/admin/AdminGamesView.vue'
import AdminOrphanedClipsView  from './views/admin/AdminOrphanedClipsView.vue'

export default createRouter({
  history: createWebHistory(),
  routes: [
    // Públicas
    { path: '/',               component: ListView },
    { path: '/player/:id',     component: PlayerView },
    { path: '/display',        component: DisplayView },
    { path: '/jogo/:qrToken',  component: GameView },
    { path: '/share/:token',   component: ShareView },
    { path: '/recuperar/:token', component: RecoverView },

    // Self-service auth
    { path: '/login',            component: LoginView },
    { path: '/cadastro',         component: RegisterView },
    { path: '/redefinir-senha',  component: ResetPasswordView },
    { path: '/assinar',          component: SubscribeView },
    { path: '/grupo',            component: GroupDashboardView },
    { path: '/convite/:token',   component: InviteView },

    // Grupo (acesso direto via group_token — mantido para compatibilidade)
    { path: '/group', component: GroupVideosView },

    // Admin
    { path: '/admin/login', component: AdminLoginView },
    {
      path: '/admin',
      component: AdminLayout,
      redirect: '/admin/dashboard',
      children: [
        { path: 'dashboard',       component: AdminDashboardView },
        { path: 'slots',           component: AdminSlotsView },
        { path: 'groups',          component: AdminGroupsView },
        { path: 'games',           component: AdminGamesView },
        { path: 'orphaned-clips',  component: AdminOrphanedClipsView },
      ],
    },
  ],
})
