<?php
	
	for($j=1;$j<11;$j++){
		$m[$j] = 0;
	}
	$count=10000;
	for($i=0;$i<$count;$i++){
		srand((double) microtime() * 1000000);
		$n = rand(1,10);
		
		switch($n){
			case 1: $m[1]++;break;
			case 2: $m[2]++;break;
                        case 3: $m[3]++;break;
                        case 4: $m[4]++;break;
                        case 5: $m[5]++;break;
                        case 6: $m[6]++;break;
                        case 7: $m[7]++;break;
                        case 8: $m[8]++;break;
                        case 9: $m[9]++;break;
                        case 10: $m[10]++;break;
			default: echo "error,exit!\n";
		}

	}
	for($p=1;$p<11;$p++){
		echo $p.":".$m[$p]/$count."\n";
	}
?> 

