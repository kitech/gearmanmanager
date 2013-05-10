<?php
/*
 * 示例worker
 * 
 * 参数规则：
 * 传过来的参数是json格式结构，需要json解码
 * 由于参数按照json字符串传输，参数的数据结构对gearman透明，
 * 调用端与worker端必须明确json解码后参数结构的意义
 * 不再需要由worker来存储结果，gearman管理机制能捕捉到worker的返回值，按固定统一格式存储
 * 因为php本身的普通函数是不能重定义，重加载，或者是删除函数定义的
 * 所以worker函数写成闭包语法，在gearman管理的时候，能容易地动态修改worker函数
 */

  /**
   * 执行后台http请求
   * 
   * @param string $url
   * @param string $method get|post
   * @param array $data 可选择的
   * @param array $header 可选择的
   */
$_function = function ($job) /* use (&$g_workerman)  */ /* 可选，把全局变量带进worker函数，可以直接使用 */ 
{
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);
    $url = $args['url'];
    $method = isset($args['method']) ? strtolower($args['method']) : 'get';
    $data = isset($args['data']) ? $args['data'] : array();
    $header = isset($args['header']) ? $args['header'] : array();


    ///////////////////////////////////////////////////////
    // methods
    ///////////////////////////////////////////////////////
    /**
     * 提交GET请求，curl方法
     * @param string  $url       请求url地址
     * @param mixed   $data      GET数据,数组或类似id=1&k1=v1
     * @param array   $header    头信息
     * @param int     $timeout   超时时间
     * @param int     $port      端口号
     * @return array             请求结果,
     *                            如果出错,返回结果为array('error'=>'','result'=>''),
     *                            未出错，返回结果为array('result'=>''),
     */
    $curl_get = function ($url, $data = array(), $header = array(), $timeout = 5, $port = 80)
    {
        $ch = curl_init();
        if (!empty($data)) {
            $data = is_array($data)? http_build_query($data): $data;
            $url .= (strpos($url,'?')?  '&': "?") . $data;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_POST, 0);
        //curl_setopt($ch, CURLOPT_PORT, $port);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);


        $result = array();
        $result['result'] = curl_exec($ch);
        if (0 != curl_errno($ch)) {
            $result['error']  = "Error:\n" . curl_error($ch);
            $result['debug_info'] = curl_getinfo($ch);
        }
        curl_close($ch);

        return $result;
    };


    /**
     * 提交POST请求，curl方法
     * @param string  $url       请求url地址
     * @param mixed   $data      POST数据,数组或类似id=1&k1=v1
     * @param array   $header    头信息
     * @param int     $timeout   超时时间
     * @param int     $port      端口号
     * @return string            请求结果,
     *                            如果出错,返回结果为array('error'=>'','result'=>''),
     *                            未出错，返回结果为array('result'=>''),
     */
    $curl_post = function ($url, $data = array(), $header = array(), $timeout = 5, $port = 80)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        //curl_setopt($ch, CURLOPT_PORT, $port);
        !empty ($header) && curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

        $result = array();
        $result['result'] = curl_exec($ch);
        if (0 != curl_errno($ch)) {
            $result['error']  = "Error:\n" . curl_error($ch);
            $result['debug_info'] = curl_getinfo($ch);
        }
        curl_close($ch);

        return $result;
    };
    /**
     *  curl put 上传文件
     * @param <type> $url          请求url
     * @param <type> $file         文件位置
     * @param <type> $filehandle   文件resource
     * @param <type> $header       请求头
     * @param <type> $timeout      请求超时限制
     * @param <type> $port         请求端口
     * @return string
     */
    $curl_put = function ($url, $file, $filehandle, $header = array(), $timeout = 5, $port = 80)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        !empty ($header) && curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_INFILE, $filehandle);
        curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file));
        $result = array();
        $result['result'] = curl_exec($ch);
        if (0 != curl_errno($ch)) {
            $result['error']  = "Error:\n" . curl_error($ch);
            $result['debug_info'] = curl_getinfo($ch);
        }
        curl_close($ch);

        return $result;
    };
    

    ////////////////// main 2
    $result = array('run via curl', 'run vai curl 123' . rand(), $args);

    switch ($method) {
    case 'post':
        $result = $curl_post($url, $data, $header);
        break;
    case 'get':
        $result = $curl_get($url, $data, $header);
        break;
    default:
        break;
    }
       
    
    // 演示结果返回
    $json_result = json_encode($result);
    return $json_result;



};

///////////////////////////.////////////////////
// $_function = null;
$_register_name = 'run_http';
$_enable = true;
return array($_function, $_register_name, $_enable);


