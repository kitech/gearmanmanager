<?php
	$worker = new GearmanWorker();
	$worker->addServer("127.0.0.1","4730");
	$worker->addFunction('reverse','gofuck');
	while($worker->work());
	
	function gofuck($job){
		return "yes:".strrev($job->workload());
}
?>
