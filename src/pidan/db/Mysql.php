<?php
namespace pidan\db;
use Exception;
use PDO;
use PDOException;
use pidan\Db;

class Mysql implements Db
{
	protected $pdo;
	/**
	 * PDOStatement 实例
	 *
	 * @var \PDOStatement
	 */
	protected $sQuery;
	protected $lastSql;


	/**
	 * 操作哪个表
	 *
	 * @var string
	 */
	protected $table;

	protected $settings;

	public function __construct($host, $user, $password, $db_name, $prefix = '', $port='3306', $charset = 'utf8')
	{
		$this->settings = array(
			'host'     => $host,
			'port'     => $port,
			'user'     => $user,
			'password' => $password,
			'dbname'   => $db_name,
			'prefix'   => $prefix,
			'charset'  => $charset,
		);
		$this->connect();
	}

	public static function __make()
	{
		$config=app('config')->get('datebase');
		return new static($config['db_host'], $config['db_user'], $config['db_pwd'], $config['db_name'], $config['db_prefix']);
	}
	/**
	 * 创建 PDO 实例
	 */
	protected function connect()
	{
		$dsn       = 'mysql:dbname=' . $this->settings["dbname"] . ';host=' .
			$this->settings["host"] . ';port=' . $this->settings['port'];
		$this->pdo = new PDO($dsn, $this->settings["user"], $this->settings["password"],
			array(
				PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . (!empty($this->settings['charset']) ?
						$this->settings['charset'] : 'utf8')
			));
		$this->pdo->setAttribute(PDO::ATTR_ERRMODE, app_debug() ? PDO::ERRMODE_EXCEPTION : PDO::ERRMODE_SILENT);
		$this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
		$this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
	}
	/**
	 * 开始事务
	 */
	public function beginTrans()
	{
		try {
			return $this->pdo->beginTransaction();
		} catch (PDOException $e) {
			// 服务端断开时重连一次
			if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
				$this->closeConnection();
				$this->connect();
				return $this->pdo->beginTransaction();
			} else {
				throw $e;
			}
		}
	}

	/**
	 * 提交事务
	 */
	public function commitTrans()
	{
		return $this->pdo->commit();
	}

	/**
	 * 事务回滚
	 */
	public function rollBackTrans()
	{
		if ($this->pdo->inTransaction()) {
			return $this->pdo->rollBack();
		}
		return true;
	}
	

	/**
	* 关闭连接
	*/
	public function closeConnection()
	{
		$this->pdo = null;
	}


	public function prefix($query = ''){
		return str_replace('{#je_#}', $this->settings['prefix'],$query);
	}
	/**
	 * 返回最后一条执行的 sql
	 *
	 * @return  string
	 */
	public function lastSQL()
	{
		return $this->lastSql;
	}
	/**
	 * 执行     外部不得已才用execute
	 *
	 * @param string $query
	 * @param string $parameters  bindParam函数从1开始   execute从0开始
	 * @throws PDOException
	 */
	private function execute($query, $parameters = array()){ 
		$param=array();
		if($parameters)foreach($parameters as $k =>$v) {
			if(is_int($k)){
				$param[$k]=$parameters[$k];
			}else{
				$param[':'.$k]=$parameters[$k];
			}
		}
		try {
			$this->sQuery = @$this->pdo->prepare($query);
			$this->success = $this->sQuery->execute($param);
			if(app_debug()) file_put_contents('class_mysql.txt',$query."\r\n".print_r($param,1)."\r\n",FILE_APPEND);
		} catch (PDOException $e) {
			// 服务端断开时重连一次
			if ($e->errorInfo[1] == 2006 || $e->errorInfo[1] == 2013) {
				$this->closeConnection();
				$this->connect();
				try {
					$this->sQuery = @$this->pdo->prepare($query);
					$this->success = $this->sQuery->execute($param);
					if(app_debug()) file_put_contents('class_mysql_catch.txt','[重连]'.$query."\r\n".print_r($param,1)."\r\n",FILE_APPEND);
				} catch (PDOException $ex) {
					$this->rollBackTrans();
					throw $ex;
				}
			} else {
				$this->rollBackTrans();
				$msg = $e->getMessage();
				$err_msg = "SQL:".$this->lastSQL()." ".$msg;
				$exception = new PDOException($err_msg, (int)$e->getCode());
				throw $exception;
			}
		}
	}
	/**
	 * 执行 SQL   统一调用它查询   不用execute
	 *
	 * @param string $query
	 * @param array  $params
	 * @param int    $fetchmode
	 * @param int    $exec  	执行完就返回，用于手动后续操作  如取单个值
	 * @return mixed
	 */
	public function query($query = '', $params = null, $fetchmode = PDO::FETCH_ASSOC,$exec=0)
	{
		app()->N('db_query',1);
		$query = $this->prefix(trim($query));
		$this->lastSql = $query;
		$this->execute($query, $params);
		if($exec) return;
		
		$rawStatement = explode(' ', $query);
		$statement = strtolower(trim($rawStatement[0]));
		if ($statement === 'select' || $statement === 'show') {
			return $this->sQuery->fetchAll($fetchmode);
		} elseif ($statement === 'update' || $statement === 'delete' || $statement === 'replace') {
			app()->N('db_write',1);
			return $this->sQuery->rowCount();
		} elseif ($statement === 'insert') {
			app()->N('db_write',1);
			if ($this->sQuery->rowCount() > 0) {
				return $this->lastInsertId();
			}
		} else {
			return null;
		}
		return null;
	}
		/**
	 * 返回一列
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @return array
	 */
	public function column($query = '', $params = null)
	{
		$this->query($query, $params, 0,1);
		$columns = $this->sQuery->fetchAll(PDO::FETCH_NUM);
		$column  = null;
		foreach ($columns as $cells) {
			$column[] = $cells[0];
		}
		return $column;
	}

	/**
	 * 返回一行
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @param  int    $fetchmode
	 * @return array
	 */
	public function row($query = '', $params = null, $fetchmode = PDO::FETCH_ASSOC)
	{
		$this->type = 'SELECT';
		$this->query($query, $params, 0,1);
		return $this->sQuery->fetch($fetchmode);
	}

	/**
	 * 返回单个值
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @return string
	 */
	public function single($query = '', $params = null)
	{
		$this->query($query, $params, 0,1);
		return $this->sQuery->fetchColumn();
	}


	/**
	 * 返回 lastInsertId
	 *
	 * @return string
	 */
	public function lastInsertId()
	{
		return $this->pdo->lastInsertId();
	}

	public function count($query = '', $params = null){
		return $this->single($query, $params);
	}

	public function insert($table, $data) {
		$sql=$key=$value='';
		$params=array();
		foreach ($data as $k => $v) {
			$key.= '`'.$k.'`,';//加反引号
			$value.= '?,';
			$params[]=$v;
		}
		$key=rtrim($key,',');
		$value=rtrim($value,',');
		$sql='insert `'.$this->settings['prefix'].$table.'` ('.$key.') value ('.$value.')';
		return $this->query($sql, $params);
	}
	public function update($table, $data, $condition) {
		if(!$condition)die('no condition');
		$sql=$set='';
		$params=array();
		foreach ($data as $k => $v) {
			$set.= '`'.$k.'`';//加反引号
			$set.='=';
			$set.= '?,';
			$params[]=$v;
		}
		$set=rtrim($set,',');
		$sql='update `'.$this->settings['prefix'].$table.'`  set '.$set .' where '.$condition;
		return $this->query($sql, $params);
	}


}
