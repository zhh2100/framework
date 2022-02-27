<?php
namespace pidan;

interface Db
{
	
	/**
	 * 开始事务
	 */
	public function beginTrans();

	/**
	 * 提交事务
	 */
	public function commitTrans();

	/**
	 * 事务回滚
	 */
	public function rollBackTrans();

	/**
	* 关闭连接
	*/
	public function closeConnection();


	public function prefix($query);
	/**
	 * 返回最后一条执行的 sql
	 *
	 * @return  string
	 */
	public function lastSQL();
	/**
	 * 执行 SQL   统一调用它查询   不得已才用execute
	 *
	 * @param string $query
	 * @param array  $params
	 * @param int    $fetchmode
	 * @param int    $exec  	执行完就返回，用于手动后续操作  如取单个值
	 * @return mixed
	 */
	public function query($query, $params, $fetchmode,$exec);
	/**
	 * 返回一列
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @return array
	 */
	public function column($query, $params);
	/**
	 * 返回一行
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @param  int    $fetchmode
	 * @return array
	 */
	public function row($query, $params, $fetchmode);
	/**
	 * 返回单个值
	 *
	 * @param  string $query
	 * @param  array  $params
	 * @return string
	 */
	public function single($query, $params);
	/**
	 * 返回 lastInsertId
	 *
	 * @return string
	 */
	public function lastInsertId();
	public function count($query, $params);
	public function insert($table, $data);
	public function update($table, $data, $condition);


}
