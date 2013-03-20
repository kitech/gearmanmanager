<?php
  $worker= new GearmanWorker();
  $worker->addServer();
  $worker->addFunction("strlen", "title_function");
  while ($worker->work());
   
  function title_function($job)
  {
	return strlen($job->workload());  
  }
?>
