<template>
  <section class="panel col">
    <h2>{{ quest?.title || 'Quest' }}</h2>
    <p class="muted">{{ quest?.description }}</p>

    <div v-if="quest" class="list">
      <article class="card col" v-for="step in quest.steps" :key="step.id">
        <strong>Step {{ step.step_index }} - {{ step.step_type }}</strong>
        <pre style="white-space: pre-wrap; margin: 0">{{ pretty(step.payload) }}</pre>

        <div class="row">
          <input v-if="step.step_type === 'quiz'" v-model="answers[step.step_index]" placeholder="Enter answer" />
          <textarea v-else-if="step.step_type === 'snippet'" v-model="answers[step.step_index]" placeholder="Paste snippet"></textarea>
          <label v-else-if="step.step_type === 'checklist'" class="row">
            <input type="checkbox" v-model="checks[step.step_index]" />
            Mark checklist done
          </label>
          <button @click="submitStep(step)">Submit Step</button>
        </div>
      </article>
    </div>

    <div class="row">
      <button @click="complete">Complete Quest</button>
      <span class="muted">{{ status }}</span>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { api } from '@/services/api';
import { cacheQuest, enqueueOutbox, getCachedQuest } from '@/services/idb';

const route = useRoute();
const quest = ref<any>(null);
const status = ref('');
const answers = ref<Record<number, string>>({});
const checks = ref<Record<number, boolean>>({});

onMounted(async () => {
  await loadQuest();
});

async function loadQuest() {
  const id = Number(route.params.id);
  try {
    const data = await api.get<{ quest: any }>(`/quest/${id}`);
    quest.value = data.quest;
    await cacheQuest({ id, ...data.quest });
  } catch {
    const cached = await getCachedQuest(id);
    quest.value = cached || null;
    status.value = cached ? 'Loaded from offline cache.' : 'Quest unavailable offline.';
  }
}

function pretty(obj: any) {
  return JSON.stringify(obj, null, 2);
}

async function submitStep(step: any) {
  const id = Number(route.params.id);
  const submission: any = {};
  if (step.step_type === 'quiz') {
    submission.answer = answers.value[step.step_index] || '';
  } else if (step.step_type === 'snippet') {
    submission.code = answers.value[step.step_index] || '';
  } else if (step.step_type === 'checklist') {
    submission.checked = !!checks.value[step.step_index];
  }

  const payload = { step_index: step.step_index, submission };

  try {
    const data = await api.post<{ status: string }>(`/quest/${id}/submit-step`, payload);
    status.value = `Step saved (${data.status})`;
  } catch {
    await enqueueOutbox({ endpoint: `/quest/${id}/submit-step`, method: 'POST', payload });
    status.value = 'Offline: step queued for sync.';
  }
}

async function complete() {
  const id = Number(route.params.id);
  try {
    await api.post(`/quest/${id}/complete`);
    status.value = 'Quest completed.';
  } catch {
    await enqueueOutbox({ endpoint: `/quest/${id}/complete`, method: 'POST', payload: {} });
    status.value = 'Offline: completion queued for sync.';
  }
}
</script>
