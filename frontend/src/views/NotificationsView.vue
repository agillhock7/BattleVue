<template>
  <section class="panel col">
    <h2>Notifications</h2>
    <div class="row">
      <button @click="markAll">Mark All Read</button>
    </div>
    <div class="list">
      <div class="card" v-for="item in notifications.items" :key="item.id">
        <div class="row" style="justify-content: space-between;">
          <strong>{{ item.title }}</strong>
          <span class="muted">{{ item.is_read ? 'Read' : 'Unread' }}</span>
        </div>
        <div>{{ item.body }}</div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useNotificationsStore } from '@/stores/notifications';

const notifications = useNotificationsStore();

onMounted(() => {
  notifications.fetch();
});

async function markAll() {
  await notifications.markRead([]);
}
</script>
