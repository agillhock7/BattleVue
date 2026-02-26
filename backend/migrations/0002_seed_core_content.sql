INSERT INTO tracks (slug, title, description, sort_order, is_active)
VALUES
  ('vue-basics', 'Vue Basics', 'Core reactivity, templates, and components.', 1, 1),
  ('pwa-primer', 'PWA Primer', 'Offline-first app patterns and caching.', 2, 1)
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), sort_order = VALUES(sort_order), is_active = VALUES(is_active);

INSERT INTO quests (track_id, slug, title, description, difficulty, sort_order, is_active)
SELECT t.id, 'reactive-core', 'Reactive Core', 'Learn refs, computed, and watch.', 'easy', 1, 1
FROM tracks t WHERE t.slug = 'vue-basics'
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), difficulty = VALUES(difficulty), sort_order = VALUES(sort_order), is_active = VALUES(is_active);

INSERT INTO quests (track_id, slug, title, description, difficulty, sort_order, is_active)
SELECT t.id, 'offline-cache', 'Offline Cache', 'Build resilient local-first quest caching.', 'medium', 1, 1
FROM tracks t WHERE t.slug = 'pwa-primer'
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), difficulty = VALUES(difficulty), sort_order = VALUES(sort_order), is_active = VALUES(is_active);

INSERT INTO quest_steps (quest_id, step_index, step_type, payload_json, required)
SELECT q.id, 1, 'read', JSON_OBJECT('title', 'Reactive data flow', 'content', 'Study how refs update templates.'), 1
FROM quests q WHERE q.slug = 'reactive-core'
ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), required = VALUES(required);

INSERT INTO quest_steps (quest_id, step_index, step_type, payload_json, required)
SELECT q.id, 2, 'quiz', JSON_OBJECT('question', 'Which API returns reactive primitives?', 'choices', JSON_ARRAY('ref', 'signal', 'state', 'atom'), 'answer', 'ref'), 1
FROM quests q WHERE q.slug = 'reactive-core'
ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), required = VALUES(required);

INSERT INTO quest_steps (quest_id, step_index, step_type, payload_json, required)
SELECT q.id, 1, 'read', JSON_OBJECT('title', 'Service worker strategy', 'content', 'Cache static content and fallback gracefully.'), 1
FROM quests q WHERE q.slug = 'offline-cache'
ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), required = VALUES(required);

INSERT INTO quest_steps (quest_id, step_index, step_type, payload_json, required)
SELECT q.id, 2, 'checklist', JSON_OBJECT('items', JSON_ARRAY('Install vite-plugin-pwa', 'Cache quest responses', 'Show offline banner')), 1
FROM quests q WHERE q.slug = 'offline-cache'
ON DUPLICATE KEY UPDATE payload_json = VALUES(payload_json), required = VALUES(required);

INSERT INTO items (slug, name, item_type, rarity, metadata_json)
VALUES
  ('chassis-starter', 'Starter Chassis', 'chassis', 'common', JSON_OBJECT('base_hp', 100, 'speed', 10)),
  ('module-laser-mk1', 'Laser MK1', 'module', 'common', JSON_OBJECT('damage', 12, 'cooldown', 2)),
  ('module-shield-mk1', 'Shield MK1', 'module', 'uncommon', JSON_OBJECT('armor', 4))
ON DUPLICATE KEY UPDATE name = VALUES(name), item_type = VALUES(item_type), rarity = VALUES(rarity), metadata_json = VALUES(metadata_json);

INSERT INTO reward_packs (quest_id, name, description, rewards_json)
SELECT q.id, 'Reactive Core Rewards', 'Starter rewards for quest completion',
JSON_ARRAY(
  JSON_OBJECT('item_slug', 'chassis-starter', 'quantity', 1),
  JSON_OBJECT('item_slug', 'module-laser-mk1', 'quantity', 1)
)
FROM quests q WHERE q.slug = 'reactive-core'
ON DUPLICATE KEY UPDATE description = VALUES(description), rewards_json = VALUES(rewards_json);

INSERT INTO reward_packs (quest_id, name, description, rewards_json)
SELECT q.id, 'Offline Cache Rewards', 'Module reward for PWA quest completion',
JSON_ARRAY(
  JSON_OBJECT('item_slug', 'module-shield-mk1', 'quantity', 1)
)
FROM quests q WHERE q.slug = 'offline-cache'
ON DUPLICATE KEY UPDATE description = VALUES(description), rewards_json = VALUES(rewards_json);
