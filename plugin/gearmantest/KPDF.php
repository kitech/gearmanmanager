<?php
	
	$IP = "127.0.0.1";
	

///Get file from ARGS

	$args = getopt('f:');
	$filename = $args['f'];

///file Steram

	$myfile = file_get_contents($filename);
	
///Normal file md5

	$file_key_md5 = md5_file($filename);


///Upload file md5

//	$upload_file_md5 = md5_file($_FILES['userfile']['tmp_name']);


	$FOLDER_NUMS = 320;	
	$folder_arr = array(); 	

	
	for($i=0;$i<$FOLDER_NUMS;$i++){
		array_push($folder_arr,$i);
	}


	$folder_key = array_rand($folder_arr);
	$folder_key_md5 = md5($folder_key);
	

	$Main_Storage_Location =  $IP.':root/'.$folder_key_md5.'/'.$file_key_md5;
        
///UPLOAD location

//	     $upload_location = $IP.':root/'.$folder_key_md5.'/'.$upload_file_md5;


	echo "Main storage location: ".$Main_Storage_Location."\n";
	
///Gearman Romote Files Store
	$client = new GearmanClient();
	$client->addServer('127.0.0.1','1210');
	
	$storeinfo = array('folder'=>$folder_key_md5,'filename'=>$file_key_md5,'picture_contents'=>$myfile);	
	$client->doBackground($IP.'_store',json_encode($storeinfo));
?> 


