<?php
	
	for($j=1;$j<=10;$j++){
		$m[$j] = 0;
	}
	
	$arr = array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10'); 

	$count=1000000;
	for($i=0;$i<$count;$i++){
		$n = array_rand($arr);
	
		switch($n){
			case '0': $m[1]++;break;
			case '1': $m[2]++;break;
                        case '2': $m[3]++;break;
                        case '3': $m[4]++;break;
                        case '4': $m[5]++;break;
                        case '5': $m[6]++;break;
                        case '6': $m[7]++;break;
                        case '7': $m[8]++;break;
                        case '8': $m[9]++;break;
                        case '9': $m[10]++;break;
			default: echo $n."  error,exit!\n";
		}

	}
	for($p=1;$p<=10;$p++){
		echo $p.":".$m[$p]/$count."\n";
	}
?> 

