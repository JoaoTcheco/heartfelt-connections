# FarmaPonto - imagem minima (PHP + Apache), sem dependencias externas
FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite headers deflate expires

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/storage \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
       > /etc/apache2/conf-available/farmaponto.conf \
    && a2enconf farmaponto

EXPOSE 80
