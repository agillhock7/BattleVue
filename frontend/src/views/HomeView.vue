<template>
  <section class="grid two">
    <article class="panel col">
      <h2>Command Center</h2>
      <p class="muted">Track your progress, upgrade your bot, and queue your next async PvP match.</p>
      <div class="row">
        <RouterLink class="card" to="/learn">Continue Learning</RouterLink>
        <RouterLink class="card" to="/workshop">Open Workshop</RouterLink>
        <RouterLink class="card" to="/battle">Queue Match</RouterLink>
      </div>
    </article>

    <article class="panel col">
      <h3>Current Quests</h3>
      <div class="list">
        <RouterLink class="card" v-for="quest in quests.slice(0, 4)" :key="quest.id" :to="`/learn/quest/${quest.id}`">
          <strong>{{ quest.title }}</strong>
          <span class="muted">{{ quest.difficulty }}</span>
        </RouterLink>
      </div>
    </article>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/services/api';

const quests = ref<any[]>([]);

onMounted(async () => {
  try {
    const data = await api.get<{ quests: any[] }>('/quests');
    quests.value = data.quests;
  } catch {
    quests.value = [];
  }
});
</script>
