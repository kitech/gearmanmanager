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
   *
   * @param array $server
   * @param string $sql
   */
$_function = function ($job) /* use (&$g_workerman)  */ /* 可选，把全局变量带进worker函数，可以直接使用 */ 
{
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);

    $server = $args['server'];
    $sql = $args['sql'];

    $dsn = "mysql:host=10.207.26.253;port=3307;dbname=photo_kitech.com.cn;";

    try {
        $pdo = new PDO($dsn, 'mysql', 'ComeOn*@!)');
        $recs = array();

        foreach ($pdo->query($sql) as $row) {
            $recs[] = $row;
        }

        $pdo = null;
    } catch (PDOException $e) {
        print_r($e);
    }

    $result = array('sqlquery', 'sqlquery123' . rand(), $args);
    $result = $recs;

    // 演示结果返回
    $json_result = json_encode($result, JSON_UNESCAPED_UNICODE);
    // $job->sendStatus(100, strlen($json_result));
    // $job->sendData($json_result);
    // $job->sendComplete($json_result);
    $job->setReturn(GEARMAN_SUCCESS);
    return $json_result;
};

///////////////////////////.////////////////////
// $_function = null;
$_register_name = 'sqlquery';
$_enable = true;
return array($_function, $_register_name, $_enable);
// array('func_obj', 'reg_name', 'enable');

