<?php
declare (strict_types = 1);

namespace pidan\token;
use pidan\App;

class Apcu{
	//private
	protected $id='';//加$prefix的sid  存入redis  $_REQUEST['access_token']
	protected $expire=3600;
	protected $data;

	
	//如果有sid  读   没有建  如果有cookie sid =read session memery table 
	public function __construct(string $id,int $expire) {
		$this->id=$id;
		$this->expire=$expire;
		$this->data=apcu_fetch($id);
	}
	public static function __make()
	{
		$req=app('request');
		//取id
		$config=app('config')->get('access_token');
		if(PHP_SAPI == 'cli'){
			$id=$req->request($config['name'],'','trim,md5_clear');
		}else{
			$id=$req->request($config['name'],'','trim,md5_clear');
		}

		//如果id或缓存不存在  创建id与session
		if(empty($id) || !apcu_exists($id)) {
			do{
                $id=$config['prefix'].md5(number_format(microtime(true),10));
			}while(!apcu_add($id, [],$config['expire']));//add不会盖
		}
		
		return new static($id,acpu_ttl($id));
	}
	//$_REQUEST['access_token']
	public function getId(){
		return $this->id;
	}
	public function getExpire(){
		return $this->expire;
	}
	/**
	* 设置或删除key
	* 使用方法:
	* <code>
	* set('db',NULL); // 删除key
	* </code> 
	* @param string $key 标识位置
	* @param mixed  $value
	* @return mixed true成功  false失败
	*/
 	public function set($key, $value) {
        $this->data[$key]=$value;
        return apcu_store($this->id,$this->data,$this->expire); //true成功  false失败
	}
	public function delete($key){
 		if(isset($this->data[$key])){
 			unset($this->data[$key]);
 			return apcu_store($this->id,$this->data,$this->expire);//true成功  false失败
		} 
		return true;
	}
	/**
	* 取key值 
	* @param string $key 标识位置
	* @return mixed 0修改成功   1新增成功  false失败
	*/	
	public function get($key,$default = null){
		if(isset($this->data[$key]) && $this->data[$key]){
			return $this->data[$key];
		} 		
		return $default;
	}
 	public function has($key) {
        return isset($this->data[$key]);//成功true  失败false
	}
	public function all(){
		return $this->data;//没有值为空数组   array()
	}
	//如果有用户退出或者新用户登陆  就会清除已过期的登陆不用设置radom清垃圾
	public function clear() {
		return apcu_delete($this->id);
	}
	public function pull($key) {
		$return=$this->data[$key];
		$this->delete($key);
		return $return;//没有值为false
	}

	//取所有字段名
	public function keys() {
		return array_keys($this->data);//没有值为空数组   array()
	}

}