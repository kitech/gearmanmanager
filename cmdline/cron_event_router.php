<?php
  /**
   * 把系统的cron事件转发到gearman系统中，执行其中的特定worker
   *
   *
   * php /path/to/cron_event_router.php function_name
   * @author guangzhao1@kitech.com.cn
   * $Id$
   */

print_r($argv);

$servers = "127.0.0.1:4730";
$function_name = $argv[1];
$arg_file = realpath(__DIR__ . '/../var/tmp') . '/gmcron_event_args_' . $argv[1] . '.json';
$gmclient = null;
$log_file = "";

//////////////////
$gmclient = new GearmanClient();
$gmclient->addServers($servers);

echo $arg_file . "\n";
if (!file_exists($arg_file)) {
  echo "arg file not exists.\n";
  exit(-1);
} 

$arg_str = file_get_contents($arg_file);
$arg_obj = json_decode($arg_str, true);

print_r($arg_obj);

$repeat = $arg_obj['repeat'];
// $function_name = $arg_obj['fname'];
$workload = json_encode(empty($arg_obj['args']) ? '' : $arg_obj['args']);

if (empty($function_name)) {
  echo "empty function name, do what?\n";
  exit(-2);
}

$json_result = $gmclient->doBackground($function_name,
				       empty($workload) ? $function_name : $workload
				       );
$result = json_decode($json_result, true);

print_r($result);

// 如果非循环执行功能，则删除掉临时文件
if (empty($repeat)) {
    unlink($arg_file);
}

echo "Route to gearman done.\n";

?>