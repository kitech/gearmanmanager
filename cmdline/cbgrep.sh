#!/bin/sh

# couchbase 1.8 永久存储数据key值检索工具

# set -x


CBDIR=/opt/couchbase
CBUSER=Administrator
CBPASS=

SQLITE_CMD=$CBDIR/bin/sqlite3
#DATA_DIR=$CBDIR/var/lib/couchbase/data/default-data
#DATA_DIR=/data1/couchbasedb/default-data

DATA_PATH=$($CBDIR/bin/couchbase-cli server-info -c 127.0.0.1:8091 -u $CBUSER -p $CBPASS| grep path  | grep data | awk '{print $2}' | awk -F\" '{print $2}');
DATA_DIR=$DATA_PATH/default-data
# echo $DATA_DIR;

DB_FILES=$(ls $DATA_DIR/*.mb)

GREP_OPTS=$@
if [ x"$GREP_OPTS" == x"" ] ; then
    GREP_CMDLINE=" grep \'\' "
else
    GREP_CMDLINE=" grep $@ "
fi
# echo "$@"
# exit;


db_count=0;
table_count=0;
for dbfile in $DB_FILES
do
    #echo $dbfile;
    # echo "Getting $dbfile 's tables ...";
    db_tables=$($SQLITE_CMD $dbfile ".tables");
    for db_table in $db_tables
    do
       # echo "Processing $dbfile's table '$db_table' ...";

       $SQLITE_CMD $dbfile "SELECT vbucket,k FROM $db_table"  | $GREP_CMDLINE ;
       # break;
    done
    # break;
done




