<?php
/**
 * rsync同步文件或目录操作
 * @author liuxp
 * @version $Id: rsync.php 1614 2011-08-08 09:58:45Z shiling $
 */
class Plugin_Rsync
{


	 private $_servers;

	 private $_module;

     private $_distServer = null;
     
     public static $_error;

	/**
	 *
	 * 构造函数
	 * @param  $modules  rsync模块
     * @param  $servers  rsync服务器
	 */
	public function __construct($module='', $server = '')
	{
        //module处理
        if ('' != $server) {
            $this->_servers = $server;
        } else {
            //$this->_servers = array('60.28.113.142');
            $this->_servers = array($_SERVER['kitech.com.cn_RSYNC_SERVER']);
        }

        //module处理
        if ('' != $module) {
            $this->_module = $module;
        }
	}

	/**
	 * 同步文件或目录
	 *
	 * @param string   $source          源文件名（相对路径）
     * @param boolean  $showMsg         是否显示错误信息
	 * @param boolean  $delSourceFile   是否删除源文件
 	 * @return boolean                  true成功，false失败
	 */
	/*public function rsync($source, $showMsg = true, $delSourceFile = false)
	{
		//同步文件到分发机
		$err_msg = '';
		//$param = '-rtpu --delete';

        $preDir = _DATA_DIR_ . _DIR_SEPARATOR_ . str_replace('_', '.', $this->_module)
				  . _DIR_SEPARATOR_;

        $file = $source;
        if ('/' == substr($source, 0, 1)) {
            $file = substr($source, 1);

        }

		foreach ($this->_servers as $server)
		{
            
            $command = "cd $preDir && rsync -Rtp --timeout=20 "
                       . $file
                       . "  $server::" . $this->_module . _DIR_SEPARATOR_;

            if($showMsg) echo '开始同步......';
            $st = microtimeFloat();
			system($command, $status);


            //flush_output($command);
			if (0 != $status) {
				$no_error = false;
                $err_msg = 'RSYNC同步到分发机失败：' . $command;
			}  else {
                $et = microtimeFloat();
                $et = $et - $st;
                if($showMsg) echo "同步完成，耗时：{$et}秒。<br>";
            }
		}


		if('' != $err_msg){
			//throw new Exception($err_msg);
            $showMsg && flush_output($err_msg);
			return false;
		} else {

            //告诉分发机分发文件到静态池

            $params = array(
                                'module'=> urlencode($this->_module),
                                'files' => urlencode($file . "\t"
                                           . md5_file($preDir . $file))
                           );

            $result = $this->_dist($params);
            if ('0' != trim($result)) {
                //throw new Exception($err_msg);
				$showMsg && flush_output('<div></div><div class="fl w230 red">' . $result . '</div><div class="clearfix"></div>');
                return false;
            }

			if ($delSourceFile) {
				unlink($prefixPath . $source);
			}
		}

		return true;
	}*/
	
	/**
     * 同步与分发
     *
     * @param array  $ips           同步主机ip数组
     * @param string $module        模块名
     * @param string $preDir        目录前辍
     * @param string $file          要同步的文件(全路径减去目录前辍之后的部分)
     * @param string $assign_api    分发接口,为空则不分发
     * @return bool
     */
	public static function rsync($ips, $module, $preDir, $file, $assign_api = '', $showMsg = false)
	{
        
	    self::$_error = '';
	    if(!is_array($ips)) return false;
	    
        //1.同步
        if($showMsg) echo '开始同步......';
        foreach ($ips as $ip)
        {
            if(!$ip) continue;
            $command = "cd $preDir && rsync -Rtp --timeout=20 "
                       . $file
                       . "  $ip::" . $module . '/';
            system($command, $status);

            if (0 != $status) {
                self::$_error = 'RSYNC同步到分发机失败：' . $command;
                if($showMsg) echo self::$_error;
            }
        }
        if(self::$_error) {
            return false;
        }
        if($showMsg) {
            echo '同步成功';
            // @unlink($preDir . $file);
        }
        
        
        //2.分发
        if(!$assign_api) return true;
        $params = array(
            'module'=> urlencode($module),
            'files' => urlencode($file . "\t" . md5_file($preDir . $file))
        );
        $result = self::_dist($params, $assign_api);
        if ('0' != trim($result)) {
            self::$_error = '分发失败' . $result;
            if($showMsg) echo self::$_error;
            return false;
        }
        return true;
	}

