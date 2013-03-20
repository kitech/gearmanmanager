<?php
	$client = new GearmanClient();
	$client->addServer('127.0.0.1','3333');
	
	$logdata = "hehehhehehehe";

	$client->do('work_log',$logdata);

	echo "Task done.";


?>
