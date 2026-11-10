FROM php:8.5-apache

# Las dependencias (packages/ y public/packages) van en el repo: el build no descarga nada de GitHub
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl zip \
	&& a2enmod rewrite headers deflate \
	&& sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
	&& printf '<Directory /var/www/html/public>\n\tAllowOverride All\n</Directory>\n' > /etc/apache2/conf-enabled/daily.conf

WORKDIR /var/www/html
COPY . .
RUN chmod +x docker-entrypoint.sh

ENV GROCY_MODE=production \
	GROCY_DEFAULT_LOCALE=es \
	GROCY_TIMEZONE=America/Bogota

ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["apache2-foreground"]
