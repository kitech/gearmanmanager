<?php
	$gclient = new GearmanClient();
	
	$gclient->addServer("127.0.0.1","4730");

	print $gclient->do("reverse","hello kearney!");
	print "\n";
?>
