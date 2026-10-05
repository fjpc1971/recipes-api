# ---- Imagen de la aplicación ----
FROM php:8.3-apache AS app

# Todas las peticiones que no sean un fichero existente van al front controller
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
COPY public/ public/
COPY src/ src/

RUN mkdir -p /var/cache/recipes && chown -R www-data:www-data /var/cache/recipes

EXPOSE 80
