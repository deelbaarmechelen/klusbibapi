#!/bin/bash

echo "Backing up Mysql databases to Dropbox ..."

dt=$(date +"%Y-%m-%d")

echo " today is $dt"

BACKUP_PATH=/home/dokku/Dropbox/backup/mysql/$(date +"%Y")/$(date +"%B")
echo " creating $BACKUP_PATH .."

dbs=$(dokku mysql:list | grep mysql | cut -f1 -d' ')

for db in $dbs
do
  echo " backing up $db ..."
  mkdir -p $BACKUP_PATH/$db
  f=$BACKUP_PATH/$db/$dt-$db.tar
  rm -f $f
  dokku mysql:export $db > $f
  gzip -f $f
done

