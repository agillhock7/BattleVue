<template>
  <section class="battle-dashboard">
    <article class="panel col battle-queue">
      <h2 style="margin: 0;">Battle Queue</h2>
      <p class="muted" style="margin: 0;">Queue into async PvP. If a match is ready, submit your snapshot in Match Detail.</p>

      <div class="queue-actions">
        <button @click="queue('casual')" :disabled="queueingMode !== ''">
          {{ queueingMode === 'casual' ? 'Queueing Casual...' : 'Queue Casual' }}
        </button>
        <button class="ghost" @click="queue('ranked')" :disabled="queueingMode !== ''">
          {{ queueingMode === 'ranked' ? 'Queueing Ranked...' : 'Queue Ranked' }}
        </button>
      </div>

      <div class="row" style="align-items: center; justify-content: space-between;">
        <strong>Active Matches</strong>
        <button class="ghost" @click="loadHistory" :disabled="loading">{{ loading ? 'Refreshing...' : 'Refresh' }}</button>
      </div>

      <div v-if="loading" class="muted">Loading matches...</div>
      <div v-else-if="!activeMatches.length" class="muted">No active matches. Queue one to get started.</div>

      <div class="list" v-else>
        <RouterLink class="card match-card active" v-for="match in activeMatches" :key="`active-${match.id}`" :to="`/battle/${match.id}`">
          <div class="row" style="justify-content: space-between; align-items: center;">
            <strong>#{{ match.id }} vs {{ opponentLabel(match) }}</strong>
            <span class="status" :class="statusClass(match.status)">{{ statusLabel(match.status) }}</span>
          </div>
          <div class="muted">Mode: {{ match.mode }} | Submission: {{ match.my_submitted_at ? 'submitted' : 'pending' }}</div>
          <div class="muted">Open match to submit or monitor progress.</div>
        </RouterLink>
      </div>

      <p class="status-line" :class="{ error: hasError }">{{ queueStatus }}</p>
    </article>

    <article class="panel col battle-history">
      <h2 style="margin: 0;">Completed Matches</h2>
      <p class="muted" style="margin: 0;">Browse your recent outcomes and open replay for each completed match.</p>

      <div v-if="loading" class="muted">Loading match history...</div>
      <div v-else-if="!completedMatches.length" class="muted">No completed matches yet.</div>

      <div class="list" v-else>
        <RouterLink class="card match-card" v-for="match in completedMatches" :key="`done-${match.id}`" :to="`/battle/${match.id}`">
          <div class="row" style="justify-content: space-between; align-items: center;">
            <strong>#{{ match.id }} vs {{ opponentLabel(match) }}</strong>
            <span class="result" :class="resultClass(match.my_result)">{{ resultLabel(match.my_result) }}</span>
          </div>
          <div class="muted">Mode: {{ match.mode }} | {{ statusLabel(match.status) }}</div>
          <div class="muted">Completed: {{ formatDate(match.completed_at || match.created_at) }}</div>
        </RouterLink>
      </div>
    </article>
  </section>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { api } from '@/services/api';

type MatchHistoryRow = {
  id: number;
  mode: string;
  status: string;
  my_result: string;
  my_submitted_at?: string | null;
  opponent_display_name?: string | null;
  opponent_username?: string | null;
  completed_at?: string | null;
  created_at?: string | null;
};

const router = useRouter();
const queueStatus = ref('');
const hasError = ref(false);
const queueingMode = ref<'' | 'casual' | 'ranked'>('');
const loading = ref(false);
const matches = ref<MatchHistoryRow[]>([]);

const activeMatches = computed(() =>
  matches.value.filter((match) => !['completed', 'cancelled'].includes(String(match.status || '').toLowerCase()))
);

const completedMatches = computed(() =>
  matches.value.filter((match) => ['completed', 'cancelled'].includes(String(match.status || '').toLowerCase()))
);

onMounted(() => {
  loadHistory();
});

async function queue(mode: 'casual' | 'ranked') {
  queueingMode.value = mode;
  hasError.value = false;
  try {
    const data = await api.post<{ match_id: number; status: string }>(`/matches/queue`, { mode });
    queueStatus.value = `Match #${data.match_id} ${statusLabel(data.status)}.`;
    await loadHistory();
    await router.push(`/battle/${data.match_id}`);
  } catch (error: any) {
    queueStatus.value = error?.message || 'Failed to queue match.';
    hasError.value = true;
  } finally {
    queueingMode.value = '';
  }
}

async function loadHistory() {
  loading.value = true;
  try {
    const data = await api.get<{ matches: MatchHistoryRow[] }>(`/matches/history`);
    matches.value = data.matches;
  } catch (error: any) {
    queueStatus.value = error?.message || 'Failed to load match history.';
    hasError.value = true;
  } finally {
    loading.value = false;
  }
}

function opponentLabel(match: MatchHistoryRow) {
  return match.opponent_display_name || match.opponent_username || 'Waiting for opponent';
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

function resultLabel(result: string) {
  if (result === 'win') {
    return 'Win';
  }
  if (result === 'loss') {
    return 'Loss';
  }
  if (result === 'draw') {
    return 'Draw';
  }
  return 'Pending';
}

function statusClass(status: string) {
  return {
    queued: status === 'queued',
    awaiting: status === 'awaiting_submission',
    simulating: status === 'simulating',
  };
}

function resultClass(result: string) {
  return {
    win: result === 'win',
    loss: result === 'loss',
    draw: result === 'draw',
  };
}

function formatDate(value?: string | null) {
  if (!value) {
    return 'n/a';
  }
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleString();
}
</script>

<style scoped>
.battle-dashboard {
  display: grid;
  grid-template-columns: minmax(320px, 0.9fr) minmax(0, 1.1fr);
  gap: 14px;
}

.battle-queue,
.battle-history {
  min-height: 72vh;
}

.queue-actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.match-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.match-card.active {
  border-color: rgba(56, 189, 248, 0.45);
  background: rgba(14, 27, 49, 0.8);
}

.status,
.result {
  border: 1px solid rgba(142, 166, 203, 0.35);
  border-radius: 999px;
  padding: 3px 9px;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.status.queued {
  border-color: rgba(148, 163, 184, 0.45);
}

.status.awaiting {
  border-color: rgba(56, 189, 248, 0.5);
}

.status.simulating {
  border-color: rgba(251, 191, 36, 0.5);
}

.result.win {
  border-color: rgba(74, 222, 128, 0.6);
  color: #bbf7d0;
}

.result.loss {
  border-color: rgba(248, 113, 113, 0.6);
  color: #fecaca;
}

.result.draw {
  border-color: rgba(250, 204, 21, 0.6);
  color: #fde68a;
}

.status-line {
  margin: 0;
  color: #9fb1cc;
}

.status-line.error {
  color: #fca5a5;
}

@media (max-width: 980px) {
  .battle-dashboard {
    grid-template-columns: 1fr;
  }

  .battle-queue,
  .battle-history {
    min-height: 0;
  }
}
</style>
