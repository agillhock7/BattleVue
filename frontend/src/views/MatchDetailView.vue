<template>
  <section class="col" style="gap: 14px;">
    <article class="panel col">
      <div class="row" style="justify-content: space-between; align-items: center;">
        <h2 style="margin: 0;">Match #{{ matchId }}</h2>
        <div class="row" style="align-items: center;">
          <span v-if="matchState" class="status-pill" :class="statusClass(matchState.match.status)">
            {{ statusLabel(matchState.match.status) }}
          </span>
          <button class="ghost" @click="refresh" :disabled="loading">{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
        </div>
      </div>

      <div v-if="loading && !matchState" class="muted">Loading match...</div>
      <p v-if="errorText" class="error-line">{{ errorText }}</p>

      <template v-if="matchState">
        <div class="card col">
          <div class="row" style="justify-content: space-between; align-items: center;">
            <strong>Players</strong>
            <span class="muted">Mode: {{ matchState.match.mode }}</span>
          </div>

          <div class="list">
            <div class="player-row" v-for="player in matchState.players" :key="`${player.user_id}-${player.slot_order}`">
              <div>
                <strong>{{ player.display_name || player.username }}</strong>
                <div class="muted">Slot {{ player.slot_order + 1 }} | {{ player.result }}</div>
              </div>
              <span class="submit-pill" :class="{ done: !!player.submitted_at }">
                {{ player.submitted_at ? 'submitted' : 'pending' }}
              </span>
            </div>
          </div>
        </div>

        <div class="card col" v-if="matchState.can_submit">
          <strong>Submit Snapshot</strong>
          <p class="muted" style="margin: 0;">
            Choose your saved blueprint and AI profile. Once both players submit, simulation starts automatically.
          </p>

          <div class="grid two">
            <label>
              Blueprint
              <select v-model.number="selectedBlueprintId">
                <option :value="0">Select blueprint</option>
                <option v-for="bp in blueprints" :key="bp.id" :value="bp.id">{{ bp.name }}</option>
              </select>
            </label>
            <label>
              AI Profile
              <select v-model.number="selectedRulesetId">
                <option :value="0">Select AI profile</option>
                <option v-for="rs in rulesets" :key="rs.id" :value="rs.id">{{ rs.name }}</option>
              </select>
            </label>
          </div>

          <button @click="submitLoadout" :disabled="submittingLoadout || !canSubmitLoadout">
            {{ submittingLoadout ? 'Submitting...' : 'Submit Snapshot' }}
          </button>
        </div>

        <div class="card" v-else>
          <strong>Submission Status</strong>
          <div class="muted" v-if="matchState.match.status === 'completed'">Match completed. Replay is available below.</div>
          <div class="muted" v-else-if="matchState.self.submitted_at">Your snapshot is submitted. Waiting for the opponent and simulation.</div>
          <div class="muted" v-else>Waiting for match readiness.</div>
        </div>
      </template>

      <div class="muted" v-if="statusText">{{ statusText }}</div>
    </article>

    <ReplayViewer v-if="replay" :replay="replay" />
    <article class="panel" v-else-if="matchState?.match.status === 'completed'">
      <div class="muted">Replay not available yet. Try refreshing.</div>
    </article>

    <MatchChat v-if="matchState" :match-id="matchId" />
  </section>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import ReplayViewer from '@/components/ReplayViewer.vue';
import MatchChat from '@/components/MatchChat.vue';
import { api } from '@/services/api';
import { cacheReplay, enqueueOutbox, getCachedReplay } from '@/services/idb';

type MatchDetail = {
  match: {
    id: number;
    mode: string;
    status: string;
    created_at: string;
    completed_at?: string | null;
  };
  self: {
    blueprint_id?: number | null;
    ruleset_id?: number | null;
    submitted_at?: string | null;
  };
  players: Array<{
    user_id: number;
    slot_order: number;
    username: string;
    display_name?: string | null;
    submitted_at?: string | null;
    result: string;
  }>;
  can_submit: boolean;
  can_replay: boolean;
};

const route = useRoute();
const matchId = Number(route.params.id);

const blueprints = ref<any[]>([]);
const rulesets = ref<any[]>([]);
const selectedBlueprintId = ref(0);
const selectedRulesetId = ref(0);
const replay = ref<any>(null);
const matchState = ref<MatchDetail | null>(null);

const loading = ref(false);
const submittingLoadout = ref(false);
const statusText = ref('');
const errorText = ref('');

let pollTimer: number | null = null;

const canSubmitLoadout = computed(() => selectedBlueprintId.value > 0 && selectedRulesetId.value > 0);

onMounted(async () => {
  await Promise.all([loadBlueprints(), loadRulesets()]);
  await refresh();
  startPolling();
});

onBeforeUnmount(() => {
  stopPolling();
});

async function loadBlueprints() {
  const data = await api.get<{ blueprints: any[] }>('/blueprints/list');
  blueprints.value = data.blueprints;
}

async function loadRulesets() {
  const data = await api.get<{ rulesets: any[] }>('/rulesets/list');
  rulesets.value = data.rulesets;
}

async function refresh() {
  loading.value = true;
  errorText.value = '';
  try {
    const detail = await api.get<MatchDetail>(`/matches/${matchId}`);
    matchState.value = detail;

    if (!selectedBlueprintId.value && detail.self.blueprint_id) {
      selectedBlueprintId.value = Number(detail.self.blueprint_id);
    }
    if (!selectedRulesetId.value && detail.self.ruleset_id) {
      selectedRulesetId.value = Number(detail.self.ruleset_id);
    }

    if (detail.can_replay) {
      await loadReplay();
    }
  } catch (error: any) {
    errorText.value = error?.message || 'Failed to load match.';
  } finally {
    loading.value = false;
  }
}

async function submitLoadout() {
  if (!canSubmitLoadout.value) {
    statusText.value = 'Select a blueprint and AI profile first.';
    return;
  }

  const payload = { blueprint_id: selectedBlueprintId.value, ruleset_id: selectedRulesetId.value };
  submittingLoadout.value = true;
  errorText.value = '';

  try {
    const data = await api.post<{ simulated: boolean }>(`/matches/${matchId}/submit`, payload);
    statusText.value = data.simulated
      ? 'Both players submitted. Simulation complete.'
      : 'Snapshot submitted. Waiting for opponent.';
    await refresh();
  } catch {
    await enqueueOutbox({ endpoint: `/matches/${matchId}/submit`, method: 'POST', payload });
    statusText.value = 'Offline: submission queued for sync.';
  } finally {
    submittingLoadout.value = false;
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

function startPolling() {
  stopPolling();
  pollTimer = window.setInterval(() => {
    if (!matchState.value) {
      return;
    }
    if (matchState.value.match.status === 'completed' || matchState.value.match.status === 'cancelled') {
      stopPolling();
      return;
    }
    refresh().catch(() => undefined);
  }, 5000);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
}

function statusLabel(status: string) {
  const map: Record<string, string> = {
    queued: 'queued',
    awaiting_submission: 'awaiting submission',
    simulating: 'simulating',
    completed: 'completed',
    cancelled: 'cancelled',
  };
  return map[status] || status;
}

function statusClass(status: string) {
  return {
    queued: status === 'queued',
    awaiting: status === 'awaiting_submission',
    simulating: status === 'simulating',
    completed: status === 'completed',
    cancelled: status === 'cancelled',
  };
}
</script>

<style scoped>
.status-pill {
  border: 1px solid rgba(142, 166, 203, 0.35);
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status-pill.queued {
  border-color: rgba(148, 163, 184, 0.45);
}

.status-pill.awaiting {
  border-color: rgba(56, 189, 248, 0.55);
}

.status-pill.simulating {
  border-color: rgba(251, 191, 36, 0.55);
}

.status-pill.completed {
  border-color: rgba(74, 222, 128, 0.55);
}

.status-pill.cancelled {
  border-color: rgba(248, 113, 113, 0.55);
}

.player-row {
  border: 1px solid rgba(142, 166, 203, 0.24);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
}

.submit-pill {
  border: 1px solid rgba(148, 163, 184, 0.45);
  border-radius: 999px;
  padding: 2px 8px;
  font-size: 11px;
  text-transform: uppercase;
}

.submit-pill.done {
  border-color: rgba(74, 222, 128, 0.55);
  color: #bbf7d0;
}

.error-line {
  margin: 0;
  color: #fca5a5;
}
</style>
