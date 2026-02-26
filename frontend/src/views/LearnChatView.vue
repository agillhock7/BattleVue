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

        <div class="card col checkpoint-overview">
          <div class="row checkpoint-head">
            <div class="row checkpoint-head-left">
              <span class="tier-pill">Tier {{ sessionState.session.next_checkpoint_tier }}</span>
              <span class="muted">Last cleared: Tier {{ sessionState.session.last_checkpoint_tier }}</span>
            </div>
            <span class="points-pill">Bot Points {{ sessionState.session.bot_points }}</span>
          </div>

          <div class="tier-track">
            <div v-for="tier in tierPreview" :key="tier.tier" :class="['tier-node', tier.state]">
              Tier {{ tier.tier }}
            </div>
          </div>

          <div class="col progress-stack">
            <div class="col" style="gap: 6px;">
              <div class="row progress-row">
                <strong>Checkpoint Progress (User Tokens)</strong>
                <span>{{ sessionState.session.tier_progress_user_tokens }} / {{ sessionState.session.tier_progress_target_tokens }}</span>
              </div>
              <div class="progress-bar">
                <span :style="{ width: `${sessionState.session.tier_progress_percent || 0}%` }"></span>
              </div>
              <div class="muted progress-meta">
                {{ sessionState.session.tokens_to_next_checkpoint }} user tokens and {{ sessionState.session.turns_to_next_checkpoint }} turns to next checkpoint.
              </div>
            </div>

            <div class="col" style="gap: 6px;">
              <div class="row progress-row">
                <strong>Session Budget</strong>
                <span>{{ sessionState.session.cumulative_total_tokens }} / {{ sessionState.session.session_max_total_tokens }}</span>
              </div>
              <div class="progress-bar session">
                <span :style="{ width: `${sessionState.session.session_progress_percent || 0}%` }"></span>
              </div>
              <div class="muted progress-meta">{{ sessionState.session.tokens_to_session_completion }} total tokens remaining in this thread.</div>
            </div>
          </div>
        </div>

        <div class="chat-window" ref="chatWindowRef">
          <div v-for="message in sessionState.messages" :key="message.id" :class="['chat-msg', message.role]">
            <div class="role">{{ message.role }}</div>
            <div v-if="message.role === 'assistant'" class="assistant-markdown" v-html="formatAssistant(message.content)"></div>
            <div v-else class="plain-message">{{ message.content }}</div>
          </div>
        </div>

        <div class="card col" v-if="sessionState.session.starter_prompts?.length && sessionState.messages.length <= 2">
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

        <div class="card col" v-if="sessionState.session.suggested_prompts?.length && !isSessionCompleted && sessionState.messages.length > 0">
          <strong>Suggested Next Inputs</strong>
          <p class="muted" style="margin: 0;">These are generated from your current direction. Tap one to continue.</p>
          <div class="row">
            <button
              v-for="(prompt, idx) in sessionState.session.suggested_prompts"
              :key="`suggest-${idx}-${prompt}`"
              class="ghost prompt-chip"
              type="button"
              @click="sendQuickPrompt(prompt)"
              :disabled="sendingMessage"
            >
              {{ prompt }}
            </button>
          </div>
        </div>

        <div class="card col checkpoint-banner" v-if="sessionState.pending_checkpoint">
          <div class="row checkpoint-banner-head">
            <div class="col checkpoint-banner-copy">
              <strong>Checkpoint Ready: Tier {{ sessionState.pending_checkpoint.tier }}</strong>
              <p class="muted" style="margin: 0;">Quiz stays in-thread so you can keep the conversation context visible while answering.</p>
            </div>
            <button class="ghost" type="button" @click="showCheckpointPanel = !showCheckpointPanel">
              {{ showCheckpointPanel ? 'Hide Quiz' : 'Open Quiz' }}
            </button>
          </div>

          <div v-if="showCheckpointPanel" class="col checkpoint-inline">
            <p class="muted" style="margin: 0;">{{ sessionState.pending_checkpoint.quiz.instructions }}</p>
            <div v-for="(question, qIdx) in sessionState.pending_checkpoint.quiz.questions" :key="qIdx" class="checkpoint-question">
              <strong>{{ qIdx + 1 }}. {{ question.question }}</strong>
              <label v-for="(choice, cIdx) in question.choices" :key="cIdx" class="row" style="align-items: center;">
                <input type="radio" :name="`q_${qIdx}`" :value="cIdx" v-model.number="checkpointAnswers[qIdx]" />
                <span>{{ choice }}</span>
              </label>
            </div>

            <button @click="submitCheckpoint" :disabled="submittingCheckpoint || !canSubmitCheckpoint">
              {{ submittingCheckpoint ? 'Submitting...' : 'Submit Checkpoint' }}
            </button>
          </div>
        </div>

        <div class="card" v-if="isSessionCompleted">
          <strong>Session Completed</strong>
          <div class="muted">{{ sessionState.session.completion_reason || 'Token limit reached for this thread.' }}</div>
          <div class="muted">Start a new session to continue learning deeper.</div>
        </div>

        <div class="row" style="align-items: flex-end;" v-if="!isSessionCompleted">
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
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { api } from '@/services/api';
import { renderAssistantMarkdown } from '@/utils/markdown';

