<?php
	function MD5_DIR($dir){

    		if (!is_dir($dir)){
			
			if(!is_file($dir)){
			
       				return false;
			}else{
				return md5_file($dir);	
			}
    		}
    
    		$filemd5s = array();
    		$d = dir($dir);

    		while (false !== ($entry = $d->read())){

        		if ($entry != '.' && $entry != '..'){

             			if (is_dir($dir.'/'.$entry)){

                 			$filemd5s[] = MD5_DIR($dir.'/'.$entry);
             			}else{

                 			$filemd5s[] = md5_file($dir.'/'.$entry);
             			}
         		}
    		}
    		
		$d->close();
    		return md5(implode('', $filemd5s));
	}

	$worker = new GearmanWorker();
	$worker->addServer('localhost',3333);
	$worker->addFunction('file_md5','my_dir_file_md5');

	echo "The worker(file_md5) started, waiting for job...\n";
	
	while(true){
		echo $worker->work();
	}

	function my_dir_file_md5($job){
		
		echo "The worker(file_md5) starts working...\n";
		
		$file_md5_value = MD5_DIR($job->workload());
		
		if(!$file_md5_value){
			echo "The file does not exits.\n";
			return false;
		}

		echo "The file's md5 is ".$file_md5_value."\n";
			
		return $file_md5_value;
	}		

?>
