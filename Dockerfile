FROM 1x.ax/mamluk/php/8.5:franken-dev

# The build marker: an identifier of this build, sent as X-Build on every response when it is set, so that a deploy
# job can read it back from the public hostname and confirm that the new build is serving. Pass it at build time, for
# example the pipeline's date tag and the first 12 characters of the commit:
#   docker build --build-arg BUILD_REF=2026-11-17-11-35+6d43f2f7e494 .
# It is empty by default, and an image built without it sends no X-Build header. Any other form fails the build, so
# that nothing malformed reaches the server configuration. The length test rejects a value with a newline in it,
# which grep alone would read line by line.
ARG BUILD_REF=
RUN if [ -n "$BUILD_REF" ]; then \
      [ "${#BUILD_REF}" -eq 29 ] \
      && printf '%s\n' "$BUILD_REF" | grep -Eqx '[0-9]{4}(-[0-9]{2}){4}\+[0-9a-f]{12}' \
      || { echo "BUILD_REF must look like 2026-11-17-11-35+6d43f2f7e494" >&2; exit 1; }; \
    fi
ENV BUILD_REF=$BUILD_REF

COPY . /var/www/

# Runs FrankenPHP in worker mode. To run without worker mode, copy etc/caddy/Caddyfile.nonworker instead.
COPY etc/caddy/Caddyfile /etc/frankenphp/

RUN cd /var/www && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --optimize-autoloader && rm -rf /var/www/.git
