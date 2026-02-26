<template>
  <section class="grid two">
    <article class="panel col">
      <h2>User Search</h2>
      <div class="row">
        <input v-model="q" placeholder="Search users" @keyup.enter="search" />
        <button @click="search">Search</button>
      </div>
      <div class="list">
        <div class="card" v-for="user in users" :key="user.id">
          <div class="row" style="justify-content: space-between; align-items: center;">
            <div>
              <strong>{{ user.display_name }}</strong>
              <div class="muted">@{{ user.username }}</div>
            </div>
            <div class="row">
              <button class="ghost" @click="requestFriend(user.id)">Add Friend</button>
              <RouterLink class="ghost" :to="`/profile/${user.id}`">Profile</RouterLink>
            </div>
          </div>
        </div>
      </div>
    </article>

    <article class="panel col">
      <h2>Friends & Requests</h2>

      <h3>Incoming Requests</h3>
      <div class="list">
        <div class="card" v-for="request in requests" :key="request.id">
          <strong>{{ request.sender_display_name || request.sender_username }}</strong>
          <div class="row">
            <button @click="respond(request.id, 'accepted')">Accept</button>
            <button class="ghost" @click="respond(request.id, 'declined')">Decline</button>
          </div>
        </div>
      </div>

      <h3>Friends</h3>
      <div class="list">
        <div class="card" v-for="friend in friends" :key="friend.id">
          <div class="row" style="justify-content: space-between; align-items: center;">
            <div>
              <strong>{{ friend.display_name }}</strong>
              <div class="muted">@{{ friend.username }}</div>
            </div>
            <div class="row">
              <button @click="challenge(friend.id)">Challenge</button>
              <button class="ghost" @click="remove(friend.id)">Remove</button>
            </div>
          </div>
        </div>
      </div>
      <p class="muted">{{ status }}</p>
    </article>
  </section>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/services/api';

const q = ref('');
const users = ref<any[]>([]);
const friends = ref<any[]>([]);
const requests = ref<any[]>([]);
const status = ref('');

onMounted(loadFriends);

async function search() {
  const data = await api.get<{ users: any[] }>(`/users/search?q=${encodeURIComponent(q.value)}`);
  users.value = data.users;
}

async function requestFriend(receiver_user_id: number) {
  await api.post('/friends/request', { receiver_user_id });
  status.value = 'Friend request sent.';
}

async function respond(request_id: number, action: 'accepted' | 'declined') {
  await api.post('/friends/respond', { request_id, action });
  await loadFriends();
}

async function loadFriends() {
  const data = await api.get<{ friends: any[]; requests: any[] }>('/friends/list');
  friends.value = data.friends;
  requests.value = data.requests;
}

async function remove(friend_user_id: number) {
  await api.post('/friends/remove', { friend_user_id });
  await loadFriends();
}

async function challenge(target_user_id: number) {
  const data = await api.post<{ match_id: number }>('/matches/challenge', { target_user_id });
  status.value = `Challenge sent for match #${data.match_id}.`;
}
</script>
