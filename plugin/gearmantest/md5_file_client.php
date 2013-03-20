<?php
	$client = new GearmanClient();
	$client->addServer('localhost','3333');
	
	$file_dir = "asdasda:asdasd";

	echo "The client(file_md5) is sending...\n";

		
	$result = $client->do("file_md5",$file_dir);
	
	
	if(!$result){
		echo "Task failed.\n";
		exit;
	}

	while($client->returnCode() != GEARMAN_SUCCESS);

	echo "\n";
	echo "Task(file_md5) has done.\n";

?>
