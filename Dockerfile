# =====================================================================
# Sistema "Alta Solicitada" - SEDES Oruro
# Imagen para CapRover: Apache + mod_php, sin ningun proceso adicional.
# =====================================================================
FROM php:8.3-apache

# --- Extensiones de PHP ----------------------------------------------
# La imagen oficial ya trae iconv, mbstring, fileinfo y zlib, que es todo
# lo que necesita FPDF. Solo falta el conector de MySQL.
RUN docker-php-ext-install -j"$(nproc)" pdo_mysql

# --- Configuracion de Apache -----------------------------------------
# headers: cabeceras de seguridad. rewrite no es imprescindible, pero
# evita sorpresas si mas adelante se agregan URLs amigables.
RUN a2enmod headers rewrite

COPY docker/apache-altas.conf /etc/apache2/conf-available/altas.conf
RUN a2enconf altas

COPY docker/php-altas.ini /usr/local/etc/php/conf.d/zz-altas.ini

# --- Codigo de la aplicacion ------------------------------------------
WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

# Estas dos carpetas se montan como volumenes persistentes en CapRover
# (ver DEPLOY.md). Se crean aqui para que la imagen funcione tambien sin
# volumenes, por ejemplo en una prueba local.
RUN mkdir -p /var/www/html/uploads/altas /var/www/html/data \
 && chown -R www-data:www-data /var/www/html/uploads /var/www/html/data \
 && rm -f /var/www/html/config.local.php

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
