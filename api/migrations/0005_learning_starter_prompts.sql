SET @has_starter_prompts := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'learning_topics'
    AND COLUMN_NAME = 'starter_prompts_json'
);

SET @alter_topics_sql := IF(
  @has_starter_prompts = 0,
  'ALTER TABLE learning_topics ADD COLUMN starter_prompts_json JSON NULL AFTER system_prompt',
  'SELECT 1'
);

PREPARE alter_topics_stmt FROM @alter_topics_sql;
EXECUTE alter_topics_stmt;
DEALLOCATE PREPARE alter_topics_stmt;

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'I am brand new to WordPress. What should I learn first?',
  'Can you explain themes vs plugins with simple examples?',
  'Give me a beginner-friendly 20-minute WordPress practice plan.'
)
WHERE slug = 'wordpress';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'I am new to MySQL. How do tables and relationships work?',
  'Teach me how to use phpMyAdmin to create a safe schema.',
  'What are indexes and when should I add them?'
)
WHERE slug = 'mysql-phpmyadmin';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'I am new to PostgreSQL. What basics should I start with?',
  'How do Postgres data types differ from MySQL in practice?',
  'Give me a starter exercise using tables and joins in PostgreSQL.'
)
WHERE slug = 'postgresql-phppgadmin';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'I am new to Vue 3. Explain refs and reactive state simply.',
  'How do components communicate in Vue with real examples?',
  'Give me a short Vue practice challenge I can do right now.'
)
WHERE slug = 'vue';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'What does Vite do compared to older build tools?',
  'Teach me practical npm scripts for dev and deployment.',
  'How do I troubleshoot build errors in Vite quickly?'
)
WHERE slug = 'vite-npm';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'I am new to PHP backend development. Where should I begin?',
  'How do sessions, auth, and CSRF protection fit together?',
  'Give me a beginner API architecture plan in PHP.'
)
WHERE slug = 'php';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  'How should I structure safe deployments on cPanel?',
  'Teach me how to debug Apache route and rewrite issues.',
  'Give me a deployment checklist to prevent downtime.'
)
WHERE slug = 'devops-cpanel';

UPDATE learning_topics
SET starter_prompts_json = JSON_ARRAY(
  CONCAT('I am brand new to ', title, '. What should I learn first?'),
  CONCAT('Can you give me a beginner roadmap for ', title, '?'),
  CONCAT('Give me a hands-on first exercise for ', title, '.')
)
WHERE is_custom = 1
  AND starter_prompts_json IS NULL;
