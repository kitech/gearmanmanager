<?php
/*
 * 在gearman worker中共用的函数
 *
 * @author guangzhao1@leju.com
 */



/**
 * worker管理封装
 *
 * @TODO static or not static
 */
class GearmanWorkerManager
{
    // 单实例句柄
    static protected $_instance = null;

    // 相关目录
    private $_gmw_root = GMW_ROOT;
    private $_gmw_plugin = GMW_PLUGIN;
    private $_gmw_conf = GMW_CONF;
    private $_core_mode = false;

    // 运行状态
    private $_prefixes = null;
    public static $_route_tables = array(); // worker路由表，array('worker name' => $func_obj)
    private $_plugins = array(); // // ns => array($func_obj, $register_name, enable)
    private $_reg_count = 0;
    private $_raw_job_servers = ''; // 'ip1:port1,ip2:port2'
    public static $_memcache_servers = '';
    public static $_memres_expire = 604800; // 异步任务结果存储过期时间,7days
    private $_job_servers = array(); // array('ip1:port1', 'ip2:port2');
    private $_offline_job_servers = array();  //当前检测自动下线的 $_offline_job_server + $_job_servers = $raw_job_servers;
    private $_live_job_servers = array(); // 当前可用的job server，实现job server宕机自动踢除功能
    public static $_proj_envs = array(); // pdomain => env

    // 基本设置
    private $_worker = null;
    private $_wake_timeout = 1000; // 1秒
    private $_manage_skel_lock = null;
    private $_max_error_times = 30;
    private $_max_reconn_times = 100;
    private $_error_counter = 0;
    private $_served_counter = 0;

    private $_timeout_handler = null; // array($this, 'timeoutHandler'); 
    private $_shutdown = false;

    private $_worker_func_wrapper_prefix = 'wrapper';
    private $_skel_lock_fp = null;
    private $_log_dir = null;
    private $_proc_index = 0;
    private $_pid_file = null;
    private $_pid_fp = null;  // 给app模式使用

    private $_inotify_fds = null;

    private $_memory_size = 0;
    private $_cpu_count = 0;
    private $_node_weight = 0;

    protected $_node_pid = 0;
    protected static $_node_prefixes = array();
    protected static $_node_projects = array();
    public static $_stats = array(); // 每个worker的使用统计, total, worker_name_1,
    private $_crash_nodes = array(); // 重启动的worker序号， node_no => array('first_crash_time'=>, 'last_crash_time'=>, 'crash_times'=>);


