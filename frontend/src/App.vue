<template>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand">BattleVue</div>
      <nav class="topnav" v-if="auth.user">
        <RouterLink to="/home">Home</RouterLink>
        <RouterLink to="/learn">Learn</RouterLink>
        <RouterLink to="/workshop">Workshop</RouterLink>
        <RouterLink to="/battle">Battle</RouterLink>
        <RouterLink to="/social">Social</RouterLink>
      </nav>
      <div class="actions" v-if="auth.user">
        <button class="ghost" @click="syncNow">Sync</button>
        <RouterLink to="/notifications" class="notification-link">
          Notifications
          <span v-if="notifications.unreadCount" class="badge">{{ notifications.unreadCount }}</span>
        </RouterLink>
        <button class="ghost" @click="logout">Logout</button>
      </div>
    </header>

    <OfflineBanner :online="network.online" :api-healthy="network.apiHealthy" />

    <main :class="['main-wrap', { 'main-wrap--wide': isLearnRoute }]">
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import OfflineBanner from '@/components/OfflineBanner.vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useNetworkStore } from '@/stores/network';
import { flushOutbox } from '@/services/sync';

const auth = useAuthStore();
const notifications = useNotificationsStore();
const network = useNetworkStore();
const route = useRoute();
const router = useRouter();
const isLearnRoute = computed(() => route.path.startsWith('/learn'));

onMounted(async () => {
  network.start();
  await auth.fetchMe();
  if (auth.user) {
    notifications.fetch();
  }
});

async function logout() {
  await auth.logout();
  router.push('/login');
}

async function syncNow() {
  await flushOutbox();
  await notifications.fetch();
}
</script>
