FROM php:8.2-apache

# pgsql 拡張 + curl（Overpass/Nominatim API用）
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
    && docker-php-ext-install pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# db/ scripts/ へのウェブアクセスを拒否
RUN printf '<Directory /var/www/html/db>\n  Require all denied\n</Directory>\n\
<Directory /var/www/html/scripts>\n  Require all denied\n</Directory>\n' \
    > /etc/apache2/conf-enabled/deny-internal.conf

# アプリをコピー（.env は .dockerignore で除外）
COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && chmod +x /var/www/html/db/entrypoint.sh

EXPOSE 80

CMD ["/var/www/html/db/entrypoint.sh"]
