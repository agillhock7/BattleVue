<template>
  <section class="col" style="gap: 14px;">
    <article class="panel col">
      <h2>Match #{{ matchId }}</h2>
      <div class="row">
        <label>
          Blueprint
          <select v-model.number="selectedBlueprintId">
            <option :value="0">Select blueprint</option>
            <option v-for="bp in blueprints" :key="bp.id" :value="bp.id">{{ bp.name }}</option>
          </select>
        </label>
        <label>
          Ruleset
          <select v-model.number="selectedRulesetId">
            <option :value="0">Select ruleset</option>
            <option v-for="rs in rulesets" :key="rs.id" :value="rs.id">{{ rs.name }}</option>
          </select>
        </label>
        <button @click="submitLoadout">Submit Snapshot</button>
      </div>
      <div class="muted">{{ status }}</div>
    </article>

    <ReplayViewer v-if="replay" :replay="replay" />
    <MatchChat :match-id="matchId" />
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import ReplayViewer from '@/components/ReplayViewer.vue';
import MatchChat from '@/components/MatchChat.vue';
import { api } from '@/services/api';
import { cacheReplay, enqueueOutbox, getCachedReplay } from '@/services/idb';

const route = useRoute();
const matchId = Number(route.params.id);

const blueprints = ref<any[]>([]);
const rulesets = ref<any[]>([]);
const selectedBlueprintId = ref(0);
const selectedRulesetId = ref(0);
const replay = ref<any>(null);
const status = ref('');

onMounted(async () => {
  await Promise.all([loadBlueprints(), loadRulesets(), loadReplay()]);
});

async function loadBlueprints() {
  const data = await api.get<{ blueprints: any[] }>('/blueprints/list');
  blueprints.value = data.blueprints;
}

async function loadRulesets() {
  const data = await api.get<{ rulesets: any[] }>('/rulesets/list');
  rulesets.value = data.rulesets;
}

async function submitLoadout() {
  const payload = { blueprint_id: selectedBlueprintId.value, ruleset_id: selectedRulesetId.value };
  try {
    const data = await api.post<{ simulated: boolean }>(`/matches/${matchId}/submit`, payload);
    status.value = data.simulated ? 'Both players submitted. Simulation complete.' : 'Snapshot submitted. Waiting for opponent.';
    await loadReplay();
  } catch {
    await enqueueOutbox({ endpoint: `/matches/${matchId}/submit`, method: 'POST', payload });
    status.value = 'Offline: submission queued for sync.';
  }
}

async function loadReplay() {
  try {
    const data = await api.get<any>(`/matches/${matchId}/replay`);
    replay.value = data;
    await cacheReplay(matchId, data);
  } catch {
    const cached = await getCachedReplay(matchId);
    replay.value = cached?.replay ?? null;
  }
}
</script>
