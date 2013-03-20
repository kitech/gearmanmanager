<?php

$worker = new GearmanWorker();
$bret = $worker->addServer('127.0.0.1', 4730);
$bret = $worker->removeServers();
$bret = $worker->addServer('127.0.0.1', 4730);

var_dump($bret);

$testworker_func = function () {
    $rdn = rand();
    return "testworker_result,{$rdn}\n";
};

$funcs = array();
function load_worker_func ()
{
    if (empty($funcs)) {
        $dummy_func = include(__DIR__ . '/../plugin/gmp_dummy.php');
        global $funcs;
        $funcs[0] = $dummy_func[0];
    }

    return $funcs[0];
    // return $dummy_func;
}

function testworker_wrapper($job)
{

    // global $testworker_func; // 没有这个就是不行啊
    // $abc = $testworker_func[0]();
    $func = load_worker_func();
    $abc = $func($job);
    return $abc;

    $rdn = rand();
    return "testworker_resultdir,{$rdn}\n";
}

$worker->addFunction('testworker', 'testworker_wrapper');

while (true) {
    try {
        $bret2 = $worker->work();
    } catch (Exception $e) {
        print_r($e);
    }
    var_dump($bret2);
}

