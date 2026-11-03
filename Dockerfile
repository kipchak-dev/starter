FROM 1x.ax/mamluk/php/8.5:franken-dev

COPY . /var/www/

# Runs FrankenPHP in worker mode. To run without worker mode, copy etc/caddy/Caddyfile.nonworker instead.
COPY etc/caddy/Caddyfile /etc/frankenphp/

RUN cd /var/www && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --optimize-autoloader && rm -rf /var/www/.git
