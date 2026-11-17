FROM 1x.ax/mamluk/php/8.5:franken-dev

# The build marker: an identifier of this build, sent as X-Build on every response when it is set, so that a deploy
# job can read it back from the public hostname and confirm that the new build is serving. Pass it at build time, for
# example your release tag or the output of git describe:
#   docker build --build-arg BUILD_REF="$(git describe --tags)" .
# It is empty by default, and an image built without it sends no X-Build header. It may hold 1 to 64 letters, digits,
# dots, hyphens, plus signs and underscores, which covers tags such as v1.4.2, v1.4.2-3-gabc1234def56 and
# 2026-11-17-11-35+6d43f2f7e494. Any other value fails the build, so that no quote, space or control character reaches
# the server configuration. The test is a case pattern, which also refuses a newline.
ARG BUILD_REF=
RUN case "$BUILD_REF" in \
      *[!A-Za-z0-9._+-]*) echo "BUILD_REF may contain only letters, digits, '.', '-', '+' and '_'" >&2; exit 1 ;; \
    esac; \
    if [ "${#BUILD_REF}" -gt 64 ]; then echo "BUILD_REF may be at most 64 characters" >&2; exit 1; fi
ENV BUILD_REF=$BUILD_REF

COPY . /var/www/

# Runs FrankenPHP in worker mode. To run without worker mode, copy etc/caddy/Caddyfile.nonworker instead.
COPY etc/caddy/Caddyfile /etc/frankenphp/

RUN cd /var/www && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --optimize-autoloader && rm -rf /var/www/.git
