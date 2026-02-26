<template>
  <section class="grid two">
    <article class="panel col">
      <h2>Learning Topics</h2>
      <div class="list">
        <button class="ghost" v-for="topic in topics" :key="topic.id" @click="startSession(topic.id)">
          <strong>{{ topic.title }}</strong>
          <div class="muted">{{ topic.description }}</div>
        </button>
      </div>

      <div class="card col">
        <strong>Create Custom Topic</strong>
        <input v-model="customTitle" placeholder="e.g. Laravel Queues" />
        <textarea v-model="customDescription" rows="4" placeholder="Describe what you want to learn and current skill level"></textarea>
        <button @click="createCustomTopic" :disabled="creatingTopic">{{ creatingTopic ? 'Creating...' : 'Create + Start' }}</button>
      </div>

      <p v-if="error" style="color: #fca5a5">{{ error }}</p>
    </article>

    <article class="panel col">
      <template v-if="sessionState">
        <div class="row" style="justify-content: space-between; align-items: center;">
          <h2 style="margin: 0">{{ sessionState.session.topic_title }}</h2>
          <RouterLink class="ghost" to="/learn">Back to Learn</RouterLink>
        </div>

        <div class="grid two">
          <div class="card">
            <div><strong>Bot Points</strong>: {{ sessionState.session.bot_points }}</div>
            <div><strong>Tier</strong>: {{ sessionState.session.next_checkpoint_tier }}</div>
          </div>
          <div class="card">
            <div><strong>User Tokens</strong>: {{ sessionState.session.cumulative_user_tokens }}</div>
            <div><strong>To Next Checkpoint</strong>: {{ sessionState.session.tokens_to_next_checkpoint }}</div>
          </div>
        </div>

        <div class="chat-window">
          <div v-for="message in sessionState.messages" :key="message.id" :class="['chat-msg', message.role]">
            <div class="role">{{ message.role }}</div>
            <div v-if="message.role === 'assistant'" class="assistant-markdown" v-html="formatAssistant(message.content)"></div>
            <div v-else class="plain-message">{{ message.content }}</div>
          </div>
        </div>

        <div class="card col" v-if="sessionState.session.starter_prompts?.length">
          <strong>Quick Start Prompts</strong>
          <p class="muted" style="margin: 0;">Tap a prompt to begin if you are not sure what to ask.</p>
          <div class="row">
            <button
              v-for="(prompt, idx) in sessionState.session.starter_prompts"
              :key="`${idx}-${prompt}`"
              class="ghost prompt-chip"
              type="button"
              @click="sendQuickPrompt(prompt)"
              :disabled="sendingMessage"
            >
              {{ prompt }}
            </button>
          </div>
        </div>

        <div v-if="sessionState.pending_checkpoint" class="card col">
          <h3 style="margin: 0">Checkpoint Tier {{ sessionState.pending_checkpoint.tier }}</h3>
          <p class="muted" style="margin: 0">{{ sessionState.pending_checkpoint.quiz.instructions }}</p>

          <div v-for="(question, qIdx) in sessionState.pending_checkpoint.quiz.questions" :key="qIdx" class="checkpoint-question">
            <strong>{{ qIdx + 1 }}. {{ question.question }}</strong>
            <label v-for="(choice, cIdx) in question.choices" :key="cIdx" class="row" style="align-items: center;">
              <input type="radio" :name="`q_${qIdx}`" :value="cIdx" v-model.number="checkpointAnswers[qIdx]" />
              <span>{{ choice }}</span>
            </label>
          </div>

          <button @click="submitCheckpoint" :disabled="submittingCheckpoint">{{ submittingCheckpoint ? 'Submitting...' : 'Submit Checkpoint' }}</button>
        </div>

        <div class="row" style="align-items: flex-end;">
          <textarea v-model="draftMessage" rows="3" placeholder="Ask the tutor anything about this topic..."></textarea>
          <button @click="sendMessage" :disabled="sendingMessage">{{ sendingMessage ? 'Sending...' : 'Send' }}</button>
        </div>

        <div v-if="lastCheckpointResult" class="card">
          <strong>{{ lastCheckpointResult.passed ? 'Checkpoint Passed' : 'Checkpoint Not Passed' }}</strong>
          <div>Score: {{ lastCheckpointResult.score_percent }}%</div>
          <div>Awarded Points: {{ lastCheckpointResult.awarded_points }}</div>
        </div>
      </template>

      <template v-else>
        <h2>Start a Learning Session</h2>
        <p class="muted">Pick a topic from the left to begin your guided study conversation.</p>
      </template>
    </article>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api } from '@/services/api';
import { renderAssistantMarkdown } from '@/utils/markdown';

const route = useRoute();
const router = useRouter();

const topics = ref<any[]>([]);
const sessionState = ref<any | null>(null);

const draftMessage = ref('');
const sendingMessage = ref(false);

const customTitle = ref('');
const customDescription = ref('');
const creatingTopic = ref(false);

const checkpointAnswers = ref<number[]>([]);
const submittingCheckpoint = ref(false);
const lastCheckpointResult = ref<any | null>(null);

const error = ref('');

onMounted(async () => {
  await loadTopics();
  await maybeLoadSessionFromRoute();
});

