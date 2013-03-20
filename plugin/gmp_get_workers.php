<?php

  /**
   * 获取workers信息
   *
   * @return array('worker_ip1'=> array('worker_name1', 'worker_name2', ...), ...)
   */
$_function = function ($job)
 {

	$result = "Get_workers:\n";
	
	//10.207.0.247:4730,10.207.0.248:4730,10.207.16.251:4730
	//$server_list = array('10.207.0.247:4730','10.207.0.248:4730','10.207.16.251:4730');
	//$server_list = $gearmand_servers;
	
    global $g_workerman;
    $server_list = $g_workerman->servers();

	foreach($server_list as $s){
		$s_ip_port = explode(':',$s);
		if (count($s_ip_port) == 1) {
            		$s_ip_port[1] = 4730;
        	}
		$server_address[] = array($s_ip_port[0],$s_ip_port[1]);
	}
	//print_r($server_address);


	// workers
	$all_workers_number = 0;


	$node_workers_arr = array();
	foreach($server_address as $server){

		$ncmd = "/usr/local/gearman/bin/gearadmin --host=".$server[0]." --port=".$server[1] ." --workers";
		$rlines = array();
		$rvar = 0;
		$rstr = exec($ncmd, $rlines, $rvar);

		//print_r($rlines);
		
		//node_workers_arr[$server[0]] = array();

		foreach($rlines as $line){
			$node_info = explode(' ',$line);
			//print_r($node_info);

			
			
			$node_numbers = count($node_info);
			//echo $node_numbers;
			
			for($i = 4; $i < $node_numbers; $i++){
				if(strpos($node_info[$i], 'gmwn_') ===  false
                   && strpos($node_info[$i], 'project_') === false
                   && isset($node_info[$i])) {
					$node_workers_arr[$node_info[1]][$node_info[$i]] = $node_info[$i];
					//$all_workers_number++;
				}
			}
				
			//print_r($node_workers_arr = array_filter($node_workers_arr));
		}
	}
	
	//$node_workers_arr = array_unique($node_workers_arr);
	if(false){		
		print_r($node_workers_arr);	
		
		foreach($node_workers_arr as $key=>$node){
			//print_r($node);
			if(isset($key)){
				echo $key.":\n";
				$j=0;
				foreach($node as $node_workers){
					$result.= $node_workers." ";
					$j++;
					if(isset($node_workers)){
						$all_workers_number++;
					}
				}
				$result.="\n";
				$result.="nums: ".$j++;
				$result.="\n\n";
			}
		}
		
		$result.="There are ".$all_workers_number." workers in the cluster.\n";
		echo $result;
	}
	return json_encode($node_workers_arr);
 };

$_register_name = 'get_workers';
$_enable = true;
return array($_function, $_register_name, $_enable);

