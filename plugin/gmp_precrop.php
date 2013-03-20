<?php
  /*
   * TODO 路径信息变量化
   */
require_once (GMW_ROOT.  '/class/gmagick.php');

$_function = function ($job)
{

        //获得数据
        $gm_info_json = $job->workload();
        $gm_info = json_decode($gm_info_json);

        $pid = $gm_info->pid;
        $path = $gm_info->fpath;
        $domain = $gm_info->domain;
        $project_crop_info = $gm_info->project_crop_info;
        $attachdir = $gm_info->attachdir;
        $ips = $gm_info->ips;
        $module = $gm_info->module;
        $preDir = $gm_info->preDir;
        $assign_api = $gm_info->assign_api;
        $mem_servers = $gm_info->memcache_servers;
        $m_key = $gm_info->memcache_key;



        //初始化Rsync gearman 客户端，将预处理文件异步存储
        $client = new GearmanClient();


        $memcache = new Memcache();
        $memcache_info = explode(':', $mem_servers);
        $memcache_server = $memcache_info[0];
        $memcache_port = (int)$memcache_info[1];

        $memcache->connect($memcache_server, $memcache_port) or die ('Memcache could not connect');

        $value = file_get_contents('/data1/vhosts/photo.kitech.com.cn/htdocs/gearman/test.gif');
        var_dump(strlen($value));


        //获取任务分发IP
        global $server_lists;
        $server_list = $server_lists;

        foreach($server_list as $s){
            $s_ip_port = explode(':',$s);
            if (count($s_ip_port) == 1) {
                $s_ip_port[1] = 4730;
            }
            // $client->addServer($s_ip_port[0], $s_ip_port[1]);
        }


        //获取原文件
//        $value = $memcache->get($m_key);
//
//        if(!$value){
//
//            $key = $domain . '/' . $path;
//
//            $content = $memcache->get($key);
//            if ($content && substr($content, 0, 8) == 'ZLINKTO:') {
//                // this is a resource link meta file
//                $link_to = substr($content, 8);
//                // $resfile = $attachdir . '/' . $link_to;
//                $inter_key = $domain . '/' . $link_to;
//                $content = $memcache->get($inter_key);
//            }
//
//            $value =  $content;
//        }

        foreach($project_crop_info as $crop_info){

            $gmagick = new plugin_gmagick();
            $gmagick->read_image_blob($value);
            $format = $gmagick->get_image_format();

            var_dump($gmagick->get_image_width(), $gmagick->get_image_height());

            if($format == 'gif'){

                $count = $gmagick->get_image_index();
var_dump($count);
                $gmagick->set_image_index(0);
                for($i=0; $i<=$count; $i++){
                    $gmagick->set_image_index($i);

                    $gmagick->crop_image(50, 50, 0, 0);
                    //$gmagick->crop_image($crop_info[0], $crop_info[1], $crop_info[2], $crop_info[3]);

                }

            }else{
                $gmagick->crop_image($crop_info[0], $crop_info[1], $crop_info[2], $crop_info[3]);

            }

            // $crop_image_data = (string)$gmagick->get_image();
            $crop_image_data = $gmagick->get_image_data();


	    file_put_contents('/tmp/new', $crop_image_data);

            $gmagick->destroy();





            $crop_path =  $path. '_c'.$crop_info[0].'X'.$crop_info[1].'X'.$crop_info[2].'X'.$crop_info[3];


            //Memcache缓存
            $crop_key = str_replace($preDir, "", $crop_path);
            $crop_res = $memcache->set($crop_key, $crop_image_data, 0);

            $rsync_info = array();

            $rsync_info['ckey'] = $crop_key;
            $rsync_info['ips'] = $ips;
            $rsync_info['module'] = $module;
            $rsync_info['preDir'] = $preDir;

            $rsync_info['content_file'] = $crop_key;
            $rsync_info['assign_api'] = $assign_api;


            $client->doBackground('rsync', json_encode($rsync_info));

        }

        $result = 'precrop-sucess';
        $result = json_encode($result);

        return $result;
};


    $_register_name = 'precrop';
    $_enable = true;
return array($_function, $_register_name, $_enable);

