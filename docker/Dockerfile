# Development and CI image for the ExeLearning plugin: the published Chamilo
# image plus what a runtime-mounted plugin needs. Chamilo itself, its
# unattended install and its web server come from erseco/alpine-chamilo.
ARG CHAMILO_VERSION=3.0.2-r1
FROM ghcr.io/erseco/alpine-chamilo:${CHAMILO_VERSION}

USER root

# public/plugin is a Composer classmap entry: a plugin mounted at runtime only
# exists for Chamilo after `composer dump-autoload`, which rewrites these files.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN chown -R nobody:nobody /var/www/html/vendor/composer /var/www/html/vendor/autoload.php

# Runs after the base image's install step (10-chamilo.sh), on every boot.
COPY --chown=nobody --chmod=0755 30-plugin-autoload.sh /docker-entrypoint-init.d/30-plugin-autoload.sh

# Composer needs a writable home.
ENV HOME=/tmp

USER nobody