    /**
     * 实例化本程序
     * @param $args = func_get_args();
     * @return object of this class
     */
    static public function getInstance()
    {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * 构造函数
     *
     * @param $servers string
     */
    public function __construct($gearman_servers, $memcache_servers, $log_dir, $core_mode, $proc_index)
    {
        $this->_node_pid = posix_getpid();

        $this->_raw_job_servers = $gearman_servers;
        self::$_memcache_servers = $memcache_servers;
        $this->_log_dir = $log_dir;
        $this->_proc_index = $proc_index;
        $this->_pid_file = $log_dir . '/gmworker.pid.' . $proc_index;

        $this->_core_mode = $core_mode;
        $this->_gmw_plugin = $this->_gmw_root . '/plugin';

        // set_exception_handler(array($this, 'gmwm_exception_handler'));
        set_error_handler(array($this, 'gmwm_error_handler'));

        $this->_initialize($gearman_servers);
    }

    public function __destruct()
    {
        if ($this->_pid_fp) {
            fclose($this->_pid_fp);
        }
    }

    /**
     * 初始化
     *
     * @param $servers 'ip1:port1,ip2:port2...'
     * @return true | false
     */
    protected function _initialize($servers)
    {
        $this->_worker = new GearmanWorker();

        /* depcreated 2013-03-18
        if ($this->_wakely) {
            $this->_worker->setTimeout($this->_wake_timeout);
            $this->_timeout_handler = array($this, 'timeoutHandler');
            // $worker->addOptions(GEARMAN_WORKER_NON_BLOCKING); 
        }
        */

        $this->_addServers($servers);
        // var_dump($this->_worker->echo('abc'.rand()));
        $this->_worker->removeServers('');
        // var_dump($this->_worker->echo('abc'.rand()));
        $this->_addServers($servers);
        // var_dump($this->_worker->echo('abc'.rand()));

        $iret = true;
        if (!$this->_core_mode) {
            $this->_pid_fp = fopen($this->_pid_file, 'w+');
            $iret = fwrite($this->_pid_fp, "{$this->_node_pid}");
            fflush($this->_pid_fp);
        }

        if (!$iret) {
            $this->_log('write pid file error.');
            return false;
        }

        // 初始化项目配置信息
        $conf_dir = __DIR__ . '/__etc/gmproj';
        if (!is_dir($conf_dir)) {
            $conf_dir = __DIR__ . '/etc/gmproj';
        }
        if (is_dir($conf_dir)) {
            $confs = glob($conf_dir . '/*.conf');
            if (count($confs)) {
                foreach ($confs as $idx => $conf) {
                    $fi = pathinfo($conf);
                    $envs = parse_ini_file($conf);
                    if ($envs === false) {
                        $this->_log('Parse project config file error:' . $conf);
                    } else {
                        self::$_proj_envs[$fi['filename']] = $envs;
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * 添加job server列表
     *
     * @param $servers 'ip1:port1,ip2:port2...'
     * @return true | false
     */
    protected function _addServers($servers)
    {
        $server_list = $servers;
        $this->_job_servers = array();

        if (empty($servers)) {
            return false;
        }

        // 解析gearmand server列表
        $srvs = explode(',', $server_list);
        if (count($srvs) > 0) {
        } else {
            $this->_log("Error, no gearmand server supplied.");
            return false;
        }

        foreach ($srvs as $idx => $srv) {
            $parts = explode(':', $srv);
            if (count($parts) == 1) {
                $parts[1] = 4730;
            }
            $this->_log("Adding server: {$parts[0]}:{$parts[1]}");
            $res = $this->_worker->addServer($parts[0], $parts[1]);

            $this->_job_servers[] = implode(':', $parts);
        }

        // $worker->addServer('10.207.26.251', 1234);
        // $worker->addServer('10.207.26.251', 1235);
        
        return true;
    }

    /**
     * 获取job server列表
     *
     * @return array('ip1:port1', 'ip2:port2', ...)
     */
    public function servers()
    {
        return $this->_job_servers;
    }

    /**
     * 注册所有的worker
     */
    public function register_plugins()
    {
        $plug_files = glob($this->_gmw_plugin . '/gmp_*\.php');

        // print_r($plug_files);
        if (empty($plug_files)) {
            $this->_log("No plugin found.");
            return false;
        }

        $succ_count = 0;
        $error_count = 0;
        foreach ($plug_files as $idx => $file) {
            $bret = $this->register_plugin($file, $idx);
            // print_r($this->_plugins);
            if ($bret <= 0) {
                // has error, continue
                $error_count += 1;
            } else {
                $succ_count += 1;
            }
        }        

        $this->_log("Loading all workers done, succ:{$succ_count}, error:{$error_count}.");
        
        return true;
    }

    /**
     * 加载并注册一个worker，记录worker相关信息
     */
    public function register_plugin($file_name)
    {
        global $g_workerman; // for worker closure use
        $plug_key = $file_name;
        $plug_meta = include($file_name);
        if (empty($plug_meta)) {
            $this->_log("Loading plugin ${file} failed.");
            return -2;
        }
        $load_time = time();
        $plug_source = file_get_contents($file_name);
        $plug_md5 = md5($plug_source);

        // 检查代码是否有语法错误，防止加载导致worker进程崩溃
        $my_check_syntax = 'php_check_syntax';
        if (!function_exists('php_check_syntax')) {
            $my_check_syntax = function ($file_name, &$emsg) {
                $php_exe = "/proc/{$this->_node_pid}/exe";
                $cmd = "{$php_exe} -l {$file_name}";
                $output = null;
                $rval = null;
                $line = exec($cmd, $output, $rval);
                var_dump($line, $output, $rval);

                if ($rval === 0) {
                    return true;
                }
                return false;
            };
        }
        if (!$my_check_syntax($file_name, $emsg)) {
            $this->_log('plug source file has syntax error:'.$emsg);
            return -3;
        }

        $this->_plugins[$plug_key] = array (
                                            'func_obj' => $plug_meta[0],
                                            'path' => $file_name,
                                            'register_name' => $plug_meta[1],
                                            'enable' => count($plug_meta)==3 ? $plug_meta[2] : true,
                                            'md5_hash' => $plug_md5,
                                            'load_time' => $load_time,
                                            );
        // print_r($plug_meta);
        if ((count($plug_meta) == 3 && $plug_meta[2] == true) 
            || count($plug_meta) == 2) {
            $res = $this->_register_worker_unit($plug_meta[0], $plug_meta[1]);
            $this->_plugins[$plug_key]['register_result'] = $res;
            if (!$res) {
                $this->_log("Register plugin ${file} failed.");
                return -3;
            }
        } else {
            $this->_log("Omited disabled plugin ${file}.");
        }

        return true;
    }


    /**
     * 核心动态路由worker函数实现
     * 
     * @status production
     *
     */
    public static function gmw_route_worker($job)
    {
        GearmanWorkerManager::_log("herele");
        $func_name = $job->functionName();
        GearmanWorkerManager::_log("herele" . $func_name);

        if (!array_key_exists($func_name, self::$_route_tables)) {
            self::_log('function not exists ' . $func_name . ' in list: ' . var_export(array_keys(self::$_route_tables), true));
            return json_encode(false);
        }

        $func_obj = self::$_route_tables[$func_name];
        ///// run it
        $success = true;
        $btime = microtime(true);
        try {
            // $job->sendStatus(0, 0);
            $tret = $func_obj($job);
            // $job->sendStatus(100, 100);

            $etime = microtime(true);
            $dtime = $etime - $btime;
            GearmanWorkerManager::_log("run code use time:" . $dtime . ($tret?'OK':'error'));
            $bret = self::store_worker_result($job, $tret, $btime, $etime);
            GearmanWorkerManager::_log("storage code :" . ($tret?'OK':'error'));

            if (!$tret) {
                $success = false;
            }
            GearmanWorkerManager::access($func_name, $success, microtime(true) - $btime);
            GearmanWorkerManager::_log("worker run done:" . $tret);
            return is_string($tret) ? $tret : json_encode($tret);
        } catch(Exception $e) {
            GearmanWorkerManager::_log('worker excpetioned.' . var_export($e, true));
            $success = false;
        }
        
        GearmanWorkerManager::access($func_name, $success, microtime(true) - $btime);
        return json_encode(false);
    }

    /**
     * @status depcreated
     *
     */
    private function _create_wrapper_worker_function($func_name)
    {
        $wrapper_function_name = $this->_worker_func_wrapper_prefix. '_' . $func_name;
        $func_code = "
                     function {$wrapper_function_name}(\$job) {
                         \$success = true;
                         \$btime = microtime(true);
                         try {
                             \$job->sendStatus(0, 0);
                             \$tret = {$func_name}(\$job);
                             \$job->sendStatus(100, 100);
                             if (!\$tret) {
                                 \$success = false;
                             }
                             GearmanWorkerManager::access('$func_name', \$success, microtime(true) - \$btime);
                             return \$tret;
                             // return {$func_name}(\$job);
                         } catch(Exception \$e) {
                             GearmanWorkerManager::_log('worker excpetioned.' . var_export(\$e, true));
                             \$success = false;
                         }

                         GearmanWorkerManager::access('$func_name', \$success, microtime(true) - \$btime);
                         return json_encode(false);
                     }
                     "
            ;

        if (function_exists($wrapper_function_name)) {
            $this->_log('wrapper function already exist: ' . $wrapper_function_name);
            return false;
        }
        
        eval($func_code);

        if (!function_exists($wrapper_function_name)) {
            $this->_log('create wrapper function error: ' . $wrapper_function_name);
            return false;
        }

        /*
        $wrapper_func_name = create_function('$job', "   try {
                                                             return {$func_name}(\$log);
                                                         } catch(Execption \$e) {
                                                             echo 'worker excpetioned.';
                                                         }
                                                         return NULL;
                                                     "
                                             );

        */

        return $wrapper_function_name;
    }

    /**
     * 创建路由gearman worker 函数
     *
     * 好处，一次创建，保持生效，并且不需要动态修改
     */
    private function _create_wrapper_worker_function2($func_name)
    {
        // 由于我们要获取客户希望执行的真实worker名字，还是需要生成一个函数，
        // 这个函数名以后就不需要运行动态修改了
        $wrapper_function_name = $this->_worker_func_wrapper_prefix. '_' . $func_name;
        $func_code = "
                     function {$wrapper_function_name}(\$job) {
                         GearmanWorkerManager::_log('wrapper level 1 function');
                         \$res = GearmanWorkerManager::gmw_route_worker(\$job);
                         GearmanWorkerManager::_log('wrapper level 1 function 222');
                         return \$res;
                     }
                     "
            ;

        if (function_exists($wrapper_function_name)) {
            $this->_log('wrapper function already exist, no need create: ' . $wrapper_function_name);
            return $wrapper_function_name;
        }
        
        eval($func_code);

        if (!function_exists($wrapper_function_name)) {
            $this->_log('create wrapper function error: ' . $wrapper_function_name);
            return false;
        }

        return $wrapper_function_name;
    }

    /**
     *
     * @param $func_name depcreated
     */
    private function _register_worker_unit($func_obj, $export_func_name)
    {
        if ($this->_prefixes == null) {
            $this->_prefixes = $this->get_node_prefixes();
        }

        // 
        $func_name = $export_func_name;

        // $reged_count ++;
        $this->_reg_count ++;
        $this->_log("W-{$this->_reg_count}. Servering {$export_func_name} ...");
        
        // wrapper for expection
        $wrapper_func_name = $this->_create_wrapper_worker_function2($func_name);
        $this->_worker->addFunction($export_func_name, $wrapper_func_name);
        self::$_route_tables[$export_func_name] = $func_obj; // 每次注册都需要更新

        if (!empty($this->_prefixes)) {
            foreach ($this->_prefixes as $k => $prefix) {
                // 注册以节点IP命名的worker
                $full_export_func_name = $prefix . $export_func_name;
                $this->_log("W-{$this->_reg_count}. Servering {$full_export_func_name} ...");
                $this->_worker->addFunction($full_export_func_name, $wrapper_func_name);
                self::$_route_tables[$full_export_func_name] = $func_obj; // 每次注册都需要更新

                // 注册以进程ID命名的worker
                $full_export_func_name = "{$prefix}{$this->_node_pid}_{$export_func_name}";
                $this->_log("W-{$this->_reg_count}. Servering {$full_export_func_name} ...");
                $this->_worker->addFunction($full_export_func_name, $wrapper_func_name);
                self::$_route_tables[$full_export_func_name] = $func_obj; // 每次注册都需要更新
            }
        } else {
            return false;
        }

        // register project name prefixed worker
        $project_names = array('gearman.house', 'photo.house', 'bbs.house');
        $project_names = $this->get_node_projects();
        foreach ($project_names as $idx => $pname) {
            $pname = 'project_' . str_replace('.', '_', $pname) . '_';
            $project_export_func_name = $pname . $export_func_name;
            $this->_log("W-{$this->_reg_count}. Servering {$project_export_func_name} ...");
            $this->_worker->addFunction($project_export_func_name, $wrapper_func_name);
            self::$_route_tables[$project_export_func_name] = $func_obj; // 每次注册都需要更新

            if (!empty($this->_prefixes)) {
                foreach ($this->_prefixes as $k => $prefix) {
                    $full_export_func_name = $prefix . $pname . $export_func_name;
                    $this->_log("W-{$this->_reg_count}. Servering {$full_export_func_name} ...");
                    $this->_worker->addFunction($full_export_func_name, $wrapper_func_name);
                    self::$_route_tables[$full_export_func_name] = $func_obj; // 每次注册都需要更新
                }
            }
        }

        return true;
    }


    /**
     * gearman主事件循环
     *
     * 检测错误，对一些可恢复错误做处理。
     * 
     */
    public function runForever()
    {
        if ($this->_core_mode) {
            return $this->runMonitorForever();
        } else {
            return $this->runWorkerForever();
        }
    }

    public function runWorkerForever()
    {
        $keep_going = true;
        $continue_error_count = 0;

        while (true && $keep_going) {
            $rv = 0;
            try {
                $rv = @$this->_worker->work();
            } catch (GearmanException $ge) {
                $this->_log("worker exception: ${continue_error_count}");
                print_r($ge);
                $emsg = $ge->getMessage();
                $gwerror = $this->_worker->error();
                $gwerrno = $this->_worker->getErrno();  // 
                $gwcode = $this->_worker->returnCode();
                $this->_log("eee: {$gwerrno}, {$gwerror}, {$gwcode}");

                // TODO 可以使用$gwerrno检测job server异常
                if ($emsg == 'Failed to set exception option' && $ge->getCode() === 0) {
                    $this->_log("May be job server(s) not invalid or gone away. reconnect after 3 sec. ({$this->_raw_job_servers})");
                    // 通过gearmand的端口号确定是哪些job server节点挂了.
                    $old_live_servs = $this->_live_job_servers;
                    $servs = $this->check_gearman_servers();
                    $diff_servs = array_diff($old_live_servs, $servs);
                    if (!empty($diff_servs) && !empty($servs)) {
                        $this->_log("drop offline servers and go on, offlines:" . implode(',', $this->_offline_job_servers));
                        continue;
                    }
                    
                    sleep(3);
                    $old_live_servs = $this->_live_job_servers;
                    $servs = $this->check_gearman_servers();
                    if (empty($servs)) {
                        $this->_log("still no live server, try next");
                    }

                    $servs = implode(',', $servs);
                    $this->_log("current lived servers, {$servs}");
                    continue;
                }

                // 未知错误，错误计数，退出
                // if ($continue_error_count ++ > 30) {
                if ($this->_error_counter ++ > $this->_max_error_times) {
                    $this->_log("Exceed max error times, {$this->_max_error_times}, exit.");
                    return -123;
                } else {
                    continue;
                }
            }
            /////////// block timeout, only for core_mode
            if ($this->_worker->returnCode() == GEARMAN_TIMEOUT) {
                // checkout app worker status
                // 检查应用层worker的状态
                $this->_log("checking app worker status ...");
                if (!empty($this->_timeout_handler)) {
                    call_user_func($this->_timeout_handler, 'a');
                }
            } else if ($this->_worker->returnCode() == GEARMAN_NO_ACTIVE_FDS) {
                // 到job server的连接中断 // 尝试重新连接
                $this->_log("Retry reconnect after 1 sec ...");
                sleep(1);
            } else if ($this->_worker->returnCode() == GEARMAN_SUCCESS) {
        
            }

            /////////////
            // $continue_error_count = 0;  // 重置计数
            $this->_error_counter = 0;
            $rc = $this->_worker->returnCode();
            $rs = $rc == GEARMAN_TIMEOUT ? '' : $this->_worker->error();
            $rn = $this->_worker->getErrno();
            $tout = $this->_worker->timeout();
            $this->_served_counter = $rc == GEARMAN_TIMEOUT ? ($this->_served_counter) : ($this->_served_counter + 1);
            $rv = var_export($rv, true);
            $this->_log("Served {$this->_served_counter}: rv=$rv, rc=$rc rn=$rn rs=$rs timeout=${tout}");
            if ($rv != true) {
            }        
        }

        return true;
    }

    public function runMonitorForever()
    {
        $keep_going = true;

        $bret = $this->_initialize_inotify();
        if (!$bret) {
            $this->_log("init inotify faild.");
            return $bret;
        }

        $change_num = 0;
        while (true && $keep_going == true) {
            // 获取监控事件资源
            $rdfds = array_values($this->_inotify_fds);
            $wtfds = null;
            $expfds = null;

            // select on all notify fds
            $iret = stream_select($rdfds, $wtfds, $expfds, 0xEFFFFF);
            if ($iret === false) {
                $this->_log("stream select error.");
                return false;
            }

            if ($iret == 0) {
                $this->_log("stream select timeout.".var_export($rdfds, true) . count($this->_inotify_fds));
                sleep(1);
                continue;
            }

            foreach ($rdfds as $idx => $mfd) {
                $evts = inotify_read($mfd); // 必须阻塞
                if (!$evts) {
                    $this->_log('invalid fsevent:' . var_export($evts, true));
                    continue;
                }
            
                $change_num += $num = count($evts);

                $file_names = array();
                foreach ($evts as $idx => $evt) {
                    if (substr($evt['name'], 0, 5) == '.gmp_') {
                        $this->_log("omit rsync temp file: {$evt['name']}");
                        continue;
                    }

                    $widx = substr($evt['name'], strpos($evt['name'], 'pid.') + 4);
                    if (substr($evt['name'], 0, strlen('gmworker.pid.')) == 'gmworker.pid.') {
                        // 进程退出，pid文件关闭了
                        if ($this->worker_exceed_max_crash($widx)) {
                            $this->_log("Worker process exceed max crash times({$widx}):");
                        } else {
                            $this->start_worker_process($widx);
                        }
                    } else if (substr($evt['name'], 0, 4) == 'gmp_') {
                        // worker文件有修改了
                        $file_names[] = $evt['name'];
                    } else {
                        $this->_log("unknown file path." . var_export($evt, true));
                    }
                }

                if (empty($file_names)) {
                    continue;
                } else {
                    $this->ipc_hot_reload_worker($file_names);
                }
            }
        }
    }


    /**
     * 重新加载已经更新的worker，不需要重启worker服务进程
     *
     */ 
    public function hot_reload_worker($worker_file_path)
    {
        // $file_path = $this->_gmw_plugin . '/' . $worker_file_name;
        $file_path = $worker_file_path;

        $this->_log("reloading worker {$file_path} ...");

        if (!file_exists($file_path)) {
            $this->_log('worker file not found:' . $file_path);
            return false;
        }

        $bret = $this->register_plugin($file_path);
        if ($bret < 0) {
            $this->_log('reload worker error,'.$file_path);
            return false;
        }

        $this->_log("reload worker done, {$file_path}");
        return true;
    }

    /**
     * 监控进程通过gearman client方式通知要重新加载已修改worker
     *
     * @param array $file_names 仅仅是文件名，如gmp_abc.php，需要在此把路径补全
     */ 
    public function ipc_hot_reload_worker($file_names)
    {
        $btime = microtime(true);

        // 是普通worker程序文件有变化
        $gmc = new GearmanClient();
        $bret = $gmc->addServers($this->_raw_job_servers);
        if (!$bret) {
            $this->_log("gmc add server error, {$this->_raw_job_servers}");
        } else {
            $workload = array('path' => $this->_gmw_plugin, 'files' => $file_names);
            $workload = json_encode($workload);

            for ($i = 1; $i <= $this->_proc_index; $i ++) {
                $worker_pid_file = $this->_log_dir . '/gmworker.pid.' . $i;
                $node_pid = trim(file_get_contents($worker_pid_file));
                if (empty($node_pid)) {
                    continue;
                }

                $worker_name = "{$this->_prefixes[0]}{$node_pid}_reload_worker";
                $bret = $gmc->doBackground($worker_name, $workload);
                $sret = var_export($bret, true);
                $this->_log("trigger reload worker result: {$i}, {$worker_name}, {$sret}");
            }
        }
        $gmc = null;

        $etime = microtime(true);
        $dtime = $etime - $btime;
        $this->_log("send {$worker_name} job used time: {$dtime}, ret={$bret}");        
    }

    /**
     * 检测统计worker进程退出次数
     * 防止代码问题时的死循环，不断重启动进程，查不到出错位置
     * 基于规则，频繁的进程退出极可能是代码实现逻辑有问题
     *
     * @param $index
     * @return true | false
     */
    protected function worker_exceed_max_crash($index)
    {
        $ntime = time();
        $reinit = false;

        if (isset($this->_crash_nodes[$index])) {
            $fct = $this->_crash_nodes[$index]['first_crash_time'];
            $lct = $this->_crash_nodes[$index]['last_crash_time'];
            $cts = $this->_crash_nodes[$index]['crash_times'];

            // 更新统计
            $this->_crash_nodes[$index]['last_crash_time'] = $ntime;
            $this->_crash_nodes[$index]['crash_times'] ++;

            // 2分钟内崩溃超过30次
            if ($ntime - $lct > 120) {
                // reinit crash stats
                $reinit = true;
            } else if ($cts + 1 >= 30) {
                return true;
            }
        } else {
            $reinit = true;
        }

        if ($reinit) {
            $this->_crash_nodes[$index] = array('first_crash_time' => $ntime, 'last_crash_time' => $ntime, 'crash_times' => 1);
        }
        
        return false;
    }
    
    /**
     * 在监控进程中重启动退出的worker进程
     */
    protected function start_worker_process($index)
    {
        $cmd = "cd /tmp && sh " . __DIR__ . "/gmworker.sh start {$index} > /dev/null  2>&1 &";
        $output = null;
        $rval = null;
        $line = exec($cmd, $output, $rval);
        var_dump($line, $output, $rval);

        $this->_log("Start worker process {$rval}: {$cmd}, $line");
        if ($rval === 0) {
            return true;
        }
        
        return false;
    }

    /**
     * 每个worker执行访问统计信息
     *
     */
    public static function access($worker_name, $success, $used_time)
    {
        static $_total_key = 'total'; // access, error
        static $_access_key = 'access';
        static $_error_key = 'error';

        if (array_key_exists($worker_name, self::$_stats)) {
            self::$_stats[$worker_name][$_access_key] += 1;    
            if (!$success) {
                self::$_stats[$worker_name][$_error_key] += 1;                        
            }
        } else {
            self::$_stats[$worker_name][$_access_key] = 1;
            if (!$success) {
                self::$_stats[$worker_name][$_error_key] = 1;
            }
        }

        if (array_key_exists($_total_key, self::$_stats)) {
            self::$_stats[$_total_key][$_access_key] += 1;
            if (!$success) {
                self::$_stats[$_total_key][$_error_key] += 1;
            }
        } else {
            self::$_stats[$_total_key][$_access_key] = 1;
            if (!$success) {
                self::$_stats[$_total_key][$_error_key] = 1;
            }
        }
    }

    public static function getStats()
    {
        return self::$_stats;
    }

    /**
     * @status depcreated
     */
    public function timeoutHandler()
    {
        $chk_btime = microtime(true);
        if ($this->_timeout_handler) {
            // call_user_func($this->_timeout_handler);
        }
        $chk_etime = microtime(true);
        $chk_dtime = $chk_etime - $chk_btime;
        $this->_log("timeout handle, {$chk_dtime}");
    }

    /**
     * 初始化worker源代码文件修改事件和worker进程退出事件
     */ 
    private function _initialize_inotify()
    {
        if ($this->_core_mode && $this->_inotify_fds == null) {
            $this->_inotify_fds = array();

            $paths = array($this->_gmw_plugin, $this->_log_dir);

            {
                $path = $this->_gmw_plugin;
                $fd = inotify_init();
                $this->_inotify_fds[$path] = $fd;
                $mask = IN_CLOSE_WRITE | IN_DELETE | IN_MOVED_TO; // 包括文件修改/文件创建/文件删除事件
                $watch_id = inotify_add_watch($fd, $path, $mask);
            
                // set(1)
                $read_fds = array($fd);
                $write_fds = null;
                $except_fds = null;
            
                // set(2);
                // stream_set_blocking($fd, 0); // block and realtime 
            }

            {
                $path = $this->_log_dir;
                $fd = inotify_init();
                $this->_inotify_fds[$path] = $fd;
                $mask = IN_CLOSE_WRITE;
                $watch_id = inotify_add_watch($fd, $path, $mask);
            }
        } else {
            // app_mode，不启动该服务
        }
        return true;
    }


    // 检测是否是本机上第一启动manager进程
    public function check_manager_skel($log_dir)
    {
        // global $skel_lock_fp;
        if (empty($this->_log_dir)) {
            $this->_log_dir = $log_dir;
        }

        $mlock = $this->_log_dir . '/gwmanager.lock';
    
        $this->_skel_lock_fp = $lfp = fopen($mlock, "w+");

        if (flock($lfp, LOCK_EX | LOCK_NB)) {
            return true;
        }
        return false;
    }

    public static function get_node_prefixes ()
    {
        if (!empty(self::$_node_prefixes)) {
            return self::$_node_prefixes;
        }
        $prefixes = array();

        // 可能的结果行格式
        // inet 10.207.15.55  netmask 255.255.255.0  broadcast 10.207.15.255
        // inet addr:10.207.16.254  Bcast:10.207.16.255  Mask:255.255.255.0
        $ncmd = "/sbin/ifconfig -a|grep 'inet '";
        $rlines = array();
        $rvar = 0;
        $rstr = exec($ncmd, $rlines, $rvar);

        if (!empty($rlines)) {
            foreach ($rlines as $k => $v) {
                $v = trim($v);
                $les = array();
                if (strchr($v, ':')) {
                    $les = explode(':', $v);
                    $les = explode(' ', 'fix ' . $les[1]);
                } else {
                    $les = explode(' ', $v);
                }
                $ip = trim($les[1]);
                // $rlines[$k] = "gmworker_node_" . $ip . '_'; // TODO 减小prefix长度
                $rlines[$k] = "gmwn_{$ip}_";
            }
        }

        self::$_node_prefixes = $rlines;
        return $rlines;
    }

    /**
     * 获取本节点安装的项目
     *
     */
    public static function get_node_projects()
    {
        if (!empty(self::$_node_projects)) {
            return self::$_node_projects;
        }

        $projects = array();

        $myroot = realpath(__DIR__ . '/..'); // /data1/vhost/pathto/photo.kitech.com.cn/trunk or photo.kitech.com.cn/htdocs/
        $mypath = realpath($myroot . '/..'); // /data1/vhost/pathto/photo.kitech.com.cn/
        $myname = substr($mypath, strrpos($mypath, '/') + 1);  // photo.kitech.com.cn
        $proj_pool_root = realpath($myroot . '/../..');
        $proj_names = glob($proj_pool_root . '/*');

        if (!empty($proj_names)) {
            foreach ($proj_names as $idx => $proj_root) {
                $proj_name = substr($proj_root, strrpos($proj_root, '/') + 1);
                $proj_worker_dir = str_replace($myname, $proj_name, $myroot) . '/command';
                $proj_worker_ctrls = glob($proj_worker_dir . '/*');
                // 如果目录/data1/vhost/pathto/photo.kitech.com.cn/htdocs/command
                // 存在，并且不为空，认为这个项目有命令行worker，将项目名作为前缀注册worker
                if (file_exists($proj_worker_dir) && is_dir($proj_worker_dir) && !empty($proj_worker_ctrls)) {
                    $projects[] = $proj_name;
                }
            }
        }

        self::$_node_projects = $projects;
        return $projects;
    }

    /**
     * gearman服务器端口检测
     * 
     * @return array 开放的job server 列表
     */
    public function check_gearman_servers()
    {
        if ($this->_worker->echo('ping')) {
            if (empty($this->_live_job_servers)) {
                $this->_live_job_servers = $this->_job_servers;
            }
            return $this->_live_job_servers;
        }

        $live_servers = array();
        $offline_servers = array();
        foreach ($this->_job_servers as $idx => $server) {
            $sps = explode(':', $server);
            if (count($sps) == 1) {
                $port = 4730;
            } else {
                $port = $sps[1];
            }
            $ip = $sps[0];

            if (!$this->check_tcp_service($ip, $port)) {
                $offline_servers[] = $server;
            } else {
                $live_servers[] = $server;
            }
        }

        $this->_live_job_servers = $live_servers;
        $this->_offline_job_servers = $offline_servers;

        $this->_worker->removeServers('');
        $this->_worker->addServers(implode(',', $live_servers));

        return $this->_live_job_servers;
    }

    /** 
     * 网络检测 
     * @param   string  机器IP 
     * @param   string  机器port 
     * @return  bool            
     */ 
    public static function check_tcp_service($ip, $port = 4730)  
    {  
        // socket链接测试,200ms超时  
        @$fp = fsockopen($ip, $port, $errno, $errstr, 0.2);   
        if ($fp){         
            $fp && fclose($fp);  
            return true;     
        } else {  
            return false;     
        }  
    }

    public function gmwm_exception_handler($exception)
    {
        $this->_log('Got exception,'.var_export($exception, true));
    }

    public function gmwm_error_handler($errno, $message, $file, $line)
    {
        $errname = '';
        switch($errno){
        case E_ERROR:               $errname = "Error";                  break;
        case E_WARNING:             $errname = "Warning";                break;
        case E_PARSE:               $errname = "Parse Error";            break;
        case E_NOTICE:              $errname = "Notice";                 break;
        case E_CORE_ERROR:          $errname = "Core Error";             break;
        case E_CORE_WARNING:        $errname = "Core Warning";           break;
        case E_COMPILE_ERROR:       $errname = "Compile Error";          break;
        case E_COMPILE_WARNING:     $errname = "Compile Warning";        break;
        case E_USER_ERROR:          $errname = "User Error";             break;
        case E_USER_WARNING:        $errname = "User Warning";           break;
        case E_USER_NOTICE:         $errname = "User Notice";            break;
        case E_STRICT:              $errname = "Strict Notice";          break;
        case E_RECOVERABLE_ERROR:   $errname = "Recoverable Error";      break;
        default:                    $errname = "Unknown error ($errno)"; break;
        }
        $this->_log("ENO:{$errno},ENAME:{$errname},{$message}, {$file}, {$line}");
    }

    /**
     * 存储异步任务的结果到队列中，供前端查询
     * 存储位置，memcache
     * 这个必须包含失效时间，否则，在客户端不关心结果的时候，可能保留无用数据
     * 存储的执行结果key格式：$job_unique + '_result';
     * 存储的执行结果value格式：json_encode(array('result'=>$result, 'ctime'=>'begin run time', 'dtime' => 'used time', 'node'=>'run node'));
     * 结构存储过期设置为 7 天
     * memcache server指定问题：
     */
    public static function store_worker_result($job, $result, $ctime = 0, $dtime = 0)
    {
        // $servers = '10.207.26.251:11211,10.207.26.251:11211'; // for simple test on test machine
        $servers = self::$_memcache_servers;
        $servers = explode(',', $servers);

        // TODO 如果能拿到项目的配置memcache_servers信息，在向项目的memcache写一份
        if (!empty(self::$_proj_envs)) {
            $pdomain = 'aaaaaaaaaaaaaa';
            if (isset(self::$_proj_envs[$pdomain])) {
                $envs = self::$_proj_envs[$pdomain];
                if (isset($envs['SINASRV_MEMCACHED_SERVERS'])) {
                    $this->_log('domain env: ' . $envs['SINASRV_MEMCACHED_SERVERS']);
                    $servers = array_merge($servers, explode(',', $envs['SINASRV_MEMCACHED_SERVERS']));
                }
            }
        }


        // 打包存储结果
        $prefixs = self::get_node_prefixes();
        $wukey = $job->unique();
        $mckey = $wukey . '_result';

        $wrapped_result = array('result' => $result,
                                'ctime' => $ctime,
                                'dtime' => $dtime,
                                'node' => $prefixs[0] . '_' . posix_getpid());
        $jresult = json_encode($wrapped_result);

        // 存储到memcache中
        $expire = self::$_memres_expire;
        foreach ($servers as $idx => $server) {
            $srvp = explode(':', $server);
            if (count($srvp) == 1) {
                $srvp[1] = 11211;
            }

            $cbh = new Memcache();
            $cbh->addServer($srvp[0], $srvp[1]);

            if ($cbh) {
                $bret = $cbh->set($mckey, $jresult, 0, $expire);
                $cbh->close();
                $cbh = null;
            }
        }

        $servers = implode(',', $servers);
        self::_log("Store result to {$servers}: ${mckey} with r={$bret}\n");


        return true;
    }

    private function _get_memory_size()
    {

    }

    private function _get_cpu_count()
    {

    }

    private function _reslove_worker_count()
    {

    }

    private function _reslove_node_weight()
    {
        
    }

    /**
     * 检测使用到的扩展模块/函数
     *
     * @return true | (不存在的扩展模块/函数列表)
     */
    public static function checkExtensions()
    {
        $nons = array();
        
        $CHK_MOD = 1;
        $CHK_FUN = 2;

        $chks = array('Memcache' => $CHK_MOD, 'Gearman' => $CHK_MOD, 'inotify' => $CHK_MOD,
                      'posix_getpid' => $CHK_FUN, 'pcntl_fork' => $CHK_FUN,
                      );

        foreach ($chks as $fm => $mt) {
            switch ($mt) {
            case $CHK_MOD: extension_loaded($fm) ? : $nons[$fm] = array('MOD', 1);
                break;
            case $CHK_FUN: function_exists($fm) ? : $nons[$fm] = array('FUN', 1);
                break;
            default: break;
            }
        }

        if (!empty($nons)) {
            return $nons;
        }

        return true;
    }

    public static function _log($str)
    {
        $trace = debug_backtrace(false);
        if (isset($trace[1])) {
            $caller = isset($trace[1]['class']) ? 
                ($trace[1]['class'] . '::' . $trace[1]['function']) : ($trace[1]['function']);
            if (!isset($trace[1]['line'])) {
                if (isset($trace[1]['args']) && isset($trace[1]['args'][3])) {
                    $caller .= ':L' . $trace[1]['args'][3];
                }
            } else {
                $caller .= ':L' . $trace[1]['line'];
            }
        } else {
            $caller = "Global scope: {$trace[0]['file']}:{$trace[0]['line']}";
        }

        $ntime = date('Y-m-d H:i:s');
        echo "I: [${ntime}] [{$caller}] {$str}.\n";
    }
};
