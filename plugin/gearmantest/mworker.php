<?php
	
	$worker = new GearmanWorker();
	$worker->addServer('127.0.0.1',1111);
	$worker->addFunction('mtest','setmecvalue');
	
	while($worker->work());

	function setmecvalue($job){

		return 'Here is the libmemcache-servers demo:'.$job->workload();
	}
?>
