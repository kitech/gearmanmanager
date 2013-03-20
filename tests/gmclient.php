<?php
/* gmclient.php --- 
 * 
 * Author: liuguangzhao
 * Created: 2012-04-25 22:40:52 +0800
 * Version: $Id$
 */
/*
  Usage: /path/to/php /path/to/gmclient.php
  简单功能测试gearman client实现
 */

echo "Starting\n";


$client= new GearmanClient();
$client->addServer("10.207.16.251", 1234);
// $client->addServer('127.0.0.1', 1235);                                                                                                   
// $res = $client->doBackground("gmworker_node_127.0.0.1_free", "Hello World!");                                                            
$res = $client->doHigh("gmwn_10.207.15.55_free", "Hello World!");

// $gmt = $client->addTask("gmworker_node_127.0.0.1_free", "Hello World!");                                                                 
// $res = $client->runTasks();                                                                                                              


echo $res. "\n";
// $pres = unserialize($res);                                                                                                               
// print_r($pres);                                                                                                                          

// $stat = $client->jobStatus($res);                                                                                                        
// print_r($stat); 
