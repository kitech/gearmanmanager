<?php

$_function = function ($job)
{
	$ncmd = "ps aux";
    $rlines = array();
    $rvar = 0;	
    $rstr = exec($ncmd, $rlines, $rvar);

    // sleep(5);
    return json_encode($rlines); 
};

$_register_name = 'get_processes';
$_enable = true;
return array($_function, $_register_name, $_enable);


