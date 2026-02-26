<template>
  <section class="grid two">
    <article class="panel col">
      <h2>Queue</h2>
      <div class="row">
        <button @click="queue('casual')">Queue Casual</button>
        <button class="ghost" @click="queue('ranked')">Queue Ranked</button>
      </div>
      <p class="muted">{{ queueStatus }}</p>
    </article>

    <article class="panel col">
      <h2>Match History</h2>
      <div class="list">
        <RouterLink class="card" v-for="match in matches" :key="match.id" :to="`/battle/${match.id}`">
          <strong>#{{ match.id }} vs {{ match.opponent_display_name || match.opponent_username }}</strong>
          <div class="muted">{{ match.mode }} | {{ match.status }} | {{ match.my_result }}</div>
        </RouterLink>
      </div>
    </article>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/services/api';

const queueStatus = ref('');
const matches = ref<any[]>([]);

onMounted(() => {
  loadHistory();
});

async function queue(mode: 'casual' | 'ranked') {
  const data = await api.post<{ match_id: number; status: string }>('/matches/queue', { mode });
  queueStatus.value = `Match #${data.match_id} ${data.status}`;
  await loadHistory();
}

async function loadHistory() {
  const data = await api.get<{ matches: any[] }>('/matches/history');
  matches.value = data.matches;
}
</script>
