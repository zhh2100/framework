<?php
namespace pidan\token;

/**
 使用redis作session
 $sess=new session();
 $sess->set('key','2');  //要加变量改库  及var $newguest 就可
 $sess->get('key');
 */
class Redis{
	//private
	protected $handler;
	protected $id='';//加$prefix的sid  存入redis  'name'=>'PHPSESSID1'
	protected $expire;
	
	//如果有sid  读   没有建  如果有cookie sid =read session memery table 
	public function __construct(string $id,int $expire,int $select) {
		$this->id=$id;
		$this->expire=$expire;
		$this->handler=redis($select);
	}

	public static function __make()
	{
		//取id
		$config=app('config')->get('access_token');
		$handler=redis($config['select']);
		if(PHP_SAPI == 'cli'){
			$id=app('request')->request($config['name'],'','trim,md5_clear');
		}else{
			$id=app('request')->request($config['name'],'','trim,md5_clear');
		}

		//如果不存在  创建cookie与session
		if(empty($id) || !$handler->exists($id)) {
			do{
				$id=$config['prefix'].bin2hex(random_bytes(8));
			}while($handler->exists($id));
			$handler->hmset($id,array('a'=>'1'));
			$handler->expire($id,$config['expire']);
		}
		$ttl=$handler->ttl($id);
		return new static($id,$ttl>0 ? $ttl : 0 ,$config['select']);
	}

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
	* @return mixed 0修改成功   1新增成功  false失败
	*/
	public function set($key, $value) {
		return $this->handler->hset($this->id,$key,serialize($value));//0修改成功   1新增成功  false失败
	}
	public function delete($key){
		return $this->handler->hdel($this->id,$key);//成功1  失败0
	}
	/**
	* 取key值 
	* @param string $key 标识位置
	* @return mixed 0修改成功   1新增成功  false失败
	*/	
	public function get($key) {
		return unserialize($this->handler->hget($this->id,$key));//没有值为false
	}
	public function has($key) {
		return $this->handler->hexists($this->id,$key);//成功true  失败false
	}
	public function all(){
		return $this->handler->hgetall($this->id);//没有值为空数组   array()
	}
	//如果有用户退出或者新用户登陆  就会清除已过期的登陆不用设置radom清垃圾
	public function clear() {
		return $this->handler->del($this->id);
	}
	public function pull($key) {
		$return=$this->handler->hget($this->id,$key);
		$this->delete($this->id,$key);
		return unserialize($return);//没有值为false
	}


	//取所有字段名
	public function keys() {
		return $this->handler->hkeys($this->id);//没有值为空数组   array()
	}

}