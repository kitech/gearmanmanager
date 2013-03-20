<?php

$_function = function ($job)
{

	$ncmd = "df -T";
    $rlines = array();
    $rvar = 0;
    $rstr = exec($ncmd, $rlines, $rvar);

    // sleep(5);

    return serialize($rlines);

};

$_register_name = 'get_disk_filesystem';
$_enable = true;
return array($_function, $_register_name, $_enable);

