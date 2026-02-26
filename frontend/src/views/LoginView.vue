<template>
  <section class="panel col" style="max-width: 420px; margin: 24px auto;">
    <h2>Login</h2>
    <div class="oauth-row">
      <button class="oauth-btn discord" type="button" @click="startOAuth('discord')">Continue with Discord</button>
      <button class="oauth-btn github" type="button" @click="startOAuth('github')">Continue with GitHub</button>
    </div>
    <p class="muted" style="margin: 0;">or sign in with username/email</p>
    <form class="col" @submit.prevent="submit">
      <label>
        Username or Email
        <input v-model="identity" autocomplete="username" />
      </label>
      <label>
        Password
        <input v-model="password" type="password" autocomplete="current-password" />
      </label>
      <button type="submit" :disabled="loading">{{ loading ? 'Signing in...' : 'Sign In' }}</button>
    </form>
    <p class="muted">No account? <RouterLink to="/register">Register</RouterLink></p>
    <p v-if="error" style="color: #fca5a5">{{ error }}</p>
    <p v-if="oauthSuccess" style="color: #86efac">OAuth login complete.</p>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { api } from '@/services/api';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const identity = ref('');
const password = ref('');
const loading = ref(false);
const error = ref(typeof route.query.oauth_error === 'string' ? route.query.oauth_error : '');
const oauthSuccess = computed(() => route.query.oauth === 'success');

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    await auth.login(identity.value, password.value);
    router.push('/home');
  } catch (e: any) {
    error.value = e?.message || 'Login failed';
  } finally {
    loading.value = false;
  }
}

async function startOAuth(provider: 'discord' | 'github') {
  try {
    const data = await api.get<{ auth_url: string }>(`/auth/oauth/${provider}/url`);
    window.location.assign(data.auth_url);
  } catch (e: any) {
    error.value = e?.message || `Could not start ${provider} OAuth`;
  }
}
</script>

<style scoped>
.oauth-row {
  display: grid;
  gap: 10px;
}

.oauth-btn {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  border-radius: 10px;
  padding: 10px 12px;
  font-weight: 700;
  color: #fff;
  border: 0;
}

.oauth-btn.discord {
  background: #5865f2;
}

.oauth-btn.github {
  background: #111827;
}
</style>
