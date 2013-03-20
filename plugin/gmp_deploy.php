<?php

/**
 * 源代码分发
 * 
 * @param $pnames 项目名称，photo.kitech.com.cn
 * @param $ops   操作类型，UPDATE, DELETE
 * @param $rpaths 相对路径
 * @param $contents 文件内容
 * @param $md5sums 文件校验
 * @param $fmodes 文件权限属性
 **/
$_function = function ($job)
 {
    $json_args = $job->workload();
    $args = json_decode($json_args, true);
    
    $akeys = array_keys($args);
    print_r($akeys);
    foreach ($akeys as $idx => $key) {
        if ($key == 'ops' || $key == 'pnames' || $key == 'rpaths' || $key == 'md5sums' || $key == 'fmodes') {
            print_r($args[$key]);
        }
    }

    $base_dir = '/data1/vhosts';
    $docroot_prefix = 'htdocs';

    $ops = $args['ops'];
    $pnames = $args['pnames'];
    $rpaths = $args['rpaths'];
    $contents = $args['contents'];
    $b64contents = $args['b64contents'];
    $md5sums = $args['md5sums'];
    $fmodes = $args['fmodes'];
    
    $tcnt = count($ops);
    $dcnt = $tcnt;
    $ecnt = 0;
    foreach ($ops as $idx => $op) {
        $pname = $pnames[$idx];
        $rpath = $rpaths[$idx];
        $content = $contents[$idx];
        $md5sum = $md5sums[$idx];
        $fmode = $fmodes[$idx];


        /// simple reformat,两侧的目录结构不一致，需要修改一下。
        $rpath = str_replace('trunk', $docroot_prefix, $rpath);


        $file_path = $base_dir . '/' . $pname . '/' . $rpath;
        $file_dir = dirname($file_path);
        if (!file_exists($file_dir)) {
            mkdir($file_dir, 0755, true);
        }

        switch ($op) {
        case 'UPDATE':
            $bret = file_put_contents($file_path, $content);
            if ($bret === FALSE) {
                $dcnt --;
                $ecnt ++;
            } else {
                $bret = chmod($file_path, octdec($fmode));
                $bret = chown($file_path, fileowner($base_dir));
                $bret = chgrp($file_path, filegroup($base_dir));
            }
            break;
        case 'DELETE':
            $bret = unlink($file_path);
            if ($bret === FALSE) {
                $dcnt --;
                $ecnt ++;
            }
            break;
        default:
            echo "Unknown op: ${op}\n";
            $dcnt --;
            $ecnt ++;
            break;
        }
    }

    echo "INFO: deploy total: ${tcnt}, succ: ${dcnt}, faild: ${ecnt}\n";

    // $result = "deploy";
    $result = array('total' => $tcnt, 'success' => $dcnt, 'faild' => $ecnt);
    $json_result = json_encode($result);
    return $json_result;
 };

$_register_name = 'deploy';
$_enable = true;
return array($_function, $_register_name, $_enable);


