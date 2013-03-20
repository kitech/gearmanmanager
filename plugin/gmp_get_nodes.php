<?php

  /**
   * 获取gearmand节点列表
   * 
   * @return array('ip1:port1'=>pid1, 'ip2:port2'=>pid2, ...)
   */
$_function = function ($job)
{

	$result = "Get_nodes:\n";
	//10.207.0.247:4730,10.207.0.248:4730,10.207.16.251:4730
	//$server_list = array('10.207.0.247:4730','10.207.0.248:4730','10.207.16.251:4730');
    global $g_workerman;
    $server_list = $g_workerman->servers();

	foreach($server_list as $s){
		$s_ip_port = explode(':',$s);
		if (count($s_ip_port) == 1) {
            		$s_ip_port[1] = 4730;
        	}
		$server_address[] = array($s_ip_port[0],$s_ip_port[1]);
	}
	


	$node_gearmand_arr = array();
	foreach($server_address as $server){

		$path = "/usr/local/gearman/bin";
		
		$ncmd = $path."/gearadmin --host=".$server[0]." --port=".$server[1] ." --getpid";
		$rlines = array();
		$rvar = 0;
		$rstr = exec($ncmd, $rlines, $rvar);
		

		if(isset($rlines))
			$node_gearmand_arr[$server[0].':'.$server[1]] = trim($rstr);
			
	}	
	
	//print_r($node_gearmand_arr);
	
	if(false){
		foreach($node_gearmand_arr as $key=>$node){
			if(isset($key)){
				$result .= $key.": ";
				
				if(!isset($node_gearmand_arr[$key]))
					$result .= "This node has no gearmand service.\n";
				else
					$result .= "(Pid) ".$node_gearmand_arr[$key]."\n";
			}
		}
	}
	
	return json_encode($node_gearmand_arr);


};

$_register_name = 'get_nodes';
$_enable = true;
return array($_function, $_register_name, $_enable);

