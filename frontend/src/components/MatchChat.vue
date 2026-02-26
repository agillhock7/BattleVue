<template>
  <div class="panel col">
    <h3>Match Chat</h3>
    <div class="list chat-list">
      <div v-for="message in messages" :key="message.id" class="card">
        <strong>{{ message.display_name || message.username }}</strong>
        <div>{{ message.message }}</div>
      </div>
    </div>

    <div class="row">
      <input v-model="draft" placeholder="Message (max 280)" @keyup.enter="send" />
      <button @click="send">Send</button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { api } from '@/services/api';

const props = defineProps<{ matchId: number }>();

const messages = ref<any[]>([]);
const draft = ref('');
let timer: number | null = null;
let afterId = 0;

async function poll() {
  const data = await api.get<{ messages: any[] }>(`/matches/${props.matchId}/messages?after_id=${afterId}`);
  if (data.messages.length > 0) {
    messages.value.push(...data.messages);
    afterId = Number(messages.value[messages.value.length - 1]?.id || afterId);
  }
}

async function send() {
  const message = draft.value.trim();
  if (!message) {
    return;
  }
  await api.post(`/matches/${props.matchId}/messages`, { message });
  draft.value = '';
  await poll();
}

onMounted(async () => {
  await poll();
  timer = window.setInterval(() => {
    poll().catch(() => undefined);
  }, 3000);
});

onBeforeUnmount(() => {
  if (timer) {
    clearInterval(timer);
  }
});
</script>

<style scoped>
.chat-list {
  max-height: 260px;
  overflow: auto;
}
</style>
