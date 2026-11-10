FROM php:8.5-apache

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd intl zip @composer \
	&& apt-get update && apt-get install -y --no-install-recommends git unzip nodejs npm \
	&& npm install -g yarn@1 \
	&& rm -rf /var/lib/apt/lists/* \
	&& a2enmod rewrite headers deflate \
	&& sed -ri 's#/var/www/html#/var/www/html/public#g' /etc/apache2/sites-available/000-default.conf \
	&& printf '<Directory /var/www/html/public>\n\tAllowOverride All\n</Directory>\n' > /etc/apache2/conf-enabled/daily.conf

WORKDIR /var/www/html
COPY composer.json composer.lock* package.json yarn.lock .yarnrc ./
RUN git config --global url."https://github.com/".insteadOf "ssh://git@github.com/" \
	&& git config --global --add url."https://github.com/".insteadOf "git@github.com:" \
	&& sed -i 's#git+ssh://git@github.com/#git+https://github.com/#g' yarn.lock \
	&& composer install --no-dev --no-interaction --no-scripts --prefer-dist \
	&& yarn install --frozen-lockfile

COPY . .
RUN composer dump-autoload --optimize --no-dev \
	&& chmod +x docker-entrypoint.sh

ENV GROCY_MODE=production \
	GROCY_DEFAULT_LOCALE=es \
	GROCY_TIMEZONE=America/Bogota

ENTRYPOINT ["./docker-entrypoint.sh"]
CMD ["apache2-foreground"]
