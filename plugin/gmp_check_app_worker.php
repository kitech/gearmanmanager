<?php
/*
 * 示例worker
 * 
 * 参数规则：
 * 传过来的参数是json格式结构，需要json解码
 * 由于参数按照json字符串传输，参数的数据结构对gearman透明，
 * 调用端与worker端必须明确json解码后参数结构的意义
 */

function check_app_worker_impl()
{
    global $log_dir;
    
    $worker_pid_files = glob($log_dir . '/gmworker.pid.*');
    print_r($worker_pid_files);

    if (empty($worker_pid_files)) {
        echo "what / how many ?\n";
        return;
    }

    foreach ($worker_pid_files as $idx => $pid_file) {
        $pid = file_get_contents($pid_file);
        if (!empty($pid)) $pid = trim($pid);
        if (empty($pid) || !file_exists("/proc/{$pid}")) {
            echo "should try restart '{$pid_file}:{$pid}'\n";
            $start_script = 'sh ' . _DIR_ . '/gmwoker.sh start';

            $strret = null;
            $routput = null;
            $rval = null;
            $strret = exec($cmd, $routput, $rval);

            if ($rval !== 0) {
                echo "Start worker process error.errno:{$rval}, errstr:{$strret}\n";
            } else {
                echo "restart {$pid_file} ok.\n";
            }
        } else {
            echo "ok worker {$pid_file}:{$pid}\n";
        }
    }
}

$_function = function ($job) {
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);

    $result = "manager,monitor";

    // 演示结果返回
    $json_result = json_encode($result);
    return $json_result;
};

$_register_name = 'check_app_worker';
$_enable = true;
return array($_function, $_register_name, $_enable);

