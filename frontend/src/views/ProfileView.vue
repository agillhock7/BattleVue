<template>
  <section class="panel col">
    <h2>Profile</h2>
    <p class="muted">User ID: {{ userId }}</p>
    <p>This MVP view uses social lists/search for user details and focuses on challenge and friend flows.</p>
    <div class="row">
      <button @click="block">Block User</button>
      <RouterLink class="ghost" to="/social">Back to Social</RouterLink>
    </div>
    <p class="muted">{{ status }}</p>
  </section>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import { api } from '@/services/api';

const route = useRoute();
const userId = Number(route.params.id);
const status = ref('');

async function block() {
  await api.post('/blocks/add', { blocked_user_id: userId });
  status.value = 'User blocked.';
}
</script>
