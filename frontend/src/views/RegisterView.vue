<template>
  <section class="panel col" style="max-width: 500px; margin: 24px auto;">
    <h2>Register</h2>
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
    <button @click="submit" :disabled="loading">{{ loading ? 'Creating...' : 'Create Account' }}</button>
    <p class="muted">Already registered? <RouterLink to="/login">Login</RouterLink></p>
    <p v-if="error" style="color: #fca5a5">{{ error }}</p>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

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
</script>
