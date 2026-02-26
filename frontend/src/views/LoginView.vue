<template>
  <section class="panel col" style="max-width: 420px; margin: 24px auto;">
    <h2>Login</h2>
    <label>
      Username or Email
      <input v-model="identity" autocomplete="username" />
    </label>
    <label>
      Password
      <input v-model="password" type="password" autocomplete="current-password" />
    </label>
    <button @click="submit" :disabled="loading">{{ loading ? 'Signing in...' : 'Sign In' }}</button>
    <p class="muted">No account? <RouterLink to="/register">Register</RouterLink></p>
    <p v-if="error" style="color: #fca5a5">{{ error }}</p>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const router = useRouter();

const identity = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

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
</script>
