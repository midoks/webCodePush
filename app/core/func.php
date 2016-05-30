<?php

/**
 * @dir string 获取目录地址和相关信息
 */
function wcp_dir_list($dir){
	$list = array();
	if(is_dir($dir)){
		$_list = scandir($dir);
		foreach($_list as $k=>$v){
			$arr = array();
			if($v == '.' || $v == '..'){
			} else {
				$arr['fn'] = $v;
				$abspath = rtrim($dir,'/').'/'.$v;

				if(is_dir($abspath)){
					$arr['type'] = 'dir';
				} else if (is_file($abspath)){
					$arr['type'] = 'file';
				} else {
					$arr['type'] = 'unknown';
				}

				$arr['abspath'] = rtrim($dir,'/').'/'.$v;
				$list[] = $arr;
			}
		}
	}
	return $list;
}

?>