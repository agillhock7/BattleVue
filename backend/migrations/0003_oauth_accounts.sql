CREATE TABLE IF NOT EXISTS oauth_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  provider ENUM('discord','github') NOT NULL,
  provider_user_id VARCHAR(191) NOT NULL,
  provider_username VARCHAR(191) NULL,
  email VARCHAR(255) NULL,
  avatar_url VARCHAR(255) NULL,
  profile_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_oauth_provider_user (provider, provider_user_id),
  UNIQUE KEY uniq_oauth_user_provider (user_id, provider),
  INDEX idx_oauth_user_id (user_id),
  CONSTRAINT fk_oauth_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
