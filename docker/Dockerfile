FROM php:fpm

WORKDIR /app

#
# adds mysql & mail to php
# installs python for halicrime.py
# installs mariadb-server to ping the db before loading data
#
RUN docker-php-ext-install mysqli \
    && apt-get update \
    && apt-get install -y \
    msmtp \
    python \
    python-pip \
    python-dev \
    mariadb-server \
    git \
    && rm -rf /var/lib/apt/lists/*

#
# get requirements for python script
#
COPY requirements.txt .
RUN pip install -r requirements.txt

#
# install composer for phpmailer
#
COPY composer.json \
    composer.lock \
    ./
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === 'e0012edf3e80b6978849f5eff0d4b4e4c79ff1609dd1e613307e16318854d24ae64f26d17af3ef0bf7cfb710ca74755a') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" \
    && php composer-setup.php \
        --install-dir=/usr/local/bin \
        --filename=composer \
    && php -r "unlink('composer-setup.php');" \
    && composer install

#
# need entrypoint to setup mail
#
COPY ./docker/docker-entrypoint.sh /usr/local/bin/

#
# copy the rest of the app over
#
COPY . .

ENTRYPOINT [ "docker-entrypoint.sh" ]
CMD ["php-fpm"]