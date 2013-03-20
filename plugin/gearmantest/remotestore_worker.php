<?php

	$worker= new GearmanWorker();
  	$worker->addServer('127.0.0.1','1210');
  	$worker->addFunction("127.0.0.1_store", "picture_store");
	
	
	while ($worker->work());
   
	

	function createFolder($path,$chmod){
		if (!file_exists($path)){
			
			createFolder(dirname($path),$chmod);
                        @mkdir($path);
                        @chmod($path,$chmod);
                }
        }

	function picture_store($job){
		
		
		$store_ROOT = '/usr/local/gearmantest/root/';
		
		$file_contents = $job->workload();
		$store_info = json_decode($file_contents);

		$store_folder = $store_ROOT . '/' . $store_info->folder;
		$store_file = $store_folder . '/' . $store_info->filename;
		$picture_stream = $store_info->picture_contents;	
  	
		
		createFolder($store_folder,'777');
       		touch($store_file);
		
		file_put_contents($store_file,$picture_stream);
		
	}
?>
