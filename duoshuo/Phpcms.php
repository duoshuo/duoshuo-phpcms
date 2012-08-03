<?php

class Duoshuo_Phpcms extends Duoshuo_Abstract{
	
	const VERSION = '0.2.0';
	
	public static $app_identification = 'duoshuo';
	
	public static $appid = 10232;
	
	public static $commentTag = '{dede:duoshuo/}';
	
	public static $approvedMap = array(
		'pending' => '0',
		'approved' => '1',
		'deleted' => '-1',
		'spam' => '-1',
		'thread-deleted'=>'-1',
	);
	public static $actionMap = array(
		'create' => '0',
		'update' => '0',
		'approve' => '1',
		'delete' => '-1',
		'spam' => '-1',
	);
	
	//数据库连接
	private $comment_db,
		$comment_setting_db,
		$comment_data_db,
		$comment_table_db,
		$comment_check_db,
		$plugin_var_db,
		$duoshuo_commentmeta_db;
	
	/**
	 *
	 * @var array
	 */
	public static $errorMessages = array();
	
	public static $EMBED = false;
	
	function __construct() {
		$this->comment_db = pc_base::load_model('comment_model');
		$this->comment_setting_db = pc_base::load_model('comment_setting_model');
		$this->comment_data_db = pc_base::load_model('comment_data_model');
		$this->comment_table_db = pc_base::load_model('comment_table_model');
		$this->comment_check_db = pc_base::load_model('comment_check_model');
		$this->plugin_var_db = pc_base::load_model('plugin_var_model');
		$this->duoshuo_commentmeta_db =  new duoshuo_commentmeta_model();
		pc_base::load_app_func('global','comment');
		parent::__construct();
	}
	
	public static function getInstance(){
		if (self::$_instance === null)
			self::$_instance = new self();
		return self::$_instance;
	}
	
	public static function timezone(){
		global $cfg_cli_time;
		return $cfg_cli_time;
	}
	
	/**
	 * 保存多说设置
	 * @param 键 $key
	 * @param 值 $value
	 * @param 键名 $info
	 * @param 类型 $type
	 * @param 组别 $groupid
	 */
	public function updateOption($key, $value, $description = NULL,$type = NULL){
		global $dsql;
		$oldvalue = $this->getOption($key);
		if($oldvalue===NULL){
			$data = array();
			$data['title'] = "duoshuo_$key";
			$data['fieldname'] = "duoshuo_$key";
			$data['value'] = $value;
			$data['description'] = isset($description) ? $description : '多说设置项'; //默认值
			$data['type'] = isset($type) ? $type : 'text'; //默认值
			
			$option = $this->plugin_var_db->insert($data,TRUE);
		}
		else{
			$data = array();
			$data['value'] = $value;
			if(isset($info)){
				$data['description'] = $description;
			}
			if(isset($type)){
				$data['type'] = $type;
			}
			$option = $this->plugin_var_db->update($data,array('fieldname'=>"duoshuo_$key"));
		}
		
		
		$this->options[$key] = $value;
		return $option;
	}
	
	public function getOption($key){
		if(isset($this->options[$key])){
			return $this->options[$key];
		}else{
			$row = $this->plugin_var_db->get_one(array('fieldname'=>"duoshuo_$key"));
			if(empty($row)){
				return NULL;
			}
			
			$this->options[$key] = $row['value'];
			return $row['value'];
		}
	}
	
	public static function currentUrl(){
		$sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
		$php_self	 = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
		$path_info	= isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
		$relate_url   = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self . (isset($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : $path_info);
		return $sys_protocal . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '') . $relate_url;
	}
	
	static function sendException($e){
		$response = array(
			'code'	=>	$e->getCode(),
			'errorMessage'=>$e->getMessage(),
		);
		echo json_encode($response);
		exit;
	}
	
