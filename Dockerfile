FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

COPY src /var/www/html/src

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/src/frontend' \
    '    Alias /api /var/www/html/src/api' \
    '    <Directory /var/www/html/src/frontend>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '    <Directory /var/www/html/src/api>' \
    '        AllowOverride None' \
    '        Require all granted' \
    '        RewriteEngine On' \
    '        RewriteBase /api/' \
    '        RewriteRule ^index\.php$ - [L]' \
    '        RewriteRule ^ index.php [QSA,L]' \
    '    </Directory>' \
    '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

EXPOSE 80

CMD ["sh", "-c", "PUERTO=${PORT:-80}; sed -ri \"s/^Listen [0-9]+/Listen ${PUERTO}/\" /etc/apache2/ports.conf && sed -ri \"s/<VirtualHost \\*:[0-9]+>/<VirtualHost *:${PUERTO}>/\" /etc/apache2/sites-available/000-default.conf && exec apache2-foreground"]
