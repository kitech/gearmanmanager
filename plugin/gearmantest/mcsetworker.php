<?php
	
	$worker = new GearmanWorker();
	$worker->addServer('127.0.0.1',1111);
	$worker->addFunction('setmec','setmecvalue');
	while($worker->work());

	function setmecvalue($job){
		$memcache = new Memcache;
        	$memcache->connect('127.0.0.1','2222') or die('Could not connect');
       	 	$memcache->set('key','kearney ye ye ye!',0,60);
		$keyval = $memcache->get('key');
		return 'Here is the remote memcache states'.$job->workload().$keyval;
	}
?>
