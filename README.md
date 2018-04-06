# Klusbib API

This application provides an API for the klusbib tool library. The whole application is running on dokku

[![Build Status](https://travis-ci.org/renardeau/klusbibapi.svg?branch=master)](https://travis-ci.org/renardeau/klusbibapi)

## Install database

Database can be installed with phinx migrations
* connecting to container with SSH
* sudo -u dokku -i
* docker ps (get containerId)
* docker exec -ti <containerId> /bin/bash
OR
* docker exec -ti <containerName> /bin/bash (e.g. api.web.1)
* cd /app
* /app/.heroku/php/bin/php /app/vendor/robmorgan/phinx/bin/phinx migrate -e dokku
* Optionally also run seeder: /app/.heroku/php/bin/php /app/vendor/robmorgan/phinx/bin/phinx seed:run -e dokku -s UsersTableSeeder

## Install cron jobs

Cron jobs have to be installed on host and trigger script execution on dokku container

Copy scripts for backup to cron
* connecting to container with SSH
* cp /app/bin/dokku-data-export.sh /etc/cron.daily/dooku-data-export
* cp /app/bin/dokku-mysql-backup.sh /etc/cron.daily/dokku-mysql-backup
* chmod 755 /etc/cron.daily/dokku-*
* cp /app/bin/api.cron /etc/cron.d/api

TODO: install Dead Man's Snitch or Healthchecks to validate cron execution

See also
http://dokku.viewdocs.io/dokku/deployment/one-off-processes/ 
and https://code.tutsplus.com/tutorials/scheduling-tasks-with-cron-jobs--net-8800 for more info about cron.

f it still doesn't work, make sure cron mails you any STDOUT or STDERR output. That helps a lot. It doesn't need to go to a "real" email address. I did sudo apt-get install postfix, chose "Local" during setup, then sudo tail -f /var/mail/root (replace "root" with another username if applicable) to read the errors once cron had attempted to run the job. If you want to stop getting those mails later, pipe output to /dev/null in your crontab line.

## Initial server setup
https://www.digitalocean.com/community/tutorials/initial-server-setup-with-ubuntu-16-04

Login as root to create klusbib user
* adduser klusbib
* usermod -aG sudo klusbib

## Install mailer (for cron -> on host)

* login as a user with admin rights (e.g. klusbib)
* sudo apt-get install postfix
* sudo apt-get install mailutils libsasl2-2 ca-certificates libsasl2-modules
* vim /etc/postfix/main.cf
* sudo vim /etc/postfix/main.cf
* sudo vim /etc/postfix/sasl_passwd
* sudo chmod 400 /etc/postfix/sasl_passwd
* sudo postmap /etc/postfix/sasl_passwd
* sudo /etc/init.d/postfix reload
* echo "Test mail from postfix" | mail -s "Test Postfix" admin@klusbib.be

Note: if you run postfix reconfigure, the relay_host is reset. Make sure to restore the value to gmail settings
https://www.digitalocean.com/community/tutorials/how-to-install-and-configure-postfix-on-ubuntu-16-04
https://easyengine.io/tutorials/linux/ubuntu-postfix-gmail-smtp/

Forward root mail to custom address
* login as root
* vi ~/.forward
* enter destination address
Other important files: /etc/mailname, /etc/aliases, /etc/hosts