<template>
  <div v-if="!online || !apiHealthy" class="offline-banner">
    <strong>{{ !online ? 'You are offline.' : 'API unreachable.' }}</strong>
    <span>Actions are queued and can be synced when connection returns.</span>
  </div>
</template>

<script setup lang="ts">
defineProps<{ online: boolean; apiHealthy: boolean }>();
</script>

<style scoped>
.offline-banner {
  margin: 10px auto;
  max-width: 1080px;
  border: 1px solid rgba(245, 158, 11, 0.4);
  background: rgba(245, 158, 11, 0.12);
  color: #fde68a;
  border-radius: 12px;
  padding: 10px 14px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
</style>
