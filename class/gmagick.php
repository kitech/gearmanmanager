<?php
/**
 * 处理图片类
 * @author :jide@kitech.com.cn
 * @author :guangzhao1@kitech.com.cn
 * Version: $Id: gmagick.php 5735 2012-07-17 11:06:20Z kaikai $
 *
 */

define('WM_UNKNWON', 0);
define('WM_CENTER', 1);
define('WM_EAST', 2);
define('WM_SOUTHEAST', 3);
define('WM_WEST', 4);
define('WM_SOUTHWEST', 5);
define('WM_NORTHWEST', 6);
define('WM_NORTH', 7);
define('WM_SOUTH', 8);

class plugin_gmagick
{
	private $image;
	private $file;
	/**
	 * 设置要操作的文件
	 * @param  $file 文件路径
	 * */
	public function set_file($file)
	{
		$this->file=$file;
	}
	/**
	 * 构造函数
	 * */
	public function __construct()
	{
		$this->image=new Gmagick();

	}
	/**
	 * 销毁对象
	 */
	public function destroy()
	{
	    $this->image->destroy();
	}
	/**
	 * 重设图片尺寸
	 * @param $width 图片宽度
	 * @param $height 图片高度
	 * @param $filter 滤镜值
	 * @param $blur	灰度值
	 * */

	public function get_image_width(){
		return $this->image->getimagewidth();
	}

	public function get_image_height(){
		return $this->image->getimageheight();
	}


	public function resize_image($width,$height,$filter=null,$blur=1 )
	{
		$this->image->resizeImage($width,$height,$filter,$blur);
	}
	/**
	 * 生成缩略图
	 * @param	$width 宽度
	 * @param	$height	高度
	 * */
	public function thumb_nail_image($width,$height )
	{
		$this->image->thumbnailImage($width,$height);
	}
	/**
	 * 按百分比缩放图片
	 * @param $percent
	 */
	public function thumb_percent($percent)
	{
	    $percent=$percent/100;
	    $width=$this->image->getimagewidth();
	    $this->image->thumbnailImage($width*$percent,0);
	}
	/**
	 * 添加水印
	 *@param	$water 水印图
	 *@param	$x		x相对位置
	 *@param	$y		y相对位置
	 *@param	$compose	合成
	 * */
	public function water_image($water,$gravity,$offset_w=0,$offset_h=0,$compose=1)
	{
		$position=$this->get_water_position($this->image,$water,$gravity,$offset_w,$offset_h);
		$this->image->compositeimage($water,$compose,$position['x'],$position['y']);
	}
	/**
	 *	生成边框
	 * @param  $color 	颜色
	 * @param  $width	宽度
	 * @param  $height	高度
	 */
	public function border_image($color,$width,$height )
	{
		$this->image->borderimage($color,$width,$height);
	}
	/**
	 * 读取文件内容
	 * @param	$content		文件内容
	 * */
	public function read_image_blob($content)
	{
		$this->image->readimageblob($content);
	}
	/**
	 * 设置图片的格式
	 * @param  $format
	 */
	public function set_image_format($format)
	{
	    $this->image->setimageformat($format);
	}
	/**
	 * 得到图片的格式
	 */
	public function get_image_format()
	{
	    $format=$this->image->getimageformat();
		$format=strtolower($format);
		return $format;
	}
	/**
	 * 得到图片的层次位置(对gif图而言)
	 */
	public function get_image_index()
	{
	    return $this->image->getimageindex();
	}
	/**
	 * 设置图片的层次位置(对gif图而言)
	 * @param $index
	 */
	public function set_image_index($index)
	{
	    $this->image->setimageindex($index);
	}
	/**
	 * 裁剪图片
	 * @param  $width  	裁剪图宽度
	 * @param  $height 	裁剪图高度
	 * @param  $x		x方向偏移量
	 * @param  $y		y方向偏移量
	 */
	public function crop_image($width,$height,$x,$y )
	{
        $w=$this->image->getimagewidth();
        $h=$this->image->getimageheight();
        if(($w>$width)&&($w>$x))
	        $this->image->cropimage($width,$height,$x,$y);
	}
	/**
	 * 返回Gmagick对象
	 * */
	public function get_image()
	{
        $this->image->setCompressionQuality(90);
        // return $this->image->getimageblob();
		return $this->image;
	}

    /**
     * 返回Gmagick中的图片数据
     */
    public function get_image_data()
    {
        $d = $this->get_image()->getimageblob();
        return $d;
    }

	/**
	 * 输出图片
	 * @param $mime_type
	 */
	public function echo_image($mime_type)
	{
        $time = 60*60*24*30;
        header("Content-type: ".$mime_type);
        header("Cache-Control:max-age=".$time);
		header("Pragma:"); // Tells HTTP 1.0 clients to cache
        header("Expires:".gmdate("D, d M Y H:i:s", time() + $time) . " GMT");
        echo $this->get_image_data();
    }
	/**
	 * 计算位置
	 * @param	$image  图片
	 * @param	$water	水印图
	 * @param	$gravity	位置
	 * */
	public function get_water_position($image,$water,$gravity,$offset_w,$offset_h)
	{
		$image_h=$image->getimageheight()+$offset_h;
		$image_w=$image->getimagewidth()+$offset_w;
		$water_h=$water->getimageheight();
		$water_w=$water->getimagewidth();
		switch ($gravity) {
        case 'Center':
            $w=($image_w-$water_w)/2;
            $h=($image_h-$water_h)/2;
            return array('x'=>$w,'y'=>$h);
            break;

        case 'East':
            $w=($image_w-$water_w);
            $h=($image_h-$water_h)/2;
            return array('x'=>$w,'y'=>$h);
            break;

        case 'NorthEast':
            $w=($image_w-$water_w);
            $h=$offset_h;
            return array('x'=>$w,'y'=>$h);
            break;

        case 'SouthEast':
            $w=($image_w-$water_w);
            $h=($image_h-$water_h);
            return array('x'=>$w,'y'=>$h);
            break;

        case 'West':
            $w=$offset_w;
            $h=($image_h-$water_h)/2;
            return array('x'=>$w,'y'=>$h);
            break;

        case 'SouthWest':
            $w=$offset_w;
            $h=($image_h-$water_h);
            return array('x'=>$w,'y'=>$h);
            break;

        case 'NorthWest':
            $w=$offset_w;
            $h=$offset_h;
            return array('x'=>$w,'y'=>$h);
            break;

        case 'North':
            $w=($image_w-$water_w)/2;
            $h=$offset_h;
            return array('x'=>$w,'y'=>$h);
            break;

        case 'South':
            $w=($image_w-$water_w)/2;
            $h=($image_h-$water_h);
            return array('x'=>$w,'y'=>$h);
            break;

        default:
            return array('x'=>0,'y'=>0);
        }
	}
}