	public function createPost($meta){
		//查找同步记录
		$synced = $this->duoshuo_commentmeta_db->get_one(array('post_id'=>$meta['post_id']));
		if($synced){//create操作的评论，没同步过才处理
			return null;
		}
		if(!empty($meta['thread_key'])){
			$commentid = $meta['thread_key'];
			$data = get_comment_api($commentid);
			if(empty($data)) {
				return null;//无相关文章信息
			}
			$title = $data['title'];
			$url = $data['url'];
			
			unset($data);
			$title = new_addslashes($title); 
			
			//参照phpcms原始流程 by duoshuo
			$comment = $this->comment_db->get_one(array('commentid'=>$commentid), 'tableid, commentid');
			if (!$comment) { //评论不存在
				//取得当前可以使用的内容数据表
				$row = $this->comment_table_db->get_one('', 'tableid, total', 'tableid desc');
				$tableid = $row['tableid'];
				if ($row['total'] >= 1000000) {
					//当上一张数据表存的数据已经达到1000000时，创建新的数据存储表，存储数据。
					if (!$tableid = $this->comment_table_db->creat_table()) {
						$this->msg_code = 4;
						return null;	//修改返回值 by duoshuo
					}
				}
				list($modules, $contentid, $siteid) = id_decode($commentid);
				//新建评论到评论总表中。
				$comment_data = array('commentid'=>$commentid, 'siteid'=>$siteid, 'tableid'=>$tableid, 'display_type'=>($data['direction']>0 ? 1 : 0));
				if (!empty($title)) $comment_data['title'] = $title;
				if (!empty($url)) $comment_data['url'] = $url;
				if (!$this->comment_db->insert($comment_data)) {
					$this->msg_code = 5;
					return null;	//修改返回值 by duoshuo
				}
			} else {//评论存在时
				$tableid = $comment['tableid'];
			}
			if (empty($tableid)) {
				$this->msg_code = 1;
				return null;	//修改返回值 by duoshuo
			}
			//为数据存储数据模型设置 数据表名。
			$this->comment_data_db->table_name($tableid);
			//检查数据存储表。
			if (!$this->comment_data_db->table_exists('comment_data_'.$tableid)) {
				//当存储数据表不存时，尝试创建数据表。
				if (!$tableid = $this->comment_table_db->creat_table($tableid)) {
					$this->msg_code = 2;
					return null;	//修改返回值 by duoshuo
				}
			}
			//向数据存储表中写入数据。
			$data['commentid'] = $commentid;
			$data['siteid'] = $siteid;
			$data['ip'] = $meta['ip'];
			$data['status'] = self::$approvedMap[$meta['status']];
			$data['creat_at'] = strtotime($meta['created_at']);
			$data['username'] = addslashes(strip_tags($meta['author_name']));
			//对评论的内容进行关键词过滤。
			$data['content'] = addslashes(strip_tags($meta['message']));
			$badword = pc_base::load_model('badword_model');
			$data['content'] = $badword->replace_badword($data['content']);//原始关键词过滤功能仍然保留 by duoshuo
			//通过parent_id找到原参数$id
			$id = null;
			if($meta['parent_id']){
				$parent = $this->duoshuo_commentmeta_db->get_one(array('post_id'=>$meta['parent_id']));
				if($parent){
					$id = $parent['cid'];
					$tableid = $parent['tableid'];
				}
			}
			if ($id) {
				$this->comment_data_db->table_name($tableid);
				$row = $this->comment_data_db->get_one(array('id'=>$id));
				if ($row) {
					pc_base::load_sys_class('format', '', 0);
					if ($row['reply']) {
						$data['content'] = '<div class="content">'.str_replace('<span></span>', '<span class="blue f12">'.$row['username'].' '.L('chez').' '.format::date($row['creat_at'], 1).L('release').'</span>', $row['content']).'</div><span></span>'.$data['content'];
					} else {
						$data['content'] = '<div class="content"><span class="blue f12">'.$row['username'].' '.L('chez').' '.format::date($row['creat_at'], 1).L('release').'</span><pre>'.$row['content'].'</pre></div><span></span>'.$data['content'];
					}
					$data['reply'] = 1;
				}
			}
			
			$row = $this->comment_table_db->get_one('', 'tableid, total', 'tableid desc');
			$tableid = $row['tableid'];
			if ($commentDataId = $this->comment_data_db->insert($data, true)) {
				//开始更新数据存储表数据总条数
				$count = $this->comment_data_db->count();
				$this->comment_table_db->edit_total($tableid, $count);//改进原系统的计数方式 by duoshuo
				//开始更新评论总表数据总数
				$sql['lastupdate'] = SYS_TIME;
				//只有在评论通过的时候才更新评论主表的评论数
				if ($data['status'] == 1) {
					$count = $this->comment_data_db->count(array('commentid'=>$commentid,'status' => 1));
					$sql['total'] = $count;//改进原系统的计数方式 by duoshuo
				}
				$this->comment_db->update($sql, array('commentid'=>$commentid));
				$this->msg_code = 0;
				//记录反向回流结果
				$metaModel = new duoshuo_commentmeta_model();
				
				$userid = $metaModel->insert(array(
						'post_id'	=> $meta['post_id'],
						'tableid'	=> $tableid,
						'cid'		=> $commentDataId,
				), TRUE);
				return array($commentid);
			} else {
				$this->msg_code = 3;
				return null;
			}
		}
		return null;
	}
	
