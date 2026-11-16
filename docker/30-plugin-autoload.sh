#!/bin/sh
# Register the classes of the plugin mounted at public/plugin/ExeLearning in
# Chamilo's Composer classmap. The base image wipes the compiled cache on every
# start, so no cache:clear is needed here.
set -eu
cd /var/www/html
php -d memory_limit=-1 /usr/bin/composer dump-autoload --no-interaction --no-scripts --quiet
