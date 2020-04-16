# Klusbib API

This application provides an API for the klusbib tool library. The whole application is running on dokku

[![Build Status](https://travis-ci.org/renardeau/klusbibapi.svg?branch=master)](https://travis-ci.org/renardeau/klusbibapi)

## Requirements
* Apache webserver
* PHP 7
* Mysql database

For cron jobs:
* PHP client (sudo apt-get install php7.2-cli)
* Mysql client (sudo apt-get install mycli)
* PHP mysql package (sudo apt-get install php-mysql)

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
* Run project seeder to create STROOM project
* /app/.heroku/php/bin/php /app/vendor/robmorgan/phinx/bin/phinx seed:run -e dokku -s ProjectsTableSeeder
* Optionally also run other seeders (Users, Tools, Reservations):
* /app/.heroku/php/bin/php /app/vendor/robmorgan/phinx/bin/phinx seed:run -e dokku -s UsersTableSeeder

## Install cron jobs

Cron jobs have to be installed on host and trigger script execution on dokku container

Copy scripts for backup to cron
* connecting to container with SSH
* cp /app/bin/dokku-data-export.sh /etc/cron.daily/dokku-data-export
* cp /app/bin/dokku-mysql-backup.sh /etc/cron.daily/dokku-mysql-backup
* chmod 755 /etc/cron.daily/dokku-*
* cp /app/bin/api.cron /etc/cron.d/api

TODO: install Dead Man's Snitch or Healthchecks to validate cron execution

See also
http://dokku.viewdocs.io/dokku/deployment/one-off-processes/ 
and https://code.tutsplus.com/tutorials/scheduling-tasks-with-cron-jobs--net-8800 for more info about cron.

If it still doesn't work, make sure cron mails you any STDOUT or STDERR output. That helps a lot. It doesn't need to go to a "real" email address. I did sudo apt-get install postfix, chose "Local" during setup, then sudo tail -f /var/mail/root (replace "root" with another username if applicable) to read the errors once cron had attempted to run the job. If you want to stop getting those mails later, pipe output to /dev/null in your crontab line.

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

## Using PHPMailer with GMail
Google requires OAuth authentication or 2-steps authentication
We configured OAuth with PHPMailer 6.x version
First step is to create an OAUTH client ID and generate a refresh token for it. We followed the steps as described on PHPMailer wiki
https://github.com/PHPMailer/PHPMailer/wiki/Using-Gmail-with-XOAUTH2
Note that PHPMailer 6.x contains an example script
The generation of the refresh token can be done using the /test/get_oauth_token.php script (make sure to update values for client id and secret)

The api needs a gmail account and corresponding OAUTH_CLIENT_ID, OAUTH_CLIENT_SECRET and OAUTH_TOKEN in its configuration. This
can be set with dokku config:set api OAUTH_CLIENT_ID=clientid OAUTH_CLIENT_SECRET=secret OAUTH_TOKEN=token SENDER_EMAIL=account@klusbib.be

One potential issue is the invalid_grant error on send
See also https://blog.timekit.io/google-oauth-invalid-grant-nightmare-and-how-to-fix-it-9f4efaf1da35

## DEV environment install
* Install apache (sudo apt-get install apache2)
* Install PHP
sudo apt install php7.2-cli
sudo apt-get install php-mysql
* Install mysql server (sudo apt-get install mysql-server)
* Install phpmyadmin
sudo apt-get install phpmyadmin
* configure Apache virtual hosts
** add to /etc/hosts: 
** create virtual host
cd /etc/apache2/sites-available
sudo cp 000-default.conf 001-klusbibapi.conf
** enable virtual hosts: 
cd /etc/apache/sites-enabled
sudo ln -s ../sites-available/001-klusbibapi.conf
** enable rewrite module
sudo a2enmod rewrite
sudo systemctl restart apache2
** restart apache service: sudo service apache2 restart

* xdebug
** sudo apt install php7.2-dev (install phpize)
** (sudo pecl channel-update pecl.php.net)
** sudo pecl install xdebug
** update php.ini file (/etc/php/7.2/apache2 and /etc/php/7.2/cli) to add following line:
   zend_extension=/usr/lib/php/20170718/xdebug.so


## Inventory install (Snipe-IT)
* See instruction on 

* php artisan passport:install
* php artisan migrate
* create api role and user
* create api token
* install api token on klusbibapi .env file as INVENTORY_API_KEY

* setup git local repository and remote host repository
See e.g. neostrada documentation: 
https://documentation.cpanel.net/display/CKB/Guide+to+Git+-+How+to+Set+Up+Deployment


## Deploy
* backup on remote host (e.g. neostrada)
tar --exclude 'inventory.deelbaarmechelen.be/vendor' -czvf inventory.tar.gz inventory.deelbaarmechelen.be/

* upgrade inventory
** git pull github master
** php upgrade.php
** git push origin master
Should trigger an automatic deploy based on .cpanel.yml. For manual migration:
** login to remote host
** cd into remote git repo
** update to pushed changes
git reset --hard HEAD 
** copy changes to deploy dir
rsync -anv --exclude '.git' --exclude '.github' --exclude '.*' --exclude 'tests' --exclude 'storage' snipe/ inventory.deelbaarmechelen.be 
** restore access rights
chmod 775 ~/inventory.deelbaarmechelen.be
** cd ~/inventory.deelbaarmechelen.be
** php upgrade.php
** php composer.phar install --no-dev --prefer-source
**  php composer.phar dump-autoload
**  php artisan migrate
**  php artisan config:clear
**  php artisan config:cache
** Check PHP version (in composer.json) and upgrade if required

## Test tools
* curl: this command line tool can be used to send requests to inventory or api (to be updated with inventory urls)
** Get the Bearer token using cURL and jq
TOKEN=$(curl -s -X POST -H 'Accept: application/json' -H 'Content-Type: application/json' --data '{"username":"{username}","password":"{password}","rememberMe":false}' https://{hostname}/api/authenticate | jq -r '.id_token')
In this example the API expects a POST body with “username”, “password” and “rememberMe” fields. Adapt according to your own needs.

jq is used to parse the JSON response, which contains the token in a field called “id_token”.

** Pass the Bearer token in the Authorization header
curl -H 'Accept: application/json' -H "Authorization: Bearer ${TOKEN}" https://{hostname}/api/myresource