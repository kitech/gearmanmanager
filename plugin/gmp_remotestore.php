<?php

$_function = function ($job){
		
		$result = "remote_store";
		$json_result = json_encode($result);

		$store_ROOT = '/data1/storage/';
		
		$file_contents = $job->workload();
		$store_info = json_decode($file_contents);

		$store_folder = $store_ROOT . '/' . $store_info->folder;
		$store_file = $store_folder . '/' . $store_info->filename;
		$picture_stream = $store_info->picture_contents;	
  	
		
        mkdir($store_folder, '755', true);
       	touch($store_file);
		
		file_put_contents($store_file,$picture_stream);
		
		return $json_result;
  };

	$_register_name = 'remotestore';
	$_enable = true;
return array($_function, $_register_name, $_enable);

