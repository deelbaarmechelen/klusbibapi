#!/bin/bash
LOGFILE=/var/log/dokku/backup_mysql.log

echo "Backing up Mysql databases from Dokku ..." >> $LOGFILE

dt=$(date +"%Y-%m-%d")

echo " today is $dt" >> $LOGFILE

BACKUP_PATH=remote:dokku/mysql/$(date +"%Y")/$(date +"%B")
TEMP_DIR=/tmp/backup
LENDENGINE_PWD=<pwd>

echo " creating $TEMP_DIR .." >> $LOGFILE
mkdir -p $TEMP_DIR >> $LOGFILE

#dbs=$(dokku mysql:list | grep mysql | cut -f1 -d' ')
dbs=$(dokku mysql:list | grep db )

for db in $dbs
do
  echo " backing up $db ..." >> $LOGFILE
  mkdir -p $TEMP_DIR/$db >> $LOGFILE
  f=$TEMP_DIR/$db/$dt-$db.sql
  rm -f $f
  dokku mysql:export $db > $f
  gzip -f $f
  echo " backup file created at $f.gz" >> $LOGFILE
  rclone copy $f.gz $BACKUP_PATH/$db >> $LOGFILE
  rm -f $f.gz
  echo " backup file $f.gz transferred to $BACKUP_PATH/$db" >> $LOGFILE
done
echo " Lend Engine backup" >> $LOGFILE
rm -f $TEMP_DIR/klusbibdb/*.sql
sudo docker exec dokku.mysql.klusbibdb mysqldump --add-drop-table -p$LENDENGINE_PWD _core > $TEMP_DIR/klusbibdb/_core.sql
sudo docker exec dokku.mysql.klusbibdb mysqldump --add-drop-table -p$LENDENGINE_PWD lendengine > $TEMP_DIR/klusbibdb/lendengine.sql
f=$TEMP_DIR/klusbibdb/$dt-klusbibdb-lendengine.tar.gz
rm -f $f
cd $TEMP_DIR/klusbibdb
tar -czf $f *.sql
echo " backup file created at $f" >> $LOGFILE
rclone copy $f $BACKUP_PATH/klusbibdb >> $LOGFILE
rm -f $f
echo " backup file $f transferred to $BACKUP_PATH/klusbibdb" >> $LOGFILE

echo "Mysql backup completed" >> $LOGFILE
