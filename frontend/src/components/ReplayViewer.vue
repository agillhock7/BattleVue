<template>
  <div class="panel col">
    <h3>Replay Viewer</h3>
    <div class="row">
      <button @click="togglePlay">{{ playing ? 'Pause' : 'Play' }}</button>
      <span class="muted">Tick {{ currentTick }} / {{ maxTick }}</span>
    </div>
    <input type="range" min="0" :max="maxTick" v-model.number="currentTick" />

    <div class="arena">
      <div class="bot-card">
        <strong>{{ leftPlayer?.display_name || 'Player A' }}</strong>
        <div>HP: {{ state.aHp }}</div>
        <div>Lane: {{ state.aLane }}</div>
      </div>
      <div class="lanes">
        <div v-for="lane in ['left', 'mid', 'right']" :key="lane" class="lane" :class="laneClass(lane)">
          {{ lane }}
        </div>
      </div>
      <div class="bot-card">
        <strong>{{ rightPlayer?.display_name || 'Player B' }}</strong>
        <div>HP: {{ state.bHp }}</div>
        <div>Lane: {{ state.bLane }}</div>
      </div>
    </div>

    <div class="list">
      <div v-for="event in visibleEvents" :key="event.id" class="card">
        Tick {{ event.tick }} - {{ event.event_type }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ replay: any }>();

const currentTick = ref(0);
const playing = ref(false);
let timer: number | null = null;

const maxTick = computed(() => {
  const events = props.replay?.events ?? [];
  return events.reduce((max: number, event: any) => Math.max(max, Number(event.tick || 0)), 0);
});

const leftPlayer = computed(() => props.replay?.players?.find((p: any) => Number(p.slot_order) === 0));
const rightPlayer = computed(() => props.replay?.players?.find((p: any) => Number(p.slot_order) === 1));

const state = computed(() => {
  const events = (props.replay?.events ?? []).filter((event: any) => Number(event.tick) <= currentTick.value);
  let aHp = 100;
  let bHp = 100;
  let aLane = 'mid';
  let bLane = 'mid';

  for (const event of events) {
    if (event.event_type === 'state') {
      aHp = event.payload?.a_hp ?? aHp;
      bHp = event.payload?.b_hp ?? bHp;
      aLane = event.payload?.a_lane ?? aLane;
      bLane = event.payload?.b_lane ?? bLane;
    }
  }

  return { aHp, bHp, aLane, bLane };
});

const visibleEvents = computed(() => {
  const events = props.replay?.events ?? [];
  return events.filter((event: any) => Number(event.tick) <= currentTick.value).slice(-8);
});

watch(playing, (isPlaying) => {
  if (isPlaying) {
    timer = window.setInterval(() => {
      if (currentTick.value >= maxTick.value) {
        playing.value = false;
      } else {
        currentTick.value += 1;
      }
    }, 180);
  } else if (timer) {
    clearInterval(timer);
    timer = null;
  }
});

onBeforeUnmount(() => {
  if (timer) {
    clearInterval(timer);
  }
});

function togglePlay() {
  playing.value = !playing.value;
}

function laneClass(lane: string) {
  return {
    activeA: state.value.aLane === lane,
    activeB: state.value.bLane === lane
  };
}
</script>

<style scoped>
.arena {
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  gap: 12px;
  align-items: center;
}

.lanes {
  display: flex;
  flex-direction: column;
  gap: 8px;
  min-width: 90px;
}

.lane {
  border: 1px solid rgba(142, 166, 203, 0.3);
  border-radius: 10px;
  padding: 10px;
  text-align: center;
}

.lane.activeA {
  background: rgba(74, 222, 128, 0.2);
}

.lane.activeB {
  box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.9);
}

.bot-card {
  border: 1px solid rgba(142, 166, 203, 0.3);
  border-radius: 10px;
  padding: 10px;
}
</style>
