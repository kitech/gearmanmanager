<?php
/*
 * 示例worker
 *
 * 参数规则：
 * 传过来的参数是json格式结构，需要json解码
 * 由于参数按照json字符串传输，参数的数据结构对gearman透明，
 * 调用端与worker端必须明确json解码后参数结构的意义
 */

  /**
   * 执行一个命令行action
   *
   * @param $domain string action所属项目域名
   * @param $app string
   * @param $controller string
   * @param $action string
   * @param $params array
   * @return array('success' => true | false, 'result'=>mixed)
   */
  /*
    注意：
    支持多项目的action worker功能的集中部署，
    部署目录结构：
    |-- news.kitech.com.cn
    |   `-- htdocs
    |       |-- app
    |-- photo.kitech.com.cn
    |   `-- htdocs
    |       |-- app
    |-- gearman.leju.com
    |   `-- htdocs
    |       |-- app
    |-- pub.kitech.com.cn
    |   `-- htdocs
    |       |-- app

    程序根据项目名称参数自动加载项目的配置文件
   */
$_function = function ($job) use (&$g_workerman)
{
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);
    $domain = $args['domain'];
    $app = $args['app'];
    $controller = $args['controller'];
    $action = $args['action'];
    $params = $args['params'];


    $install_domain = 'gearman.leju.com'; // 这个gearman worker管理是安装在哪个项目下的
    // $install_doc_root = realpath(GMW_ROOT . '/..');
    $install_doc_root = realpath(GMW_ROOT); // 现在与其他项目平级
    $proj_doc_root = str_replace($install_domain, $domain, $install_doc_root);

    $conf_dir = __DIR__ . '/../__etc';
    if (!(file_exists($conf_dir) && is_dir($conf_dir))) {
        $conf_dir = __DIR__ . '/../etc';
    }
    $env_conf_file = $conf_dir .  "/gmproj/{$domain}.conf";
    if (!file_exists($conf_dir) || !is_dir($conf_dir) || !file_exists($env_conf_file)) {
        $result = array('success' => false, 'msg' => 'Virtual env config not exists:' . $env_conf_file);
        $json_result = json_encode($result);
        $g_workerman->_log($json_result);
        return $json_result;
    }

    $run_action_main_code = 
        "<?php
            ini_set('display_errors', 'On');
            error_reporting(E_ALL);
            define('_DEV_', false);
            define('_DEBUG_', false);
            
            if (file_exists(\"{$proj_doc_root}/config/consoleinit.php\")) {
                require_once(\"{$proj_doc_root}/config/consoleinit.php\");
            }
            define('_CLI_', true);
            \$_SERVER['argv'] = \$argv;
            \$_SERVER['argc'] = \$argc;
            \$envs = parse_ini_file(\"{$env_conf_file}\");
            foreach (\$envs as \$ek => \$ev) { \$_SERVER[\$ek] = \$ev; \$_ENV[\$ek] = \$ev; }
            // print_r(\$_SERVER);
            
            require_once(\"{$proj_doc_root}/framework/loader.php\");
            Leb_Loader::setAutoload();

            if (file_exists(\"{$proj_doc_root}/command/CLICommand.php\")) {
                 require_once(\"{$proj_doc_root}/command/CLICommand.php\");
            }

            \$controller = Leb_Controller::getInstance(true);
            \$aret = \$controller->run()->getReturn();
            // var_dump(\$aret);
            // var_dump('ob_level, ob_length', ob_get_level(), ob_get_length());
            echo \"__END_PRINT_OUTPUT__\"; // output and json_result seperator
            if (\$aret != null) { echo json_encode(\$aret, JSON_UNESCAPED_UNICODE); }
    ";

    /**
     * @status expired
     */
    $run_action_main_func = function ($proj_doc_root, $result_file) {
        ob_start();
        
        if (file_exists("{$proj_doc_root}/config/consoleinit.php")) {
            require_once("{$proj_doc_root}/config/consoleinit.php");
        }
        require_once("{$proj_doc_root}/framework/loader.php");
        Leb_Loader::setAutoload();
        $controller = Leb_Controller::getInstance(true);
        $aret = $controller->run()->getReturn();
        $jaret = json_encode($aret, JSON_UNESCAPED_UNICODE);

        $raw_output = ob_get_clean();

        $bret = file_put_contents($result_file, $raw_output . "__END_PRINT_OUTPUT__" . $jaret);

        return $jaret;
    };

    $rvar = '';
    $raw_output = '';

    ///// fork 启动新进程方式,这种方式还是不行，fork出来的进程退出后，会把继承过来的gearmanworker的socket连接关闭
    if (0) {

        $result_file = tempnam('/dev/shm/', 'run_command_action_cmd_result'). '.json';
        $pid = pcntl_fork();
        if ($pid < 0) {
            // error.
            $errmsg = posix_strerror(posix_errno());
            $rval = -1;
        } else if ($pid > 0) {
            // parent proces
            pcntl_waitpid($pid, $status);
            $rval = $status;
            if (file_exists($result_file)) {
                $raw_output = file_get_contents($result_file);
                unlink($result_file);
            }
        } else {
            // child process
            $chd_pid = posix_getpid();
            $chd_rval = $run_action_main_func($proj_doc_root, $result_file);
            echo "haha child command action runed, {$chd_pid}\n";
            posix_kill($chd_pid, 9); // 是否可以防止关闭父进程的socket???
            // 如果在run_action函数中出现异常退出，是子否会关闭父进程的socket???
            exit;
        }
    }

    /////// exec启动新进程方式
    if (1) {
        $script_file = '/dev/shm/run_command_action_cmd_' . date('Y-m-d_H-i-s_') . uniqid() . '.php';
        file_put_contents($script_file, $run_action_main_code);

        $mypid = posix_getpid();
        $php_exe = "/proc/{$mypid}/exe";

        $full_cmd = "{$php_exe} -f {$script_file} {$action} {$controller} {$app} ";
        if (!empty($params)) {
            $option_paris = array();
            foreach($params as $key => $value) {
                $vdata = json_encode($value);
                $option_paris[] = "--{$key}='{$vdata}'";
            }
            $option_line = implode(' ', $option_paris);
            $full_cmd .= " {$option_line} ";
        }

        $last_line = exec($full_cmd, $raw_output, $rvar);
        unlink($script_file);
    }

    // 解析action的所有输出结果。
    $raw_output = implode("\n", $raw_output);
    $pos = strrpos($raw_output, "__END_PRINT_OUTPUT__");
    $action_output = substr($raw_output, 0, $pos);
    $result_json_output = substr($raw_output, $pos + 20);
    $result = array('success' => false,
                    'cmd_line' => $full_cmd,
                    'retval' => $rvar,
                    'output' => $action_output,
                    'result' => $result_json_output); // 'action'
    if ($rvar === 0) {
        $result['success'] = true;
    }

    // 演示结果返回
    $json_result = json_encode($result, JSON_UNESCAPED_UNICODE);

    $g_workerman->_log($json_result);

    return $json_result;
};

$_register_name = 'run_command_action';
$_enable = true;
return array($_function, $_register_name, $_enable);
