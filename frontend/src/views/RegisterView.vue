<template>
  <section class="panel col" style="max-width: 500px; margin: 24px auto;">
    <h2>Register</h2>
    <div class="oauth-row">
      <button class="oauth-btn discord" type="button" @click="startOAuth('discord')">Sign up with Discord</button>
      <button class="oauth-btn github" type="button" @click="startOAuth('github')">Sign up with GitHub</button>
    </div>
    <p class="muted" style="margin: 0;">or create an account with email</p>
    <form class="col" @submit.prevent="submit">
      <label>
        Username
        <input v-model="username" autocomplete="username" />
      </label>
      <label>
        Email
        <input v-model="email" type="email" autocomplete="email" />
      </label>
      <label>
        Password
        <input v-model="password" type="password" autocomplete="new-password" />
      </label>
      <button type="submit" :disabled="loading">{{ loading ? 'Creating...' : 'Create Account' }}</button>
    </form>
    <p class="muted">Already registered? <RouterLink to="/login">Login</RouterLink></p>
    <p v-if="error" style="color: #fca5a5">{{ error }}</p>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { api } from '@/services/api';

const auth = useAuthStore();
const router = useRouter();

const username = ref('');
const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function submit() {
  loading.value = true;
  error.value = '';
  try {
    await auth.register(username.value, email.value, password.value);
    router.push('/home');
  } catch (e: any) {
    error.value = e?.message || 'Registration failed';
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
