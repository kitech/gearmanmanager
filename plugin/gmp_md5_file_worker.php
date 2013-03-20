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

$_function = function ($job)
{
		
		echo "The worker(file_md5) starts working...\n";
		
		$file_md5_value = MD5_DIR($job->workload());
		
		if(!$file_md5_value){
			echo "The file does not exits.\n";
			return false;
		}

		echo "The file's md5 is ".$file_md5_value."\n";
			
		return $file_md5_value;
};
	
	$_register_name = 'md5_file_worker';
	$_enable = true;
return array($_function, $_register_name, $_enable);

