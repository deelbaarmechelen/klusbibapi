#!/bin/bash

echo "Backing up data to Dropbox ..."

dt=$(date +"%Y-%m-%d")

echo " today is $dt"

DATA_PATH=/var/lib/dokku/data
BACKUP_PATH=/home/dokku/Dropbox/backup/dokku/$(date +"%Y")/$(date +"%B")

echo " creating $BACKUP_PATH .."
mkdir -p $BACKUP_PATH

cp -pr $DATA_PATH $BACKUP_PATH