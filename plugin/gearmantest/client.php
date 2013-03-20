<?php

  $client= new GearmanClient();
  $client->addServer("127.0.0.1","4730");
  
  $arr = array('a'=>'hello','b'=>'world','c'=>'me~');

  $ary = array('u','fuck','that');
  
  $newstring = "";
	 for($i=0;$i<pow(2,25);$i++){
		$newstring .= "1";
	}

  //print $client->do("title", json_encode($arr).json_encode($ary));
  print $client->do("strlen",$newstring);
 
	print "\n";
?>
