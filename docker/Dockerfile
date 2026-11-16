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

# bcmath, ldap and xmlwriter (phpspreadsheet, master) are required by chamilo-lms and missing in the base.
RUN apk add --no-cache unzip netcat-openbsd mariadb-client php83-bcmath php83-ldap php83-xsl php83-xmlwriter

# Tagged versions use the release ZIP, which already ships vendor/ and the built
# public/build/ assets. "master" (or any branch) is built from source, which
# needs Node/Yarn and ~4 GB of RAM for `yarn encore production`.
ARG CHAMILO_VERSION=v3.0.2

WORKDIR /var/www/chamilo

RUN set -x; \
    case "$CHAMILO_VERSION" in \
      v[0-9]*) \
        ver="${CHAMILO_VERSION#v}"; \
        curl -fsSL -o /tmp/chamilo.zip "https://github.com/chamilo/chamilo-lms/releases/download/${CHAMILO_VERSION}/chamilo-${ver}.zip"; \
        unzip -q /tmp/chamilo.zip -d /var/www/chamilo; \
        rm /tmp/chamilo.zip ;; \
      *) \
        apk add --no-cache git nodejs; \
        git clone --depth 1 --branch "$CHAMILO_VERSION" https://github.com/chamilo/chamilo-lms.git /var/www/chamilo; \
        # Composer scripts (assets:install, cache warmup) exceed the CLI's
        # default 128M; the runtime limit comes later from the base template.
        printf 'memory_limit=-1\n' > /etc/php83/conf.d/zz-build.ini; \
        COMPOSER_HOME=/tmp/composer composer install --no-interaction --no-progress; \
        # Alpine's nodejs ships no corepack: run the Yarn release chamilo-lms
        # commits under .yarn/releases. One command per step, so a failure
        # stops the build (set -e ignores failures inside an && chain).
        yarn_bin="$(ls .yarn/releases/yarn-*.cjs | head -n 1)"; \
        node "$yarn_bin" install; \
        NODE_OPTIONS=--max-old-space-size=4096 node "$yarn_bin" run encore production; \
        test -f public/build/entrypoints.json; \
        rm -rf node_modules /tmp/composer /etc/php83/conf.d/zz-build.ini ;; \
    esac; \
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
