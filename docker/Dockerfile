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
    && rm -rf /var/lib/apt/lists/*

#
# get requirements for python script
#
COPY requirements.txt .
RUN pip install -r requirements.txt

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