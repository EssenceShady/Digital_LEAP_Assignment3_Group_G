# Digital LEAP — PHP 8.3 + Apache image.
#
# Build from the repository root so the Dockerfile can copy the application:
#   docker build -f deploy/Dockerfile -t digital-leap .
# or use deploy/docker-compose.yml, which also starts MariaDB.
#
# The image contains no configuration file. Mount a real config at
# /var/www/html/digital-leap/config/config.php (see deploy/README.md).
# Nothing in this image reaches the internet.

FROM php:8.3-apache-bookworm

ENV APACHE_DOCUMENT_ROOT=/var/www/html/digital-leap

# mbstring (guidebook and report text), pdo_mysql (database), zip (reading
# .docx drafts in DIVA). fileinfo and json are built into the image.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-available/leap-servername.conf \
    && a2enconf leap-servername

COPY deploy/php.ini /usr/local/etc/php/conf.d/leap.ini
COPY deploy/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

COPY digital-leap/ /var/www/html/digital-leap/

# Runtime logs and uploads are excluded from the build context, so recreate
# their folders before handing the tree to Apache.
RUN mkdir -p /var/www/html/digital-leap/storage/logs \
        /var/www/html/digital-leap/storage/uploads \
    && chown -R www-data:www-data /var/www/html/digital-leap/storage \
    && chmod -R u+rwX,g+rwX /var/www/html/digital-leap/storage

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD php -r 'exit(@fsockopen("127.0.0.1", 80) ? 0 : 1);'
