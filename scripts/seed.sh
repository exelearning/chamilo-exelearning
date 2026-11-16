#!/bin/sh
# Run the development seed inside the Chamilo container (see scripts/seed.php).
set -eu
cd /var/www/html
php -d memory_limit=-1 /usr/bin/composer dump-autoload --no-interaction --no-scripts --quiet
php -d memory_limit=-1 bin/console cache:clear --no-interaction --quiet
php -d memory_limit=-1 public/plugin/ExeLearning/scripts/seed.php --url="${CHAMILO_URL:-http://localhost:8080}"