const route = useRoute();
const router = useRouter();

const topics = ref<any[]>([]);
const sessionState = ref<any | null>(null);
const chatWindowRef = ref<HTMLElement | null>(null);
const lastPendingCheckpointId = ref<number>(0);
const showCheckpointPanel = ref(false);

const draftMessage = ref('');
const sendingMessage = ref(false);

const customTitle = ref('');
const customDescription = ref('');
const creatingTopic = ref(false);

const checkpointAnswers = ref<number[]>([]);
const submittingCheckpoint = ref(false);
const lastCheckpointResult = ref<any | null>(null);

const error = ref('');
const isSessionCompleted = computed(() => sessionState.value?.session?.status === 'completed');
const pendingQuestionCount = computed(() => Number(sessionState.value?.pending_checkpoint?.quiz?.questions?.length || 0));
const canSubmitCheckpoint = computed(() => {
  if (!sessionState.value?.pending_checkpoint) {
    return false;
  }
  const answered = checkpointAnswers.value.filter((answer) => Number.isInteger(answer) && answer >= 0).length;
  return answered >= pendingQuestionCount.value;
});
const tierPreview = computed(() => {
  const nextTier = Number(sessionState.value?.session?.next_checkpoint_tier || 1);
  const lastCleared = Number(sessionState.value?.session?.last_checkpoint_tier || 0);
  return Array.from({ length: 4 }, (_, idx) => {
    const tier = nextTier + idx;
    if (tier <= lastCleared) {
      return { tier, state: 'cleared' };
    }
    if (tier === nextTier) {
      return { tier, state: 'next' };
    }
    return { tier, state: 'locked' };
  });
});

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

watch(
  () => sessionState.value?.messages?.length,
  async () => {
    await nextTick();
    enhanceCodeBlocks();
  }
);

