<?php

$_function = function ($job)
{
    $jload = $job->workload();
    $pload = json_decode($jload);

    $log_dir = '/data1/dlogs';
    $filename = $log_dir . '/distapp.log';
    createFolder($log_dir, '755');
    touch($filename);

    $retv = false;
    if (file_exists($filename) && is_writable($filename)) {
        $f = fopen($filename, 'a+');
        // fwrite($f,$job->workload());
        fwrite($f, $pload . "\n");
        fclose($f);
        // return true;
        $retv = true;
    }else{
        // return false;
    }

    $jretv = json_encode($retv);
    return $jretv;
};

$_register_name = 'log_worker';
$_enable = true;
return array($_function, $_register_name, $_enable);

