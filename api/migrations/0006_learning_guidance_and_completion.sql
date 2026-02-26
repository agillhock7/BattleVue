SET @has_suggested_prompts := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'learning_sessions'
    AND COLUMN_NAME = 'suggested_prompts_json'
);

SET @add_suggested_prompts_sql := IF(
  @has_suggested_prompts = 0,
  'ALTER TABLE learning_sessions ADD COLUMN suggested_prompts_json JSON NULL AFTER last_checkpoint_tier',
  'SELECT 1'
);

PREPARE add_suggested_prompts_stmt FROM @add_suggested_prompts_sql;
EXECUTE add_suggested_prompts_stmt;
DEALLOCATE PREPARE add_suggested_prompts_stmt;

SET @has_completion_reason := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'learning_sessions'
    AND COLUMN_NAME = 'completion_reason'
);

SET @add_completion_reason_sql := IF(
  @has_completion_reason = 0,
  'ALTER TABLE learning_sessions ADD COLUMN completion_reason VARCHAR(160) NULL AFTER status',
  'SELECT 1'
);

PREPARE add_completion_reason_stmt FROM @add_completion_reason_sql;
EXECUTE add_completion_reason_stmt;
DEALLOCATE PREPARE add_completion_reason_stmt;

SET @has_completed_at := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'learning_sessions'
    AND COLUMN_NAME = 'completed_at'
);

SET @add_completed_at_sql := IF(
  @has_completed_at = 0,
  'ALTER TABLE learning_sessions ADD COLUMN completed_at DATETIME NULL AFTER last_activity_at',
  'SELECT 1'
);

PREPARE add_completed_at_stmt FROM @add_completed_at_sql;
EXECUTE add_completed_at_stmt;
DEALLOCATE PREPARE add_completed_at_stmt;
