#!/bin/sh
# First-boot install and every-boot refresh of Chamilo for the ExeLearning dev
# environment. Idempotent: an installed volume only gets its autoloader and
# cache refreshed, so `make down && make up` keeps all data.
set -eu

cd /var/www/chamilo
rm -f /tmp/chamilo-ready

echo "Waiting for ${DB_HOST:-db}:${DB_PORT:-3306}..."
i=0
until nc -z -w 1 "${DB_HOST:-db}" "${DB_PORT:-3306}"; do
    i=$((i + 1))
    [ "$i" -gt 120 ] && { echo "Database not reachable" >&2; exit 1; }
    sleep 1
done

php /usr/local/bin/chamilo-install.php

# public/plugin is a Composer classmap entry: plugin classes (and the
# *EventSubscriber files the compiler pass looks for) exist only after this.
php -d memory_limit=-1 /usr/bin/composer dump-autoload --no-interaction --quiet

php -d memory_limit=-1 bin/console cache:clear --no-interaction --quiet || {
    # A container cache built before the plugin classes existed can fail to
    # warm up; start from an empty cache in that case.
    rm -rf var/cache/*
    php -d memory_limit=-1 bin/console cache:clear --no-interaction --quiet
}

touch /tmp/chamilo-ready
echo "Chamilo ready at ${CHAMILO_URL:-http://localhost:8080}"
