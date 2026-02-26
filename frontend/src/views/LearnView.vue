<template>
  <section class="col" style="gap: 12px;">
    <article class="panel col">
      <h2>AI Learning Quests</h2>
      <p class="muted">Study any topic through guided tutor chat, pass token checkpoints, and earn bot points.</p>
      <RouterLink class="card" to="/learn/chat">
        <strong>Open Learn Chat Quest</strong>
        <div class="muted">Token-based checkpoints + tiered rewards</div>
      </RouterLink>
    </article>

    <div class="grid two">
      <article class="panel col">
        <h2>Tracks</h2>
        <div class="list">
          <button
            v-for="track in tracks"
            :key="track.id"
            class="ghost"
            style="text-align: left;"
            @click="selectTrack(track.slug)"
          >
            <strong>{{ track.title }}</strong>
            <div class="muted">{{ track.description }}</div>
          </button>
        </div>
      </article>

      <article class="panel col">
        <h2>Classic Quests</h2>
        <div class="list">
          <RouterLink class="card" v-for="quest in quests" :key="quest.id" :to="`/learn/quest/${quest.id}`">
            <strong>{{ quest.title }}</strong>
            <div class="muted">Track: {{ quest.track_slug }} | {{ quest.difficulty }}</div>
          </RouterLink>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/services/api';

const tracks = ref<any[]>([]);
const quests = ref<any[]>([]);
const selectedTrack = ref<string>('');

onMounted(async () => {
  await Promise.all([loadTracks(), loadQuests()]);
});

async function loadTracks() {
  const data = await api.get<{ tracks: any[] }>('/tracks');
  tracks.value = data.tracks;
}

async function loadQuests() {
  const query = selectedTrack.value ? `?track=${encodeURIComponent(selectedTrack.value)}` : '';
  const data = await api.get<{ quests: any[] }>(`/quests${query}`);
  quests.value = data.quests;
}

async function selectTrack(slug: string) {
  selectedTrack.value = slug;
  await loadQuests();
}
</script>
