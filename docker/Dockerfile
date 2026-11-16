# Chamilo 3 for local development and CI of the ExeLearning plugin.
#
# Alpine + nginx + PHP-FPM from erseco/alpine-php-webserver (the base the
# family's alpine-omeka-s image uses). Chamilo 3 has no CLI installer, so the
# first boot replays the web installer's fresh-install branch from
# /usr/local/bin/chamilo-install.php (see docs/superpowers/specs, section 3.5).
FROM ghcr.io/erseco/alpine-php-webserver:3.24-php83

SHELL ["/bin/ash", "-eo", "pipefail", "-c"]

USER root

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# bcmath and ldap are required by chamilo-lms composer.json and missing in the base.
RUN apk add --no-cache unzip netcat-openbsd mariadb-client php83-bcmath php83-ldap php83-xsl

# The release ZIP already ships vendor/ and the built public/build/ assets, so
# no Composer or Yarn build is needed. Only stable tags (vX.Y.Z) are supported.
ARG CHAMILO_VERSION=v3.0.2

WORKDIR /var/www/chamilo

RUN set -x; \
    curl -fsSL -o /tmp/chamilo.zip "https://github.com/chamilo/chamilo-lms/releases/download/${CHAMILO_VERSION}/chamilo-${CHAMILO_VERSION#v}.zip"; \
    unzip -q /tmp/chamilo.zip -d /var/www/chamilo; \
    rm /tmp/chamilo.zip; \
    test -f public/build/entrypoints.json; \
    mkdir -p var public/plugin; \
    chown -R nobody:nobody /var/www/chamilo

COPY --chown=nobody rootfs/ /

ENV nginx_root_directory=/var/www/chamilo/public \
    memory_limit=512M \
    upload_max_filesize=256M \
    post_max_size=256M \
    client_max_body_size=256M \
    max_execution_time=300 \
    HOME=/tmp

USER nobody

HEALTHCHECK --interval=10s --timeout=5s --start-period=60s --retries=60 \
  CMD ["/bin/sh", "-c", "test -f /tmp/chamilo-ready && curl -fsS -o /dev/null http://127.0.0.1:8080/ || exit 1"]
