<?php
	$worker = new GearmanWorker();
	$worker->addServer('127.0.0.1','3333');
	$worker->addFunction('work_log','record');

	while($worker->work());

	function record($job){
		
		$filename = 'work.log';	


		if(file_exists($filename) && is_writable($filename)){
			
			$f = fopen($filename,'w');
			fwrite($f,$job->workload());			
			fclose($f);
			return true;

		}else{
			return false;
		}
	}
?>
