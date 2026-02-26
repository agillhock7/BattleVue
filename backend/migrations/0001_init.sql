CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  migration_name VARCHAR(255) NOT NULL UNIQUE,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL UNIQUE,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(64) NOT NULL,
  avatar_url VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  csrf_token CHAR(64) NOT NULL,
  ip_address VARCHAR(64) NULL,
  user_agent VARCHAR(255) NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sessions_user (user_id),
  INDEX idx_sessions_expires (expires_at),
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS friend_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sender_user_id BIGINT UNSIGNED NOT NULL,
  receiver_user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  responded_at DATETIME NULL,
  INDEX idx_friend_requests_receiver (receiver_user_id, status),
  INDEX idx_friend_requests_sender (sender_user_id, status),
  CONSTRAINT fk_friend_requests_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_friend_requests_receiver FOREIGN KEY (receiver_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS friends (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_one_id BIGINT UNSIGNED NOT NULL,
  user_two_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_friend_pair (user_one_id, user_two_id),
  CONSTRAINT chk_friend_pair_order CHECK (user_one_id < user_two_id),
  CONSTRAINT fk_friends_user_one FOREIGN KEY (user_one_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_friends_user_two FOREIGN KEY (user_two_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blocker_user_id BIGINT UNSIGNED NOT NULL,
  blocked_user_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_blocks_pair (blocker_user_id, blocked_user_id),
  CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tracks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  title VARCHAR(128) NOT NULL,
  description TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  track_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(64) NOT NULL,
  title VARCHAR(128) NOT NULL,
  description TEXT NOT NULL,
  difficulty ENUM('easy','medium','hard') NOT NULL DEFAULT 'easy',
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_quest_track_slug (track_id, slug),
  INDEX idx_quests_track (track_id),
  CONSTRAINT fk_quests_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quest_steps (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quest_id BIGINT UNSIGNED NOT NULL,
  step_index INT NOT NULL,
  step_type ENUM('read','quiz','snippet','checklist') NOT NULL,
  payload_json JSON NOT NULL,
  required TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uniq_quest_step_index (quest_id, step_index),
  CONSTRAINT fk_quest_steps_quest FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS quest_completions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  quest_id BIGINT UNSIGNED NOT NULL,
  status ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  progress_json JSON NOT NULL,
  completed_at DATETIME NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_quest_completion (user_id, quest_id),
  INDEX idx_quest_completions_user (user_id, status),
  CONSTRAINT fk_quest_completions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_quest_completions_quest FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(128) NOT NULL,
  item_type ENUM('chassis','module','material','cosmetic') NOT NULL,
  rarity ENUM('common','uncommon','rare','epic') NOT NULL DEFAULT 'common',
  metadata_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reward_packs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  quest_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT NOT NULL,
  rewards_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reward_packs_quest (quest_id),
  CONSTRAINT fk_reward_packs_quest FOREIGN KEY (quest_id) REFERENCES quests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_inventory (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  item_id BIGINT UNSIGNED NOT NULL,
  quantity INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_user_item_inventory (user_id, item_id),
  CONSTRAINT fk_user_inventory_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_inventory_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bot_blueprints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  chassis VARCHAR(64) NOT NULL,
  lane_pref ENUM('left','mid','right','adaptive') NOT NULL DEFAULT 'adaptive',
  modules_json JSON NOT NULL,
  stats_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_blueprints_user (user_id),
  CONSTRAINT fk_blueprints_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bot_rulesets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  rules_json JSON NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_rulesets_user (user_id),
  CONSTRAINT fk_rulesets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bot_blueprint_ruleset (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  blueprint_id BIGINT UNSIGNED NOT NULL,
  ruleset_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_blueprint_ruleset (blueprint_id),
  CONSTRAINT fk_blueprint_ruleset_blueprint FOREIGN KEY (blueprint_id) REFERENCES bot_blueprints(id) ON DELETE CASCADE,
  CONSTRAINT fk_blueprint_ruleset_ruleset FOREIGN KEY (ruleset_id) REFERENCES bot_rulesets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS matches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mode ENUM('casual','ranked','challenge') NOT NULL,
  status ENUM('queued','awaiting_submission','simulating','completed','cancelled') NOT NULL DEFAULT 'queued',
  seed INT UNSIGNED NOT NULL,
  simulator_version VARCHAR(32) NOT NULL,
  created_by_user_id BIGINT UNSIGNED NOT NULL,
  winner_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  INDEX idx_matches_status_mode (status, mode),
  INDEX idx_matches_created_by (created_by_user_id),
  CONSTRAINT fk_matches_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_matches_winner FOREIGN KEY (winner_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS match_players (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  slot_order TINYINT UNSIGNED NOT NULL,
  blueprint_id BIGINT UNSIGNED NULL,
  ruleset_id BIGINT UNSIGNED NULL,
  blueprint_snapshot_json JSON NULL,
  ruleset_snapshot_json JSON NULL,
  submitted_at DATETIME NULL,
  hp_remaining INT NULL,
  result ENUM('win','loss','draw','pending') NOT NULL DEFAULT 'pending',
  score INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_match_user (match_id, user_id),
  UNIQUE KEY uniq_match_slot (match_id, slot_order),
  INDEX idx_match_players_user (user_id),
  CONSTRAINT fk_match_players_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_players_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_players_blueprint FOREIGN KEY (blueprint_id) REFERENCES bot_blueprints(id) ON DELETE SET NULL,
  CONSTRAINT fk_match_players_ruleset FOREIGN KEY (ruleset_id) REFERENCES bot_rulesets(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS match_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  tick INT NOT NULL,
  event_type VARCHAR(64) NOT NULL,
  payload_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_match_events_match_tick (match_id, tick),
  CONSTRAINT fk_match_events_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS match_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  match_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  message VARCHAR(280) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_match_messages_match_id (match_id, id),
  CONSTRAINT fk_match_messages_match FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
  CONSTRAINT fk_match_messages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(64) NOT NULL,
  title VARCHAR(140) NOT NULL,
  body VARCHAR(280) NOT NULL,
  data_json JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at DATETIME NULL,
  INDEX idx_notifications_user_read (user_id, is_read),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
