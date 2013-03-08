<?php
/**
 * 多说插件 api处理
 *
 * @version		$Id: api.php 0 10:17 2012-7-23
 * @author 		shen2
 * @copyright	Copyright (c) 2012 - , Duoshuo, Inc.
 * @link		http://dev.duoshuo.com
 */
defined('IN_PHPCMS') or exit('No permission resources.'); 
error_reporting(E_ALL);
ini_set('display_startup_errors', 1);
ini_set('display_errors', 1);
require_once PHPCMS_PATH.'duoshuo/Client.php';
require_once PHPCMS_PATH.'duoshuo/Abstract.php';
require_once PHPCMS_PATH.'duoshuo/Phpcms.php';
require_once PHPCMS_PATH.'duoshuo/LocalServer.php';
//临时代替load_app_class
require_once PHPCMS_PATH.'duoshuo/duoshuo_commentmeta_model.class.php';

if (!extension_loaded('json'))
	include_once PHPCMS_PATH.'/duoshuo/compat_json.php';

function nocache_headers(){
	header("Pragma:no-cache\r\n");
	header("Cache-Control:no-cache\r\n");
	header("Expires:0\r\n");
}

if (!headers_sent()) {
	nocache_headers();//max age TODO:
	header('Content-Type: text/html; charset=utf-8');
}

if (!class_exists('Duoshuo_Phpcms')){
	$response = array(
			'code'			=>	30,
			'errorMessage'	=>	'Duoshuo plugin hasn\'t been activated.'
	);
	echo json_encode($response);
	exit;
}


//向插件表中插入数据
$plugin = array(
		'name'=>'多说',
		'identification'=>'duoshuo',
		'appid'=>'10232',
		'description'=>'多说评论插件',
		'dir'=>'duoshuo',
		'copyright'=>'duoshuo.com',
		'setting'=>'',
		'iframe'=>'',
		'version'=>'0.1.0',
		'disable'=>'0'
);

$pluginDb = pc_base::load_model('plugin_model');
$pluginDbVar = pc_base::load_model('plugin_var_model');
pc_base::load_app_func('global');

$installedInfo = $pluginDb->select(array('identification'=>'duoshuo'));
if($installedInfo){
	showmessage('存在已安装的多说数据，无需反复安装。');
	exit;
}
$pluginid = $pluginDb->insert($plugin,TRUE);

$plugin_data = array(
		'plugin_var' => array(
			array(
					'title' => 'duoshuo_short_name',
					'description'	=>	'多说二级域名',
					'fieldname'=>'duoshuo_short_name',
					'fieldtype'=>'text',
					'value' =>	'',
					'formattribute'=>'size="50"',
					'listorder'=>'1',
			),
			array(
					'title' => 'duoshuo_secret',
					'description'	=>	'多说站点密钥',
					'fieldname'=>'duoshuo_secret',
					'fieldtype'=>'text',
					'value' =>	'',
					'formattribute'=>'size="50"',
					'listorder'=>'2',
			),
			array(
					'title' => 'duoshuo_sync_lock',
					'description'	=>	'多说正在同步时间(0表示同步正常完成)',
					'fieldname'=>'duoshuo_sync_lock',
					'fieldtype'=>'text',
					'value' =>	'0',
					'formattribute'=>'size="50"',
					'listorder'=>'3',
			),
			array(
					'title' => 'duoshuo_last_sync',
					'description'	=>	'已完成的最后同步记录id',
					'fieldname'=>'duoshuo_last_sync',
					'fieldtype'=>'text',
					'value' =>	'0',
					'formattribute'=>'size="50"',
					'listorder'=>'4',
			),
		)
);

//向插件变量表中插入数据
if(is_array($plugin_data['plugin_var'])) {
	foreach($plugin_data['plugin_var'] as $config) {
		$plugin_var = array();
		$plugin_var['pluginid'] = $pluginid;
		foreach($config as $_k => $_v) {
			if(!in_array($_k, array('title','description','fieldname','fieldtype','setting','listorder','value','formattribute'))) continue;
			if($_k == 'setting') $_v = array2string($_v);
			$plugin_var[$_k] = $_v;
		}
		$pluginDbVar->insert($plugin_var);				
	}
}		
plugin_install_stat($plugin_data['appid']);
setcache($plugin_data['identification'], $plugin,'plugins');

if($info = $pluginDbVar->select(array('pluginid'=>$pluginid))) {
	$plugin_data =  $pluginDb->get_one(array('pluginid'=>$pluginid));
	foreach ($info as $_value) {
		$plugin_vars[$_value['fieldname']] = $_value['value'];
	}
	setcache($plugin_data['identification'].'_var', $plugin_vars,'plugins');
}
showmessage('多说安装成功');