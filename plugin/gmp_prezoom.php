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
        $project_zoom_info = $gm_info->project_zoom_info;
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

        //$memcache->connect($memcache_server, $memcache_port) or die ('Memcache could not connect');


        //获取任务分发IP
        global $server_lists;
        $server_list = $server_lists;

        foreach($server_list as $s){
            $s_ip_port = explode(':',$s);
            if (count($s_ip_port) == 1) {
                $s_ip_port[1] = 4730;
            }
            //$client->addServer($s_ip_port[0], $s_ip_port[1]);
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
        $value = file_get_contents('/data1/vhosts/photo.kitech.com.cn/htdocs/gearman/test.gif');
        foreach($project_zoom_info as $zoom_info){


            $gmagick = new plugin_gmagick();
            $gmagick->read_image_blob($value);
            $format = $gmagick->get_image_format();

            $count = $gmagick->get_image_index();
            $gmagick->set_image_index(0);
            $pwidth = $zoom_info[0];
            $pheight = $zoom_info[1];

            //获取图片原始比例
            $autoWH = $gmagick->get_image_width()/$gmagick->get_image_height();
            if ($pwidth > $pheight) {  //如果宽度大于高度，以宽度为基准,否则以高度为基准
                $width = $pwidth;
                $height = $pwidth / $autoWH;
            } else {
                $height = $pheight;
                $width = $pheight * $autoWH;
            }

            if($format == 'gif'){
                for($i=0; $i<=$count; $i++){
                    $gmagick->set_image_index($i);
                    $gmagick->thumb_nail_image(50, 50);
                }
                $gmagick->set_image_index(0);
            }else{
                $gmagick->thumb_nail_image($width, $height);
            }

            $zoom_image_data = (string)$gmagick->get_image_data();
            file_put_contents('/tmp/new_zoom', $zoom_image_data);


            $gmagick->destroy();

            $zoom_path =  $path. '_s'.$width.'X'.$height;

            //Memcache缓存
            $zoom_key = str_replace($preDir, '', $zoom_path);
            $zoom_res = $memcache->set($zoom_key, $zoom_image_data, 0);

            $rsync_info = array();

            $rsync_info['ckey'] = $zoom_key;
            $rsync_info['ips'] = $ips;
            $rsync_info['module'] = $module;
            $rsync_info['preDir'] = $preDir;
            $rsync_info['content_file'] = $zoom_key;
            $rsync_info['assign_api'] = $assign_api;


            $client->doBackground('rsync', json_encode($rsync_info));

        }

        $result = 'prezoom-sucess';
        $result = json_encode($result);

        return $result;
};

    $_register_name = 'prezoom';
    $_enable = true;
return array($_function, $_register_name, $_enable);

