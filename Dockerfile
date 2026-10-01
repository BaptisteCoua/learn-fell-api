# CINQ API — production image for Railway.
#
# serversideup/php ships nginx + php-fpm already wired for Laravel (document root
# on public/, opcache, healthcheck), runs rootless on port 8080, and bundles
# install-php-extensions and composer. One image serves the three roles the app
# needs (web, queue worker, scheduler); docker-entrypoint.sh picks the role from
# the CONTAINER_ROLE variable so each Railway service is the same build.
FROM serversideup/php:8.4-fpm-nginx

# imagick: the question-image processor decodes and resizes with the Imagick
# driver. redis: cache, queue and session locks. pdo_pgsql: the database.
USER root
RUN install-php-extensions imagick redis pdo_pgsql bcmath intl gd exif pcntl

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Bring the source in owned by the runtime user so storage/ and bootstrap/cache
# stay writable without a chown pass.
COPY --chown=www-data:www-data . /var/www/html
WORKDIR /var/www/html

USER www-data
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
