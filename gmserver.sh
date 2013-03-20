#!/bin/sh

# gmserver.sh --- 
# 
# Author: liuguangzhao
# Created: 2012-05-18 11:20:28 +0000
# Version: $Id$
# 


############# 说明
# 
# (start|stop|status|restart|reload|help)
###################

###### 运行时变量，自动计算出
SCRIPT_DIR=$(dirname $(readlink -f $0))
#echo $SCRIPT_DIR

##### 配置参数，自动加载
source $SCRIPT_DIR/etc/gmserver.conf

###### 运行时变量，自动计算出
GEARMAN_CONF_FILE=$SCRIPT_DIR/gearmand.conf
#GEARMAND_PID_FILE=$SCRIPT_DIR/var/gearmand.pid 
#GEARMAND_LOG_FILE=$SCRIPT_DIR/var/gearmand.log
GEARMAND_PID_FILE=$gearmand_log_dir/gearmand.pid
GEARMAND_LOG_FILE=$gearmand_log_dir/gearmand.log

####### 函数

function help() {
    true

    echo "Usage:"
    echo "    " "$0 (start|stop|status|restart|reload|help)"
    echo "Version: 1.0 <@> 2012"
}

##### 
function check_gearmand_process()
{
    pline=$(ps aux|grep gearmand|grep -v grep)
    echo $pline;
    true;
}

##### main 入口
# 

case "$1" in 
    start)
        pid_file=${GEARMAND_PID_FILE}
        #echo $pid_file;
        #exit;
        $gearman_prefix/sbin/gearmand --daemon --round-robin \
            --verbose=$gearmand_verbose \
            --port=$gearmand_port_base \
            --log-file=$GEARMAND_LOG_FILE \
            --pid-file=$GEARMAND_PID_FILE  \
            --queue-type=libmemcached --libmemcached-servers="$memcache_servers"
        #             --config-file=$GEARMAN_CONF_FILE 

        RC=$?
        PID=$(cat $GEARMAND_PID_FILE)

        # echo $RC $PID
        if [ x"$RC" = x"0" ] ; then
            echo $PID > ${GEARMAND_PID_FILE}
            echo "Start gearmand: success. pid=${PID}";
            true
        else
            echo "Error start gearmand : $RC"
            exit $RC
            true
        fi

        ;;
    stop)
        pid_file=${GEARMAND_PID_FILE}
        if [ ! -f $pid_file ] ; then
            echo "Gearmand pid file not exists."
            pline=$(check_gearmand_process);
            if [ x"$pline" != x"" ] ; then
                echo -e "But gearmand process is running, your's??? terminate it???\n>>> ${pline}"
            fi
        else
            PID=$(cat $pid_file)
            kill -INT $PID
            RC=$?
            if [ x"$RC" = x"0" ] ; then
                echo "Stop gearmand ($PID) done."
                # unlink $pid_file
                rm -fv $pid_file
            else
                echo "Can not stop gearmand : $RC"
            fi
        fi
        ;;
    *)
        echo "Unknown or unimpled command '$1'"
        help
        ;;
esac

exit 0
