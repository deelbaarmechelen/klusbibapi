#!/bin/bash
LOGFILE=/var/log/dokku/backup_data.log
echo "Backing up data from Klusbib API..." >> $LOGFILE

dt=$(date +"%Y-%m-%d")

echo " today is $dt" >> $LOGFILE

#DATA_PATH=/var/lib/dokku/data
DATA_PATH=/var/lib/dokku/data/storage
#BACKUP_PATH=/home/klusbib/Dropbox/backup/dokku/$(date +"%Y")/$(date +"%B")
BACKUP_PATH=remote:dokku/data/storage/$(date +"%Y")

#echo " creating $BACKUP_PATH .." >> $LOGFILE
#mkdir -p $BACKUP_PATH

#cp -pr $DATA_PATH $BACKUP_PATH
rclone sync $DATA_PATH/klusbibapi $BACKUP_PATH/klusbibapi -P >> $LOGFILE
rclone sync $DATA_PATH/inventory $BACKUP_PATH/inventory -P >> $LOGFILE
rclone sync $DATA_PATH/lendengine $BACKUP_PATH/lendengine -P >> $LOGFILE
echo " backup of $DATA_PATH to $BACKUP_PATH completed" >> $LOGFILE