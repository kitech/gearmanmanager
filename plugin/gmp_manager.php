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
   *
   * @param $op
   * @param 
   * @return true|false
   */
$_function = function ($job)
{
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    print_r($args);

    $result = "manager,monitor";

    // 演示结果返回
    $json_result = json_encode($result);
    return $json_result;
};

$_register_name = 'worker_manager';
$_enable = true;
return array($_function, $_register_name, $_enable);