watch(
  () => sessionState.value?.pending_checkpoint?.id,
  (id) => {
    const pendingId = Number(id || 0);
    if (pendingId > 0 && pendingId !== lastPendingCheckpointId.value) {
      lastPendingCheckpointId.value = pendingId;
      showCheckpointPanel.value = true;
      checkpointAnswers.value = [];
      return;
    }

    if (pendingId === 0) {
      showCheckpointPanel.value = false;
    }
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
    if (!data.pending_checkpoint) {
      showCheckpointPanel.value = false;
    }
    await nextTick();
    enhanceCodeBlocks();
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
    showCheckpointPanel.value = false;
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
    if (!data.state?.pending_checkpoint) {
      checkpointAnswers.value = [];
    }
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

  if (!canSubmitCheckpoint.value) {
    error.value = 'Answer all checkpoint questions before submitting.';
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
    showCheckpointPanel.value = false;
  } catch (e: any) {
    error.value = e?.message || 'Failed to submit checkpoint';
  } finally {
    submittingCheckpoint.value = false;
  }
}

function formatAssistant(content: string) {
  return renderAssistantMarkdown(content);
}

function enhanceCodeBlocks() {
  const root = chatWindowRef.value;
  if (!root) {
    return;
  }

  const pres = root.querySelectorAll('.assistant-markdown pre');
  pres.forEach((pre) => {
    if (pre.getAttribute('data-copy-enhanced') === '1') {
      return;
    }
    pre.setAttribute('data-copy-enhanced', '1');

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'code-copy-btn';
    button.textContent = 'Copy code';
    button.addEventListener('click', async () => {
      const codeEl = pre.querySelector('code');
      const text = codeEl?.textContent || pre.textContent || '';
      if (!text.trim()) {
        return;
      }
      try {
        await navigator.clipboard.writeText(text);
        button.textContent = 'Copied';
      } catch {
        fallbackCopyText(text);
        button.textContent = 'Copied';
      }
      window.setTimeout(() => {
        button.textContent = 'Copy code';
      }, 1200);
    });
    pre.appendChild(button);
  });
}

function fallbackCopyText(text: string) {
  const el = document.createElement('textarea');
  el.value = text;
  el.style.position = 'fixed';
  el.style.opacity = '0';
  document.body.appendChild(el);
  el.focus();
  el.select();
  document.execCommand('copy');
  document.body.removeChild(el);
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
  padding-top: 38px;
  overflow: auto;
  margin: 8px 0 10px;
  position: relative;
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

.prompt-chip {
  text-align: left;
  white-space: normal;
}

.assistant-markdown :deep(.code-copy-btn) {
  position: absolute;
  top: 8px;
  right: 8px;
  border: 1px solid rgba(142, 166, 203, 0.35);
  background: rgba(8, 21, 42, 0.92);
  color: #d9e8ff;
  border-radius: 6px;
  font-size: 12px;
  padding: 4px 8px;
}

.checkpoint-overview {
  gap: 12px;
}

.checkpoint-head {
  justify-content: space-between;
  align-items: center;
}

.checkpoint-head-left {
  align-items: center;
}

.tier-pill {
  border: 1px solid rgba(96, 165, 250, 0.6);
  background: rgba(30, 64, 175, 0.26);
  color: #dbeafe;
  border-radius: 999px;
  padding: 4px 10px;
  font-weight: 700;
}

.points-pill {
  border: 1px solid rgba(74, 222, 128, 0.55);
  background: rgba(22, 163, 74, 0.18);
  color: #dcfce7;
  border-radius: 999px;
  padding: 4px 10px;
  font-weight: 700;
}

.tier-track {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.tier-node {
  border: 1px solid rgba(142, 166, 203, 0.25);
  border-radius: 10px;
  padding: 8px;
  font-size: 13px;
  text-align: center;
  color: #9fb1cc;
  background: rgba(6, 16, 34, 0.7);
}

.tier-node.cleared {
  border-color: rgba(74, 222, 128, 0.45);
  color: #dcfce7;
  background: rgba(22, 163, 74, 0.2);
}

.tier-node.next {
  border-color: rgba(96, 165, 250, 0.55);
  color: #dbeafe;
  background: rgba(30, 64, 175, 0.24);
}

.progress-stack {
  gap: 10px;
}

.progress-row {
  justify-content: space-between;
  font-size: 13px;
}

.progress-bar {
  width: 100%;
  height: 10px;
  border-radius: 999px;
  background: rgba(15, 29, 51, 0.95);
  border: 1px solid rgba(142, 166, 203, 0.24);
  overflow: hidden;
}

.progress-bar > span {
  display: block;
  height: 100%;
  background: linear-gradient(90deg, #38bdf8, #3b82f6);
}

.progress-bar.session > span {
  background: linear-gradient(90deg, #34d399, #22c55e);
}

.progress-meta {
  font-size: 12px;
}

.checkpoint-banner {
  border-color: rgba(250, 204, 21, 0.45);
  background: rgba(59, 44, 5, 0.28);
}

.checkpoint-banner-head {
  justify-content: space-between;
  align-items: center;
}

.checkpoint-banner-copy {
  flex: 1;
}

.checkpoint-inline {
  border: 1px solid rgba(250, 204, 21, 0.35);
  background: rgba(10, 20, 37, 0.75);
  border-radius: 10px;
  padding: 10px;
}

.checkpoint-question {
  border: 1px solid rgba(142, 166, 203, 0.3);
  border-radius: 10px;
  padding: 10px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

@media (max-width: 640px) {
  .tier-track {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .checkpoint-banner-head {
    align-items: stretch;
  }
}
</style>
