<?php

$_function = function ($job)
{
	$ncmd = "free";
    $rlines = array();
    $rvar = 0;
    $rstr = exec($ncmd, $rlines, $rvar);

    // sleep(3);

    return serialize($rlines);

};

$_register_name = 'get_free';
$_enable = true;
return array($_function, $_register_name, $_enable);


