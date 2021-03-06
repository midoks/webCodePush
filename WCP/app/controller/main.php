<?php  

/**
 * 系统管理
 * 作者 midoks
 * 创建时间 2016-11-05
 */

class mainController extends baseController{

	public $rsync_config 	= '--exclude=*svn* --exclude=*.log* --exclude=*conf*';
	public $rsyncd_config 	= '--delete --exclude=*svn* --exclude=*.log* --exclude=*conf*';
	public $source_view 	= array('php', 'html', 'htmls');

	public function __construct(){
		parent::__construct();

		$this->conf['hidden'] = explode(',', $this->conf['hidden']);
		$tmp = explode(',', $this->conf['filter']);
		$trsync = '';
		foreach ($tmp as $k => $v) {
			$trsync .= "--exclude=*{$v}* ";
		}
		//var_dump($trsync);

		$this->rsync_config = $trsync;
		$this->rsyncd_config = '--delete '.$trsync;

		$tmp = explode(',', $this->conf['source_view']);
		$this->source_view = $tmp;
	}

	public function index(){

		$list = $this->getUserProjectList();
		foreach ($list as $key => $value) {
			$t = $list[$key]['project_target'];
			$list[$key]['project_target'] = str_replace(',', "<br/>", $t);
		}
		$this->list = $list;
		$this->load('project_list');
	}
	
	//文件页
	public function _dir(){
		//var_dump($_GET);
		if(!isset($_GET['project'])){
			exit('参数有误');
		}

		$project = $_GET['project'];

		$_info = array();
		$project_file = WCP_ROOT.'/conf/project/'.$project.'.php';

		if(file_exists($project_file)){
			$_info = include($project_file);
			$_info['project_name'] = $project;
		} else {
			exit('项目已经不存在');
		}

		$this->project_info = $_info;
		$project_dir = $_info['project_source'];
		if (isset($_GET['abspath']) && file_exists($_GET['abspath'])){
			$project_dir = $_GET['abspath'];

			if ($this->userinfo['type'] !=0 ){//检查权限
				if (substr($project_dir, 0, strlen($_info['project_source'])) != $_info['project_source']){
					$this->jump($this->buildUrl('index', ''));
				}
			}
		}

		$list = wcp_fileinfo_list($project_dir);
		$list = wcp_filter_list($list, $this->conf['hidden']);
		$list = wcp_file_sort($list);
		
		$this->list = $list;
		$this->load('dir');
	}

	//项目同步
	public function _copy(){

		if(($this->userinfo['type'] != 0) && ($this->userinfo['type'] != 1)){
			exit('no acl ok!');
		}

		if (empty($_POST)){
			exit('error request!!!');
		}

		if(!isset($_GET['project'])){
			exit("error request!!!\r\nwhy not set var target!!!");
		}

		$file = wcp_list_filter_prefex($_POST, 'file');
		$_list = array();
		if (isset($_POST['submit'])){
			$get_list = wcp_list_filter_prefex($_POST, 'checkbox');	
		} else {
			$get_list = wcp_list_filter_prefex($_POST, 'single');
		}

		$get_list_key = array_keys($get_list);
		foreach ($get_list_key as $k) {
			$_list[] = $file[$k];
		}
		$get_list_value = array_values($get_list);

		$project = $_GET['project'];
		$rsync_info = '';

		include(ABSPATH.'/app/core/cp_op.php');

		if(isset($get_list_value[0]) && $get_list_value[0] == 'S'){ //查看源码
			$file_ex = explode('.', $_list[0]);
			$file_type = $file_ex[count($file_ex) - 1];
			//var_dump($file_type);
			$source_code = file_get_contents($_list[0]);
			if(in_array($file_type, $this->source_view)){
				$source_code = str_replace("<?", htmlentities("<?"), $source_code);
				$this->source_code = $source_code;
				$this->load('code');
			} else {
				echo "Unauthorized access";
			}

			exit;
		} else if (isset($get_list_value[0]) && $get_list_value[0] == 'D'){ //删除项目不存在的文件 && 同步项目
			$rsync_info .= $this->rsync_file($project, $_list, 'delete');
		} else {
			$rsync_info .= $this->rsync_file($project, $_list, 'add'); //同步项目
		}

		$this->rsync_info = $rsync_info;
		$this->load('copy');
	}

