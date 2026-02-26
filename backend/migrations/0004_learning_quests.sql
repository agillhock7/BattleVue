SET @has_bot_points := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'bot_points'
);

SET @alter_users_sql := IF(
  @has_bot_points = 0,
  'ALTER TABLE users ADD COLUMN bot_points INT NOT NULL DEFAULT 0',
  'SELECT 1'
);

PREPARE alter_users_stmt FROM @alter_users_sql;
EXECUTE alter_users_stmt;
DEALLOCATE PREPARE alter_users_stmt;

CREATE TABLE IF NOT EXISTS learning_topics (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(96) NOT NULL UNIQUE,
  title VARCHAR(140) NOT NULL,
  description TEXT NOT NULL,
  system_prompt TEXT NOT NULL,
  is_custom TINYINT(1) NOT NULL DEFAULT 0,
  created_by_user_id BIGINT UNSIGNED NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_learning_topics_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  topic_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
  cumulative_user_tokens INT NOT NULL DEFAULT 0,
  cumulative_model_tokens INT NOT NULL DEFAULT 0,
  cumulative_total_tokens INT NOT NULL DEFAULT 0,
  last_checkpoint_tier INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_activity_at DATETIME NULL,
  INDEX idx_learning_sessions_user (user_id, created_at),
  INDEX idx_learning_sessions_topic (topic_id, created_at),
  CONSTRAINT fk_learning_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_learning_sessions_topic FOREIGN KEY (topic_id) REFERENCES learning_topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  role ENUM('system','user','assistant') NOT NULL,
  content TEXT NOT NULL,
  token_count INT NOT NULL DEFAULT 0,
  model_token_count INT NOT NULL DEFAULT 0,
  total_token_count INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_learning_messages_session (session_id, id),
  CONSTRAINT fk_learning_messages_session FOREIGN KEY (session_id) REFERENCES learning_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS learning_checkpoints (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id BIGINT UNSIGNED NOT NULL,
  tier INT NOT NULL,
  quiz_json JSON NOT NULL,
  submitted_answers_json JSON NULL,
  score_percent INT NULL,
  passed TINYINT(1) NULL,
  awarded_points INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  UNIQUE KEY uniq_learning_session_tier (session_id, tier),
  INDEX idx_learning_checkpoints_pending (session_id, submitted_at),
  CONSTRAINT fk_learning_checkpoints_session FOREIGN KEY (session_id) REFERENCES learning_sessions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reward_ledger (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  source VARCHAR(64) NOT NULL,
  points INT NOT NULL,
  metadata_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_reward_ledger_user (user_id, created_at),
  CONSTRAINT fk_reward_ledger_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO learning_topics (slug, title, description, system_prompt, is_custom, is_active)
VALUES
(
  'wordpress',
  'WordPress Fundamentals',
  'Learn themes, plugins, hooks, and practical WP workflows.',
  'You are a practical WordPress tutor. Teach via concise, curiosity-driven prompts. Ask one guiding question at a time. Keep tone supportive but technical. Use concrete examples and mini exercises.',
  0,
  1
),
(
  'mysql-phpmyadmin',
  'MySQL and phpMyAdmin',
  'Learn schema design, indexing, and admin operations in phpMyAdmin.',
  'You are a MySQL/phpMyAdmin tutor focused on real workflows. Explain SQL clearly, test understanding with small challenges, and keep explanations accurate and practical.',
  0,
  1
),
(
  'postgresql-phppgadmin',
  'PostgreSQL and phpPgAdmin',
  'Learn Postgres schema design, queries, and admin tooling.',
  'You are a PostgreSQL tutor. Teach with practical SQL examples, compare tradeoffs, and use guided prompts to deepen understanding step by step.',
  0,
  1
),
(
  'vue',
  'Vue 3',
  'Learn reactivity, components, state, and routing with Vue.',
  'You are a senior Vue 3 tutor. Teach through small examples and Socratic prompts. Focus on composition API, state flow, and practical debugging habits.',
  0,
  1
),
(
  'vite-npm',
  'Vite and npm Tooling',
  'Learn modern frontend build and package workflows.',
  'You are a frontend tooling tutor. Teach Vite and npm workflows using practical commands, troubleshooting patterns, and production deployment considerations.',
  0,
  1
),
(
  'php',
  'PHP Backend Engineering',
  'Learn PHP architecture, security, sessions, and APIs.',
  'You are a production PHP tutor. Teach clean architecture, security, and API design with practical examples and mini challenges.',
  0,
  1
),
(
  'devops-cpanel',
  'cPanel Deployment Ops',
  'Learn Git deployment, Apache rules, and release safety in cPanel.',
  'You are a pragmatic DevOps tutor for cPanel-hosted apps. Teach deployment safety, rollbacks, and diagnostics with concise, concrete steps.',
  0,
  1
)
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  description = VALUES(description),
  system_prompt = VALUES(system_prompt),
  is_active = VALUES(is_active);
