<?php
  /**
   * 重新加载一个worker
   * 
   * @param $file_name plugin/目录下的worker文件名
   * @param string $path worker 文件目录
   * @param array $files worker 文件名
   */
$_function = function ($job) use (&$g_workerman)  /* 可选，把全局变量带进worker函数，可以直接使用 */ 
{
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);

    $path = $args['path'];
    $files = $args['files'];

    $result = array();
    if (!empty($files)) {
        foreach ($files as $idx => $file) {
            $file_name = $path . '/' . $file;
            if ($g_workerman == NULL) {
                global $g_workerman;
            }
            $result[$file_name] = $g_workerman->hot_reload_worker($file_name);
        }
    }

    // 结果返回
    $json_result = json_encode($result);
    return $json_result;
};

// $_function = null;
$_register_name = 'reload_worker';
$_enable = true;
return array($_function, $_register_name, $_enable);
// array('func_obj', 'reg_name', 'enable');
