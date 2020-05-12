#!/bin/sh
set -ex

# Setup mail
if [ -n "$MAIL_HOST" ]; then
    if [ ! -f /etc/msmtprc ]; then
        echo "sendmail_path = \"/usr/bin/msmtp -C /etc/msmtprc -a default -t\"" >> /usr/local/etc/php/conf.d/sendmail.ini
    fi

    cat <<EOF > /etc/msmtprc
account default
tls on
auth on
host ${MAIL_HOST}
port 587
user ${MAIL_USER}
from ${MAIL_USER}
password ${MAIL_PASS}
EOF
    chmod 600 /etc/msmtprc
    chown www-data /etc/msmtprc
fi

wait_for() {
  local CMD="$1"
  local DELAY=5
  local MAX_TRIES=5
  local n=0

  while true; do
    sh -c "$CMD" && break || {
      if [ $n -lt $MAX_TRIES ]; then
        n=$((n+1))
        echo "Command failed. Attempt $n/$MAX_TRIES:"
        sleep $DELAY;
      else
        echo "The command has failed after $n attempts."
        exit 1
      fi
    }
  done
}

# check if main script
if [ "$@" = "php-fpm" ]; then
    wait_for "mysqladmin ping -u $MYSQL_USER -p$MYSQL_PASSWORD -h mysql"

    #
    # run python script
    #
    python halicrime.py load_data

    #
    # set environment variables for cron
    #
    env > /etc/environment

    #
    # run cronjob for notifier
    #
    cron
fi

#
# php entrypoint 
# https://github.com/docker-library/php/blob/master/7.4/buster/fpm/docker-php-entrypoint
#
# first arg is `-f` or `--some-option`
if [ "${1#-}" != "$1" ]; then
	set -- php "$@"
fi

exec "$@"
