#!/bin/bash

# Create a bunch of folders under the clean /var that php, nginx, and mysql expect to exist
mkdir -p /var/lib/mysql
mkdir -p /var/lib/nginx
mkdir -p /var/log
mkdir -p /var/log/mysql
mkdir -p /var/log/nginx
mkdir -p /var/cache
# Wipe /var/run, since pidfiles and socket files from previous launches should go away
# TODO someday: I'd prefer a tmpfs for these.
rm -rf /var/run
mkdir -p /var/run
mkdir -p /var/run/mysqld

# The mautic cache directory
mkdir -p /var/mautic-cache
# The mautic logs dir, which probably we don't need to store at all, I wonder?
mkdir -p /var/mautic-log

# Make the directory where PHP will store sessions.
mkdir -p /var/lib/php5/sessions

# Ensure mysql tables created
HOME=/etc/mysql /usr/bin/mysql_install_db --force

# Spawn mysqld, php
HOME=/etc/mysql /usr/sbin/mysqld &
/usr/sbin/php5-fpm --nodaemonize --fpm-config /etc/php5/fpm/php-fpm.conf &
# Wait until mysql and php have bound their sockets, indicating readiness
while [ ! -e /var/run/mysqld/mysqld.sock ] ; do
    echo "waiting for mysql to be available at /var/run/mysqld/mysqld.sock"
    sleep .2
done
while [ ! -e /var/run/php5-fpm.sock ] ; do
    echo "waiting for php5-fpm to be available at /var/run/php5-fpm.sock"
    sleep .2
done

ls -al /vagrant/app

# Start nginx.
/usr/sbin/nginx -g "daemon off;"
