<?php
	$client = new GearmanClient();
	$client->addServer('127.0.0.1','1111');
	$client->do("mtest",'hello');
	print "\n";
	

?>
