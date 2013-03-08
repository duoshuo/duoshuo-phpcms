<?php
class Duoshuo_LocalServer{
	
	protected $response = array();
	
	protected $plugin;
	
	public function __construct($plugin){
		$this->plugin = $plugin;
	}
	
	/**
	 * 从服务器pull评论到本地
	 * 
	 * @param array $input
	 */
	public function sync_log($input = array()){
		$syncLock = $this->plugin->getOption('sync_lock');//检查是否正在同步评论 同步完成后该值会置0
		if(!isset($syncLock) || $syncLock > time()- 900){//正在或15分钟内发生过写回但没置0
			$response = array(
					'code'	=>	Duoshuo_Exception::SUCCESS,
					'response'=> '同步中，请稍候',
			);
			return;
		}
		
		$this->plugin->updateOption('sync_lock',  time());
		
		$last_sync = $this->plugin->getOption('last_sync');
		
		$limit = 50;
		
		$params = array(
			'since_id' => $last_sync,
			'limit' => $limit,
			'order' => 'asc',
		);
		
		$client = $this->plugin->getClient();
		
		$posts = array();
		$affectedThreads = array();
		$max_sync_id = 0;
		
		do{
			$response = $client->getLogList($params);
		
			$count = count($response['response']);
			
			foreach($response['response'] as $log){
				switch($log['action']){
					case 'create':
						$affected = $this->plugin->createPost($log['meta']);
						break;
					case 'approve':
					case 'spam':
					case 'delete':
						$affected = $this->plugin->moderatePost($log['action'], $log['meta']);
						break;
					case 'delete-forever':
						$affected = $this->plugin->deleteForeverPost($log['meta']);
						break;
					case 'update'://现在并没有update操作的逻辑
					default:
						$affected = array();
				}
				//合并
				if(is_array($affected))
					$affectedThreads = array_merge($affectedThreads, $affected);
			
				if (strlen($log['log_id']) > strlen($max_sync_id) || strcmp($log['log_id'], $max_sync_id) > 0)
					$max_sync_id = $log['log_id'];
			}
			
			$params['since_id'] = $max_sync_id;
				
		} while ($count == $limit);//如果返回和最大请求条数一致，则再取一次
		
		
		if (strlen($max_sync_id) > strlen($last_sync) || strcmp($max_sync_id, $last_sync) > 0)
			$this->plugin->updateOption('last_sync', $max_sync_id);
		
		$this->plugin->updateOption('sync_lock',  0);
		
		$this->plugin->updateCommentsCount($affectedThreads);
		
		$this->plugin->updateOption('sync_lock',  1);
		
		$this->response['code'] = Duoshuo_Exception::SUCCESS;
	}
	
	public function update_option($input = array()){
		//duoshuo_short_name
		//duoshuo_secret
		//duoshuo_notice
		foreach($input as $optionName => $optionValue)
			if (substr($optionName, 0, 8) === 'duoshuo_'){
				$this->plugin->updateOption(substr($optionName, 8), $optionValue);
			}
		$this->response['code'] = 0;
	}
	
	public function sendResponse(){
		echo json_encode($this->response);
	}
	
	public function dispatch($input){
		if (!isset($input['signature']))
			throw new Duoshuo_Exception('Invalid signature.', Duoshuo_Exception::INVALID_SIGNATURE);
		
		$signature = $input['signature'];
		unset($input['signature']);
		
		ksort($input);
		$baseString = http_build_query($input, null, '&');
		
		$expectSignature = base64_encode(hash_hmac('sha1', $baseString, $this->plugin->getOption('secret'), true));
		if ($signature !== $expectSignature)
			throw new Duoshuo_Exception('Invalid signature, expect: ' . $expectSignature . '. (' . $baseString . ')', Duoshuo_Exception::INVALID_SIGNATURE);
		
		$method = $input['action'];
		
		if (!method_exists($this, $method))
			throw new Duoshuo_Exception('Unknown action.', Duoshuo_Exception::OPERATION_NOT_SUPPORTED);
		
		$this->$method($input);
		$this->sendResponse();
	}
	
	static function sendException($e){
		$response = array(
			'code'	=>	$e->getCode(),
			'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
	}
}
