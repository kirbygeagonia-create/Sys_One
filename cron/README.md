# SkillLoop Cron Jobs

## Session Reminders
Sends notifications to session participants 30-60 minutes before their session.

**Schedule:** Every 15 minutes
**Command:** `*/15 * * * * php /var/www/html/cron/send_reminders.php >> /var/log/skillloop.log 2>&1`

## Login Attempts Cleanup
The login_attempts table is cleaned up probabilistically (1-in-20 chance per login request).
For high-traffic deployments, add a dedicated nightly cleanup:

**Schedule:** Daily at 2am
**Command:** `0 2 * * * php -r "require '/var/www/html/config/database.php'; \$pdo->exec(\"DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 24 HOUR)\");" >> /var/log/skillloop.log 2>&1`