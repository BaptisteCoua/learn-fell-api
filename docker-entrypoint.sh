#!/bin/sh
# One image, three roles. Railway sets CONTAINER_ROLE per service:
#   web       nginx + php-fpm (serversideup's /init, which also runs the
#             migrate/optimize automations when AUTORUN_ENABLED=true)
#   worker    the queue (AddQuestionToLearners, SendReviewReminder, notifications)
#   scheduler the per-minute scheduler (reminders:dispatch, model:prune)
# Whatever the role, the command is handed to serversideup's own entrypoint so
# nginx.conf templating and file-permission setup still happen; only the web
# role runs /init (and therefore opens a port and runs the automations).
set -e

case "${CONTAINER_ROLE:-web}" in
  web)       set -- /init ;;
  worker)    set -- php artisan queue:work --tries=3 --max-time=3600 --sleep=3 ;;
  scheduler) set -- php artisan schedule:work ;;
  *)
    echo "Unknown CONTAINER_ROLE '${CONTAINER_ROLE}' (expected web, worker or scheduler)" >&2
    exit 1
    ;;
esac

exec docker-php-serversideup-entrypoint "$@"
