<?php
/* gmworker_manager.php ---
 *
 * Author: liuguangzhao
 * Created: 2012-05-06 15:40:52 +0800
 * Version: $Id$
 */
/*
  Usage: /path/to/php /path/to/gmworker_manager.php <gearman_servers> <memcache_servers>  <log_dir> <core_mode> [proc_index]
  Usage: /path/to/php -dmemory_limit=256M /path/to/gmworker_main.php 10.207.x.x:1234,10.207.x.x:2345 10.207.x.x:11211,10.207.y.y:11211  /data1/gmlogs/ app 5
  Usage: /path/to/php -dmemory_limit=256M /path/to/gmworker_main.php 10.207.x.x:1234,10.207.x.x:2345  10.207.x.x:11211,10.207.y.y:11211 /data1/gmlogs/ core
  启动 gearman manager worker 处理进程的入口脚本
  本脚本提供了其他的worker监控检测功能，为常驻系统进程，需要运行非常稳定，多测试几种情况
  params:
      core_mode 是否是管理进程模式启动 core|app
      proc_index worker的启动序号，用于定位pid文件。取值从1开开始，0为无效

  Return value:
      0 成功
      1,2,3 失败
      < 0  失败

  Depends:
      php >= 5.3.x
      php-memcache
      php-json
      php-gearman
 */

define('GMW_ROOT', dirname(__FILE__));
define('GMW_PLUGIN', GMW_ROOT . '/plugin');
define('GMW_CONF', GMW_ROOT . '/gmworker.conf');

require_once (GMW_ROOT . '/gmworker_lib.php');

//////
date_default_timezone_set('Asia/Chongqing');

// 检测所需要的扩展模块与函数
if (($nons = GearmanWorkerManager::checkExtensions()) !== true) {
    GearmanWorkerManager::_log('===== Lack of extension(s)');
    print_r($nons);
    GearmanWorkerManager::_log('===== Lack of extension(s)');
    exit(-5);
}

// 存储gearmand Job Server IP列表，格式：'10.207.15.251:4730'
// 把传递进来的IP列表添加到列表中。
$gearman_servers = $argv[1];
$memcache_servers = $argv[2];
$log_dir = $argv[3];
$core_mode = $argv[4] == 'core' ? true : false;
$proc_index = $argc >= 6 ? $argv[5] : 0;

$g_workerman = new GearmanWorkerManager($gearman_servers, $memcache_servers, $log_dir, $core_mode, $proc_index);

// 检测是否是本机上第一启动manager进程
if ($core_mode && !$g_workerman->check_manager_skel($log_dir)) {
    $g_workerman->_log('manager already run.');
    exit(-6);
}

/// for auto distiguish
// 自动加载插件，并向server注册
if (!$core_mode && !$g_workerman->register_plugins()) {
    $g_workerman->_log('register plugins error.');
    exit(-7);
}

$g_workerman->_log('Loading all workers done.');


//// blocked working ...
$bret = $g_workerman->runForever();

if ($bret === true) {
    $g_workerman->_log("worker manage exit with success: {$bret}");
    exit(0);
} else {
    $g_workerman->_log("worker manage exit with error: {$bret}");
    exit(-8);
}

