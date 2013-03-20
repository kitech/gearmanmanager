#!/bin/sh

# gmworker.sh ---
#
# Author: liuguangzhao
# Created: 2012-05-18 11:20:28 +0000
# Version: $Id$
#

############# 说明
# Usage: gmworker.sh <command> [param]
# (start|stop|status|restart|reload|help)
###################


###### 运行时变量，自动计算出
SCRIPT_DIR=$(dirname $(readlink -f $0))
#echo $SCRIPT_DIR

##### 配置参数，自动加载
if [ -f $SCRIPT_DIR/__etc/gmworker.conf ] ; then
    source $SCRIPT_DIR/__etc/gmworker.conf
elif [ -f $SCRIPT_DIR/etc/gmworker.conf ] ; then
    source $SCRIPT_DIR/etc/gmworker.conf
else
    echo "gmworker.conf not found.";
    exit -1;
fi

###### 运行时变量，自动计算出
#WORKER_PID_FILE=$SCRIPT_DIR/var/gmworker.pid.  # 后面是序号如，1,2,3
WORKER_PID_FILE=$gearmand_log_dir/gmworker.pid.  # 后面是序号如，1,2,3
ulimit -n 65535
ulimit -u 32767
#ulimit -p 32
#ulimit -a


####### 函数

function help() {
    true

    echo "Usage:  "
    echo "    " "$0 (start|stop|status|restart|reload|help)"
    echo "Version: 1.0 <@> 2012"
}


##### funcs
function start_gmworker_manager()
{
    pid_file=${gearmand_log_dir}/gwmanager.pid
    nohup $php_cmd $SCRIPT_DIR/gmworker_main.php "${gearman_servers}" "${memcache_servers}" "${gearmand_log_dir}" \
        "core" "${worker_processes}" \
        >> $gearmand_log_dir/gwmanager.log &
    RC=$?
    PID=$!

    if [ x"$RC" = x"0" ] ; then
        echo $PID > $pid_file
        true
    else
        echo "Error start manager $ic : $RC"
        exit $RC
        true
    fi
}

##### main 入口
spec_index=$2 # 指定启动某个worker进程
ic=0

case "$1" in
    start)
        #########  启动普通worker进程
        PIDNO=;  ### PID 列表，no => pid
        ic=1
        while [ $ic -le $worker_processes ] ; do

            ### 指定启动进程序号时在此确认
            if [ x"$spec_index" != x"" ] && [ $spec_index != $ic ] ; then
                ic=$(expr $ic + 1)
                continue;
            fi

            pid_file=${WORKER_PID_FILE}${ic}

            # 检测worker是否已经启动
            if [ -f $pid_file ] ; then
                OLD_PID=$(cat $pid_file)
                if [ x"$OLD_PID" = x"" ] ; then
                    true # 继续执行启动逻辑
                else
                    true # 检测是否进程还在
                    PROC_DIR=/proc/$OLD_PID
                    if [ -d "$PROC_DIR" ] ; then
                        # 进程还在，不再继续执行启动逻辑
                        echo "No. ${ic} worker process exists.Omited."
                        ic=$(expr $ic + 1)
                        continue;
                    fi
                fi
            fi

            #echo $pid_file;
            #exit;
            ##### 启动新的worker
            nohup $php_cmd $SCRIPT_DIR/gmworker_main.php "${gearman_servers}"  "${memcache_servers}" \
                "${gearmand_log_dir}" "app" "${ic}" \
                >> $gearmand_log_dir/gmworker.$ic.log &
            RC=$?
            PID=$!

            echo $RC $PID
            if [ x"$RC" = x"0" ] ; then
                # echo $PID > ${WORKER_PID_FILE}${ic}
                PIDNO[$ic]=$PID
                true
            else
                echo "Error start worker $ic : $RC"
                exit $RC
                true
            fi

            ic=$(expr $ic + 1)
        done

        # sleep 1; ### 不需要再等等了，PID已经暂存
        # 检测进程是否真的启动起来了
        ic=1
        while [ $ic -le $worker_processes ] ; do
            ### 指定启动进程序号时在此确认
            if [ x"$spec_index" != x"" ]  && [ $spec_index != $ic ] ; then
                ic=$(expr $ic + 1)
                continue;
            fi

            pid_file=${WORKER_PID_FILE}${ic};
            PID=$(cat $pid_file);
            PID=${PIDNO[$ic]};

            if [ x"$PID" = x"" ] ; then
                echo "Worker process not exists, no ${ic}.";
                true
            else
                WP=$(ps --no-heading --pid $PID);
                if [ x"$WP" = x"" ] ; then
                    echo "Worker process not exists $ic : $PID.";
                    exit -6;
                fi
            fi

            ic=$(expr $ic + 1);
        done

        ########## 启动管理进程
        if [ x"$spec_index" == x"" ] ; then
            start_gmworker_manager;
        fi
        ;;
    stop)
        ic=1
        while [ $ic -le $worker_processes ] ; do
            pid_file=${WORKER_PID_FILE}${ic}
            if [ ! -f $pid_file ] ; then
                echo "worker pid file not exists $ic."
            else
                PID=$(cat $pid_file)
                kill -KILL $PID
                RC=$?
                if [ x"$RC" = x"0" ] ; then
                    echo "Stop worker $ic($PID) done."
                    unlink $pid_file
                else
                    echo "Can not stop worker $ic : $RC"
                fi
            fi
            ic=$(expr $ic + 1)
        done
        ;;
    *)
        echo "Unknown or unimpled command '$1'"
        help
        ;;
esac

exit 0