	//同步文件
	/**
	 * rsync同步操作
	 * @param $project_name 项目名
	 * @param $_list 同步列表
	 * @param $type add|delete
	 * @param $rsync_return 是否返回同步信息
	 */
	private function rsync_file($project_name, $_list, $type='add', $rsync_return = true){
		$_info 			= array();

		$project_file 	= WCP_ROOT.'/conf/project/'.$project_name.'.php';
		if(file_exists($project_file)){
			$_info 					= include($project_file);
			$_info['project_name'] 	= $project_name;

			if(!file_exists($_info['project_source'])){
				exit('代码目录不存在!!!');
			}
		} else {
			exit('项目已经不存在');
		}
		
		
		$local_project_dir 	= $_info['project_source'];	
		$target_addrs	= $_info['project_target'];
		$target_addrs 	= explode(',', $target_addrs);

		$loginName 	= $this->getLoginName();
		$op_log 	= WCP_ROOT."/logs/".$loginName.'_'.date('Y-m-d')."_op.log";

		$rsync_info = '';
		foreach ($target_addrs as $target_addr) {
			foreach ($_list as $key => $value) {
				$relative_position_dir 	= str_replace($local_project_dir, '', $value);
				$relative_position_dir 	= trim($relative_position_dir, '/');
				$target_addr 			= trim($target_addr, '/');
				$target_service_addr 	= $target_addr.'/'.$relative_position_dir;

				if($type == 'add'){
					if (is_dir($value)){
						$target_service_addr = dirname($target_service_addr);
					}

					//var_dump($this->rsync_config,$value,$target_service_addr);exit;

					$cmd = "rsync -avz {$this->rsync_config} {$value} {$target_service_addr} 2>>{$op_log}";
				} else if($type == 'delete'){

					$target_service_addr = dirname($target_service_addr).'/';
					$value = dirname($value).'/';

					$cmd = "rsync -avz {$this->rsyncd_config} {$value} {$target_service_addr} 2>>{$op_log}";
				} else {

					if (is_dir($value)){
						$target_service_addr = dirname($target_service_addr);
					}
					$cmd = "rsync -avz {$this->rsync_config} {$value} {$target_service_addr} 2>>{$op_log}";
				}
		
				exec($cmd, $ret, $status);
				if ($rsync_return){
					foreach ($ret as $rk => $rv) {
						$rsync_info .=  $rv."<br />";
					}
				}
				$rsync_info .= "<br />";

				$value = "<b class='need_copy_value'>{$value}</b>";

				if($type == 'add'){
					$tmp = "rsync -avz {$value} {$target_service_addr}";
					if ($status != 0 ){
						$rsync_info .= "<span style='color:red;'>{$tmp} FAIL</span><br>";
						$this->rycLog($op_log, "{$tmp} FAIL");
					} else {
						$rsync_info .= "<span style='color:blue;'>{$tmp} SUCCESS</span><br>";
						$this->rycLog($op_log, "{$tmp} SUCCESS");
					}
				} else if($type == 'delete'){
					$tmp = "rsync -avz --delete {$value} {$target_service_addr}";
					if ($status != 0 ){
						$rsync_info .= "<span style='color:red;'>{$tmp} FAIL</span><br>";
						$this->rycLog($op_log, "{$tmp} FAIL");
					} else {
						$rsync_info .= "<span style='color:blue;'>{$tmp} SUCCESS</span><br>";
						$this->rycLog($op_log, "{$tmp} SUCCESS");
					}
				}
			}
		}
		return $rsync_info;
	}
	
}

?>
