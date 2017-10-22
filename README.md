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
* docker exec -ti <containerName> /bin/bash (e.g. klusbibapi.web.1)
* cd /app
* /app/.heroku/php/bin/php /app/vendor/robmorgan/phinx/bin/phinx migrate -e dokku
* Optionally also run seeder: /app/.heroku/php/bin/php /app/vendor/robmorgan/phinx/bin/phinx seed:run -e dokku -s UsersTableSeeder
