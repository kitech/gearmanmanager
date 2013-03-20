<?php
  /**
   * 自动安装定时任务
   *
   * @author guangzhao1@kitech.com.cn
   * $Id: gmp_crontab.php 19087 2012-12-24 09:32:35Z guangzhao $
   */

require_once(__DIR__ . '/crontab/CrontabManager.php');
require_once(__DIR__ . '/crontab/CronEntry.php');
require_once(__DIR__ . '/crontab/CliTool.php');
use php\manager\crontab\CrontabManager;

class CrontabImpl
{
  private $gmjob =  null;
  private $args = null;

  private $on = null;
  private $cmd = null;
  private $name = null;
  private $comments = null;
  private $comment_prefix = 'gmcron_';
  private $event_file_dir = '';
  private $event_file_prefix = 'gmcron_event_args_';
  private $event_file_path = '';
  private $php_cmd = '/usr/local/sinasrv2/bin/php /usr/local/php/bin/php';
  
  private $hcron = null;

  public $bret = false;
  public $rmsg = '';
  public $rid = '';

  function __construct($job)
  {

    $this->event_file_dir = __DIR__ . '/../var/tmp';
    if (!file_exists($this->event_file_dir)) {
      mkdir($this->event_file_dir);
    }
    $this->event_file_dir = realpath($this->event_file_dir);
    echo $this->event_file_dir . "\n";

    $this->args = $job->workload();
    $this->args = json_decode($this->args, true);
  
    print_r($this->args);

    $this->hcron = new CrontabManager();


    /// 设定默认的参数值
    $ary = array('op', 'on', 'cmd', 'args', 'minute', 'hour', 'month_day', 'month', 'week_day', 'name');
    foreach($ary as $akey) {
      if (!array_key_exists($akey, $this->args)) {
	$this->args[$akey] = '';
      } else {
	$this->args[$akey] = is_array($this->args[$akey]) ? $this->args[$akey] : trim($this->args[$akey]);
      }
    }

    $cron_obj = $this->args;

    $this->on = $cron_obj['on'];
    if (empty($cron_obj['on'])) {
      $on_list = array($cron_obj['minute'], $cron_obj['hour'], $cron_obj['month_day'],
		       $cron_obj['month'], $cron_obj['week_day']);
      foreach($on_list as $idx => &$val) {
	trim($val);
      }
      $this->on = implode(' ', $on_list);
    }

    $this->cmd = $cron_obj['cmd'];
    if (!empty($cron_obj['args'])) {
      $this->cmd .= ' ' . $cron_obj['args'];
    }


    $this->name = $cron_obj['name'];
    $this->comments = array($this->name);

    $this->event_file_path = $this->event_file_dir . '/' . $this->event_file_prefix . $this->name . '.json';
  }

  public function add() 
  {
    try {

      // overwrite
      $php_cmd_list = explode(' ', $this->php_cmd);
      foreach ($php_cmd_list as $php_cmd) {
	if (file_exists($php_cmd)) {
	  $this->cmd = $php_cmd . ' ' 
	    . realpath(__DIR__ . '/../cmdline/cron_event_router.php') . ' ' . $this->name;
	  break;
	}
      }

      if (!$this->hasCron($this->name)) {
	$cjob = $this->hcron->newJob();
	$cjob->on($this->on);
	$cjob->addComments($this->comments);
	$cjob->doJob($this->cmd);
	$this->hcron->add($cjob);
	$this->hcron->save();
      }

      $evt_args = array('repeat' => true, 
			'fname' => $this->name,
			'args' => $this->args['args'],
			'mtime' => date('Y-m-d H:i:s')
			);

      $json_evt_args = json_encode($evt_args);
      file_put_contents($this->event_file_path, $json_evt_args);

      $this->bret = true;
      $this->rmsg = $this->on . ' ' . $this->cmd;
      $this->rid = '';
    } catch (UnexpectedValueException $e) {
      $this->bret = false;
      $this->rmsg = $e->__toString();
    }
  }

  public function update()
  {
    $this->delete();
    $this->add();
  }

