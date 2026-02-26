import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/home' },
    { path: '/login', component: () => import('@/views/LoginView.vue'), meta: { public: true } },
    { path: '/register', component: () => import('@/views/RegisterView.vue'), meta: { public: true } },
    { path: '/home', component: () => import('@/views/HomeView.vue') },
    { path: '/learn', component: () => import('@/views/LearnView.vue') },
    { path: '/learn/quest/:id', component: () => import('@/views/QuestDetailView.vue') },
    { path: '/workshop', component: () => import('@/views/WorkshopView.vue') },
    { path: '/battle', component: () => import('@/views/BattleView.vue') },
    { path: '/battle/:id', component: () => import('@/views/MatchDetailView.vue') },
    { path: '/social', component: () => import('@/views/SocialView.vue') },
    { path: '/profile/:id', component: () => import('@/views/ProfileView.vue') },
    { path: '/notifications', component: () => import('@/views/NotificationsView.vue') },
    { path: '/:pathMatch(.*)*', component: () => import('@/views/NotFoundView.vue') }
  ]
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  if (!auth.ready) {
    await auth.fetchMe();
  }

  if (to.meta.public) {
    if (auth.user && (to.path === '/login' || to.path === '/register')) {
      return '/home';
    }
    return true;
  }

  if (!auth.user) {
    return '/login';
  }
  return true;
});

export default router;
