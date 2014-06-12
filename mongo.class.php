<?php
/**
*	Mongo数据库操作类 API
*/

class mongo_db{
	private $db_host;
	private $db_user;
	private $db_password;
	private $db_name;
	private $db_prefix;
	private $result;
	private $db_port = '';
	private $db = "";
	private $is_close = true;
	private $mongodb_connection = "";

	/**
	 * 打开数据库连接,有可能不真实连接数据库
	 * @param $config	数据库连接参数
	 * 			
	 * @return void
	 */
	public function open($config) {
		$this->config = $config;
		if($config['autoconnect'] == 1) {

			$this->connect();
		}
	}

	//用外部定义的变量初始类，并连接数据库
	function __construct($dbconfig){
		$this->_init_mongodb($dbconfig);
	}
	private function _init_mongodb($dbconfig){
		$this->_init_db_config($dbconfig);
		$this->connect();
	}
	private function _init_db_config($dbconfig){
		$this->db_host = $dbconfig['hostname'];
		$this->db_user = $dbconfig['username'];
		$this->db_password = $dbconfig['password'];
		$this->db_port = $dbconfig['port'];
		$this->db_name = $dbconfig['database'];
		$this->db_prefix = $dbconfig['tablepre'];
	}
	private  function connect(){
            
		try {
			//echo "mongodb://".$this->db_host.':'.$this->db_port;
			$this->mongodb_connection = new Mongo("mongodb://".$this->db_host.':'.$this->db_port);
		}catch (Exception $e){
			die('Mongodb Connection Fail');
		}
		$this->select_db($this->db_name);
		$this->is_close = false;
		return $this->db;
	}
	/**
	* 插入数据
	* @param string $collnections_name    集合名称(相当于关系数据库中的表)
	* @param array $data_array
	*/
	public function insert($collnections_name,$data_array){
		$this->_auto_connection_mongondb();
		$collnection = $this->_select_collection($collnections_name);
		return $collnection->insert($data_array);
	}
	/**
	* 查询一条记录
	* @param string $collnections_name    集合名称(相当于关系数据库中的表)
	* @param array $query                查询的条件array(key=>value) 相当于key=value
	* @param array $filed                 需要列表的字段信息array(filed1,filed2)
	*/
	public function fetch_one($collnections_name,$query,$filed=array()){
		$this->_auto_connection_mongondb();
		$connnection = $this->db->selectCollection($collnections_name);
		$result = $connnection->findOne($query,$filed);
		return $result;
	}
	public function fetch_all($collection_name,$query,$field=array()){
		$this->_auto_connection_mongondb();
		$result = array();
		$collection = $this->_select_collection($collection_name);
		$cursor = $collection->find($query,$field);
		while ($cursor->hasNext()){
			$result[] = $cursor->getNext();
		}
		return $result;
	}
	/**
	* 查询记录集的条数
	* @param string $collection_name    集合名称(相当于关系数据库中的表)
	* @param array $query
	* @return int
	*/    
	public function count($collection_name,$query=array()){
		$this->_auto_connection_mongondb();
		$collection = $this->_select_collection($collection_name);
		return $collection->count($query);
	}

	/**
	* 更新数据(注一次只能更新一条记录)
	* @param string     $collection_name    集合名称|表名
	* @param array     $query                查询条件array(key=>value)
	* @param array     $update_data        要更新的数据
	* @return bool
	*/
	public function update_one($collection_name,$query,$update_data){
		$collection = $this->_select_collection($collection_name);
		$result = $collection->update($query,$update_data);
		return $result;
	}
	/**
	* 更新所以满足条件的记录
	* @param string $collection_name
	* @param array $query
	* @param array $update_data
	* @return boolea
	*/
	public function update_all($collection_name,$query,$update_data){
		$result = false;
		$collection = $this->_select_collection($collection_name);
		$count = $collection->count($count);
		for ($i = 1;$i<=$count;$i++){
			$result = $collection->update($query,$update_data);
		}
		return $result;
	}

	/**
	* 删除记录
	* @param string $collection_name    集合名称(相当于关系数据库中的表)
	* @param array $query                删除条件
	* @param array $option                删除的选项详见mongodb开发手册
	* @return unknown
	*/
	public function _delete($collection_name,$query,$option=array("justOne"=>false)){
		$collection = $this->_select_collection($collection_name);
		$result = $collection->remove($query,$option);
		return $result;
	}

	private function _select_collection($collection_name){
		$collection = $this->db->selectCollection($collection_name);
		return $collection;
	}

	public function select_db($db_name){
		$this->db = $this->mongodb_connection->selectDB($db_name);
	//$this->_auth();
	}
	private  function _auth(){
		$result = $this->db->authenticate($this->db_user,$this->db_password);
		return $result['ok'];
	}

	public function close(){
		if(!$this->is_close){
			$this->mongodb_connection->close();
			$this->is_close = true;
		}
	}
	private function _auto_connection_mongondb(){
		if($this->is_close){
			$this->connect();
		}
	}
	/*public function __destruct(){
		$this->close();
	}*/
}
?> 