  public function delete()
  {
    $tmp_lock_file = tempnam(sys_get_temp_dir(), 'gmcm.lock.');
    $lock_fp = fopen($tmp_lock_file, "r+");
    if (!flock($lock_fp, LOCK_EX)) {
      $this->rmsg = "can not get modify lock.";
      return;
    }

    $cjobs = $this->hcron->listJobs();
    $cjobs_lines = explode("\n", $cjobs);
    if (empty($cjobs_lines)) {
      $this->bret = true;
      $this->bmsg = 'No entry';
      return;
    }

    $deletions = array();
    foreach ($cjobs_lines as $line_no => $cron_line) {
      $cron_line = trim($cron_line);
      if (empty($cron_line)) {
	continue;
      }
	  
      if (substr($cron_line, 0, 1) == '#') {
	// comment line
	$cname = substr($cron_line, 2, strlen($cron_line)-2);
	if ($cname == $this->name) {
	  echo "Delete cron job: $this->name, ${cjobs_lines[$line_no+1]} \n";
	  $deletions[$line_no] = 1;
	  $deletions[$line_no + 1] = 1;
	}
      }
    }

    foreach ($cjobs_lines as $line_no => $cron_line) {
      if (array_key_exists($line_no, $deletions)) {
	unset($cjobs_lines[$line_no]);
	continue;
      }
      if (empty($cron_line)) {
	unset($cjobs_lines[$line_no]);
	continue;
      }
    }
    print_r($cjobs_lines);
    $cjobs_str = implode("\n", $cjobs_lines) . "\n";
    
    if (!empty($cjobs_str)) {
      $tmp_file = tempnam(sys_get_temp_dir(), 'gmcnew.cron.');
      file_put_contents($tmp_file, $cjobs_str);

      ob_start();
      system('crontab ' . $tmp_file, $ret_value);
      $output = ob_get_clean();

    }
    $this->bret = true;
    $this->rmsg = $output;

    flock($lock_fp, LOCK_UN);
    fclose($lock_fp);
    unlink($tmp_lock_file);
    unlink($tmp_file);
    if (file_exists($$this->event_file_path)) {
      unlink($this->event_file_path);
    }
  }

  public function get()
  {
    $cjobs = $this->hcron->listJobs();
    $this->bret = true;
    $this->rmsg = $cjobs;
  }

  public function count()
  {
    $this->bret = true;
    $this->rmsg = 'Not impled';
  }
  
  public function hasCron($name)
  {
    $cjobs = $this->hcron->listJobs();
    if (empty($cjobs)) {
      return false;
    }

    $cjob_lines = explode("\n", $cjobs);

    
    foreach ($cjob_lines as $cron_line) {
      if (substr($cron_line, 0, 1) == '#') {
	// comment line
	$cname = substr($cron_line, 2, strlen($cron_line)-2);
	if ($cname == $name) {
	  return true;
	}
      }
    }

    return false;
  }
  
};

/**
 * op=C|U|R|D
 * on => '* * * * *'
 * minute => '' Y-m-d H:i:s
 * hour => ''
 * month_day => ''
 * month => ''
 * week_day => ''
 * cmd => ''
 * args = ''
 * name => '' --> crontab's comment
 */

$_function = function ($job) 
{
    $args = $job->workload();
    $cron_obj = json_decode($args, true);

    $cron_impl = new CrontabImpl($job);

    switch ($cron_obj['op']) {
    case 'C':
        $cron_impl->add();
        break;
    case 'U':
        $cron_impl->update();
        break;
    case 'D':
        $cron_impl->delete();
        break;
    case 'R':
    default:
        $cron_impl->get();
    break;
    }

    $result = array('success' => $cron_impl->bret, 'msg' => $cron_impl->rmsg, 'id' => $cron_impl->rid);
    if (empty($result)) {
        $result = "crontab-" . rand(1, 9999);
    }
    $json_result = json_encode($result);
    return $json_result;
};

$_register_name = 'crontab';
$_enable = true;
return array($_function, $_register_name, $_enable);

?>