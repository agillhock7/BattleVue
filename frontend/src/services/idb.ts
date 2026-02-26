import { openDB } from 'idb';

const dbPromise = openDB('battlevue-cache', 1, {
  upgrade(db) {
    if (!db.objectStoreNames.contains('quests')) {
      db.createObjectStore('quests', { keyPath: 'id' });
    }
    if (!db.objectStoreNames.contains('replays')) {
      db.createObjectStore('replays', { keyPath: 'id' });
    }
    if (!db.objectStoreNames.contains('drafts')) {
      db.createObjectStore('drafts', { keyPath: 'key' });
    }
    if (!db.objectStoreNames.contains('outbox')) {
      db.createObjectStore('outbox', { keyPath: 'id', autoIncrement: true });
    }
  }
});

export async function cacheQuest(quest: any) {
  const db = await dbPromise;
  await db.put('quests', { ...quest, cached_at: Date.now() });
}

export async function getCachedQuest(id: number) {
  const db = await dbPromise;
  return db.get('quests', id);
}

export async function cacheReplay(id: number, replay: any) {
  const db = await dbPromise;
  await db.put('replays', { id, replay, cached_at: Date.now() });
}

export async function getCachedReplay(id: number) {
  const db = await dbPromise;
  return db.get('replays', id);
}

export async function saveDraft(key: string, value: any) {
  const db = await dbPromise;
  await db.put('drafts', { key, value, updated_at: Date.now() });
}

export async function loadDraft(key: string) {
  const db = await dbPromise;
  return db.get('drafts', key);
}

export async function enqueueOutbox(entry: { endpoint: string; method: 'POST'; payload: any }) {
  const db = await dbPromise;
  await db.add('outbox', {
    ...entry,
    created_at: Date.now()
  });
}

export async function listOutbox() {
  const db = await dbPromise;
  return db.getAll('outbox');
}

export async function removeOutbox(id: number) {
  const db = await dbPromise;
  await db.delete('outbox', id);
}
