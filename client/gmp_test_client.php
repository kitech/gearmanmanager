<?php
		// jiakai


		// make sure that is a ip
		function is_ip($gonten){
			$ip = explode(".",$gonten);
			for($i=0;$i<count($ip);$i++){
				if($ip[$i]>255){
					return false;
               }
			}
			return ereg("^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$",$gonten);
		}

		//run woker with worker's name, gearmand's ip address and test times
		//php test_client.php -name xxx -nums 5 -servers 127.0.0.1:4730(,127.0.0.1:1234,127.0.0.2:1234)

		$client = new GearmanClient();
		$client_input = "";

		$opts = getopt('name:nums:servers:');


		$optname = $opts['name'];
		$nums = $opts['nums'];
		$servers = $opts['servers'];

		if(!$servers) $servers = '127.0.0.1:4730';
		$severlist = explode(",", $servers);


		foreach($serverlist as $server){
			$sinfo = explode(':', $sip);
			if(!is_ip($sinfo[0]) || !(is_int($info[1]) && count(intval($info[1]))==4) ){
				echo "Please type in the right IP list, ip:prot(example: '127.0.0.1:4730,127.0.0.2:1234').\n";
				exit();
			}
			$client->addServer($sinfo[0],$sinfo[1]);
		}


		if(!$nums || !is_int($nums)) $nums = 1;
		$nums = intval($nums);

		if($optname!=''){
			for($i=0;$i<$nums;$i++){
				$client->do($name,$client_input);
			}
		}else{
			echo "Please type command in the right way. 'php test_client.php [-name xxx] [-nums integer][servers ip_address_list]'.\n";
			exit();
		}

?>
