<?php
  $worker= new GearmanWorker();
  $worker->addServer();
  $worker->addFunction("title", "title_function");
  while ($worker->work());
   
  function title_function($job)
  {

	$arr = array('a'=>1,'b'=>2,'c'=>3);

	$ary = array('i','love','you');


    return '1++++'.json_encode($arr).'2+++'.json_encode($ary).ucwords(strtolower($job->workload()));
  }
?>