watch(
  () => route.params.id,
  async () => {
    await maybeLoadSessionFromRoute();
  }
);

async function loadTopics() {
  try {
    const data = await api.get<{ topics: any[] }>('/learn/topics');
    topics.value = data.topics;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load topics';
  }
}

async function maybeLoadSessionFromRoute() {
  const id = Number(route.params.id || 0);
  if (!id) {
    return;
  }
  await loadSession(id);
}

async function loadSession(sessionId: number) {
  try {
    const data = await api.get<any>(`/learn/sessions/${sessionId}`);
    sessionState.value = data;
    checkpointAnswers.value = [];
  } catch (e: any) {
    error.value = e?.message || 'Failed to load session';
  }
}

async function startSession(topicId: number) {
  error.value = '';
  try {
    const data = await api.post<any>('/learn/sessions/start', { topic_id: topicId });
    sessionState.value = data;
    checkpointAnswers.value = [];
    await router.push(`/learn/chat/${data.session.id}`);
  } catch (e: any) {
    error.value = e?.message || 'Failed to start session';
  }
}

async function createCustomTopic() {
  creatingTopic.value = true;
  error.value = '';
  try {
    const created = await api.post<{ topic_id: number }>('/learn/topics/custom', {
      title: customTitle.value,
      description: customDescription.value,
    });
    customTitle.value = '';
    customDescription.value = '';
    await loadTopics();
    await startSession(created.topic_id);
  } catch (e: any) {
    error.value = e?.message || 'Failed to create custom topic';
  } finally {
    creatingTopic.value = false;
  }
}

async function sendMessage() {
  return sendMessageWithContent(draftMessage.value);
}

async function sendQuickPrompt(prompt: string) {
  await sendMessageWithContent(prompt);
}

async function sendMessageWithContent(raw: string) {
  const sessionId = Number(sessionState.value?.session?.id || 0);
  if (!sessionId) {
    error.value = 'Start a session first.';
    return;
  }
  const content = raw.trim();
  if (!content) {
    return;
  }

  sendingMessage.value = true;
  error.value = '';
  try {
    const data = await api.post<any>(`/learn/sessions/${sessionId}/message`, {
      message: content,
    });
    draftMessage.value = '';
    sessionState.value = data.state;
    checkpointAnswers.value = [];
  } catch (e: any) {
    error.value = e?.message || 'Failed to send message';
  } finally {
    sendingMessage.value = false;
  }
}

async function submitCheckpoint() {
  const sessionId = Number(sessionState.value?.session?.id || 0);
  if (!sessionId) {
    return;
  }

  submittingCheckpoint.value = true;
  error.value = '';
  try {
    const data = await api.post<any>(`/learn/sessions/${sessionId}/checkpoint/submit`, {
      answers: checkpointAnswers.value,
    });
    lastCheckpointResult.value = {
      passed: data.passed,
      score_percent: data.score_percent,
      awarded_points: data.awarded_points,
    };
    sessionState.value = data.state;
    checkpointAnswers.value = [];
  } catch (e: any) {
    error.value = e?.message || 'Failed to submit checkpoint';
  } finally {
    submittingCheckpoint.value = false;
  }
}

function formatAssistant(content: string) {
  return renderAssistantMarkdown(content);
}
</script>

<style scoped>
.chat-window {
  border: 1px solid rgba(142, 166, 203, 0.3);
  border-radius: 12px;
  background: rgba(7, 15, 31, 0.78);
  padding: 12px;
  max-height: 420px;
  overflow: auto;
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.chat-msg {
  border-radius: 10px;
  padding: 10px;
  border: 1px solid rgba(142, 166, 203, 0.2);
}

.chat-msg.user {
  background: rgba(34, 197, 94, 0.14);
}

.chat-msg.assistant {
  background: rgba(14, 165, 233, 0.14);
}

.plain-message {
  white-space: pre-wrap;
  word-break: break-word;
}

.assistant-markdown :deep(p) {
  margin: 0 0 8px;
  line-height: 1.45;
}

.assistant-markdown :deep(ul),
.assistant-markdown :deep(ol) {
  margin: 6px 0 10px 18px;
  padding: 0;
}

.assistant-markdown :deep(li) {
  margin: 3px 0;
}

.assistant-markdown :deep(code) {
  background: rgba(2, 11, 26, 0.8);
  border: 1px solid rgba(142, 166, 203, 0.25);
  border-radius: 6px;
  padding: 1px 5px;
  font-family: 'JetBrains Mono', monospace;
  font-size: 12px;
}

.assistant-markdown :deep(pre) {
  background: rgba(2, 11, 26, 0.92);
  border: 1px solid rgba(142, 166, 203, 0.25);
  border-radius: 8px;
  padding: 10px;
  overflow: auto;
  margin: 8px 0 10px;
}

.assistant-markdown :deep(pre code) {
  border: 0;
  background: transparent;
  padding: 0;
  font-size: 12px;
}

.role {
  font-size: 12px;
  text-transform: uppercase;
  color: #9fb1cc;
  margin-bottom: 4px;
}

.checkpoint-question {
  border: 1px solid rgba(142, 166, 203, 0.3);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.prompt-chip {
  text-align: left;
  white-space: normal;
}
</style>
