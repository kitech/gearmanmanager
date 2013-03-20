<?php

$_function = function ($job)
{
	
		return strrev($job->workload());
};
$_register_name = 'my_reverse_function';
$_enable = true;

return array($_function, $_register_name, $_enable);