    /*
     * 分发机
	 * @param  array    $params   同步参数
     * @param  int      $timeout  超时时间，单位秒
     * @return boolean
     *
        1001           Desired CGI variable none defined
                       期望的CGI变量没有定义
        1002           CGI variable's value is NULL
                       CGI变量的值为空
        1010           File size wronging
                       文件大小错误
        1011           File size's value contain non-numeric character
                       文件长度参数包涵非数字的字符
        1012           Value is null
                       参数值为空
        1013           Read file size not equal real size
                       读文件错误，与实际大小不一致
        1014           MD5 check error
                       MD5 检测错误
        1015           MD5 checksum length not equal 33
                       MD5 校验值的长度不等于33个字符
        1016           Open log file faild
                       打开log文件错误
        1017           Write log file faild
                       写log文件错误
        1018           Create log dir faild
                       创建log文件目录错误
        1019           Bad log dir
                       错误的log目录或者不是一个目录文件
        1020           Not found module
                       配置中没有找到请求的module
        1021           Not found distribute group
                       配置中没有找到请求转发的分组
        1022           File name contain invalid characters
                       请求转发的文件名包含不合法的字符
        1023           File size too huge
                       请求转发的文件长度超出预设的最大长度
        1024           File size is zero
                       请求转发的文件长度等于0
        1025           Post length too long
                       Post过来的数据内容超长
        1050           Unknown error
                       其他未知错误
        1022 的说明：文件名只能由a-zA-Z0-9-_.这些字符构成，包涵其他的字符都不接收
        1023 的说明：转发的每个文件大小不能超过10M，这是目前的规定，不过可能有比这个大我们可以再讨论
    */
    private static function _dist($params, $assign_api)
    {
        $fields_string = '';
        foreach( $params as $key=>$value){
                $fields_string .= $key.'='.$value.'&';
        }
        rtrim($fields_string, '&');
        $ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL, $assign_api);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        //curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        $result = curl_exec($ch);
        curl_close($ch);
        return trim($result);
    }
};


/**
 * 同步与分发入口
 *
 *
 * @param string $ckey          缓存名
 * @param string $content       要同步的文件内容，如果有这个参数，则下面的参数不需要了
 * @param array  $ips           同步主机ip数组
 * @param string $module        模块名
 * @param string $preDir        目录前辍
 * @param string $file          要同步的文件(全路径减去目录前辍之后的部分)
 * @param string $assign_api    分发接口,为空则不分发
 * @return bool
 */
$_function = function ($job)
 {
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);

    $ckey = $args['ckey'];
    $content = $args['content'];
    $ips = $args['ips'];
    $module = $args['module'];
    $preDir = $args['preDir'];
    $content_file = $args['content_file'];
    $assign_api = $args['assign_api'];

    $full_path = $preDir . '/' . $content_file;

    echo $full_path . "\n";

    if (!file_exists($full_path)) {
        $server = '10.207.0.203:8091';
        $cbh = new Couchbase($server, true);

        $content = $cbh->get($ckey);
        echo "Get cb key: $ckey \n";
        echo $cbh->getResultCode() . '=' . $cbh->getResultMessage() . "\n";
        if (strlen($content) < 100) {
            var_dump($content);
        } else {
            echo "may be normal content.\n";
        }

        if (!$content) {
            $result = 404;
        } else {
            $new_preDir = sys_get_temp_dir() . $preDir;
            $new_full_path = sys_get_temp_dir() . $full_path;
            echo strrpos($new_full_path, '/') . "\n";
            $new_dir_tree = substr($new_full_path, 0, strrpos($new_full_path, '/'));
            echo "new dir: $new_dir_tree \n";
            if (!file_exists($new_dir_tree))
                mkdir($new_dir_tree, 0755, true);
            file_put_contents($new_full_path, $content);

            $rs1 = Plugin_Rsync::rsync($ips, $module, $new_preDir, $content_file, $assign_api, true);
            echo 'rsync via mc: ' . $rs1 . " $new_preDir $content_file $assign_api \n";

            $result = $rs1;

            unlink($new_full_path);
        }
    } else {
        $rs1 = Plugin_Rsync::rsync($ips, $module, $preDir, $content_file, $assign_api, true);
        echo 'rsync: ' . $rs1 . " $preDir $content_file $assign_api \n";

        $result = $rs1;
    }

    // $result = "rsync";
    var_dump($result);
    $json_result = json_encode($result);
    return $json_result;
 };

$_register_name = 'rsync';
$_enable = true;
return array($_function, $_register_name, $_enable);
