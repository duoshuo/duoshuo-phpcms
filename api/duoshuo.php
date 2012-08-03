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
require_once PHPCMS_PATH.'duoshuo/Exception.php';

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

$plugin = Duoshuo_Phpcms::getInstance();

try{
	if ($_SERVER['REQUEST_METHOD'] == 'POST'){
		$server = new Duoshuo_LocalServer($plugin);

		$input = $_POST;
		if (get_magic_quotes_gpc()){
			foreach($input as $key => $value)
				$input[$key] = stripslashes($value);
		}
		$server->dispatch($input);
	}
}
catch (Exception $e){
	Duoshuo_LocalServer::sendException($e);
	exit;
}