	public function moderatePost($action, $postIdArray){
		$aidList = array();
		foreach($postIdArray as $postId){
			$synced = $this->duoshuo_commentmeta_db->get_one(array('post_id'=>$postId));
			if(!is_array($synced)){//非create操作的评论，同步过才处理
				continue;
			}
			$tableid = $synced['tableid'];
			$cid = $synced['cid'];
			$this->comment_data_db->table_name($tableid);
			$commentData = $this->comment_data_db->get_one(array('id'=>$cid));
			
			if(!is_array($commentData)){
				continue;
			}
			
			$commentData = $this->comment_data_db->update(array('status'=>self::$actionMap[$action]),array('id'=>$cid));
			//开始更新评论总表数据总数
			$count = $this->comment_data_db->count(array('commentid'=>$commentData['commentid'],'status' => 1));
			$sql['lastupdate'] = SYS_TIME;
			$sql['total'] = $count;//改进原系统的计数方式 by duoshuo
			$this->comment_db->update($sql, array('commentid'=>$commentData['commentid']));
			$aidList[] = $commentData['commentid'];
		}
		return $aidList;
	}
	
	public function deleteForeverPost($postIdArray){
		$aidList = array();
		foreach($postIdArray as $postId){
			$synced = $this->duoshuo_commentmeta_db->get_one(array('post_id'=>$postId));
			if(!is_array($synced)){//非create操作的评论，同步过才处理
				continue;
			}
			$tableid = $synced['tableid'];
			$cid = $synced['cid'];
			$this->comment_data_db->table_name($tableid);
			$commentData = $this->comment_data_db->get_one(array('id'=>$cid));
				
			if(!is_array($commentData)){
				continue;
			}
			$this->comment_data_db->delete(array('id'=>$cid, 'commentid'=>$commentData['commentid']));
			//开始更新评论总表数据总数
			$count = $this->comment_data_db->count(array('commentid'=>$commentData['commentid'],'status' => 1));
			$sql['lastupdate'] = SYS_TIME;
			$sql['total'] = $count;//改进原系统的计数方式 by duoshuo
			$this->comment_db->update($sql, array('commentid'=>$commentData['commentid']));
			$aidList[] = $commentData['commentid'];
		}
		return $aidList;
	}
	
	public function refreshThreads($aidList){
		//phpcms下暂无此功能
		/*foreach($aidList as $aid){
			$arc = new Archives($aid);
			$arc->MakeHtml();
		}*/
	}
}