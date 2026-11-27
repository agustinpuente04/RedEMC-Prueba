FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && printf 'expose_php = Off\n' > /usr/local/etc/php/conf.d/seguridad.ini \
    && printf 'ServerName localhost\nServerTokens Prod\nServerSignature Off\n' >> /etc/apache2/apache2.conf

COPY src /var/www/html/src

RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/src/frontend' \
    '    Alias /api /var/www/html/src/api' \
    '    Header always set X-Content-Type-Options "nosniff"' \
    '    Header always set X-Frame-Options "DENY"' \
    '    Header always set Referrer-Policy "strict-origin-when-cross-origin"' \
    '    <Directory /var/www/html/src/frontend>' \
    '        Options -Indexes' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '    <Directory /var/www/html/src/api>' \
    '        Options -Indexes' \
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
