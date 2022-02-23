<?php
declare (strict_types = 1);

//把网址wwww.ma863.comj/blog/show/id/1 转换成$_GET参数      app=blog   act=show   id=1
function dispatcher(){
	$var=array();
	$request_uri =  trim($_SERVER['REQUEST_URI'],'/');
	$part =  pathinfo($request_uri);
	if($part['dirname'] && $request_uri!='index.php'){
		$part['dirname'].='/'.$part['filename'];
		$paths=explode('/',trim($part['dirname'],'/'));
		$var['app']=array_shift($paths);
		$var['act']=array_shift($paths);
		for($i=0;$i<count($paths)/2;$i++){
			$var[$paths[$i*2]]=$paths[$i*2+1];
		}
	}
	$_GET   =  array_merge($var,$_GET);
}

/**
 * 快速获取容器中的实例 支持依赖注入
 * @param string $name        类名或标识 默认获取当前应用实例
 * @param array  $args        参数
 * @param bool   $newInstance 是否每次创建新的实例
 * @return object|App
 */
function app(string $name = '', array $args = [], bool $newInstance = false)
{
	return $name ? pidan\App::getInstance()->make($name, $args, $newInstance) : pidan\Container::getInstance();
}

function route()
{
	return pidan\App::getInstance()->make('route');
}

/**
 * 绑定一个类到容器
 * @param string|array $abstract 类标识、接口（支持批量绑定）
 * @param mixed        $concrete 要绑定的类、闭包或者实例
 * @return Container
 */
function bind($abstract, $concrete = null)
{
	return pidan\Container::getInstance()->bind($abstract, $concrete);
}

/**
	* 获取和设置配置参数
	* @param string|array $name  参数名
	* @param mixed        $value 参数值
	* @return mixed
	*/
function config($name = '', $value = null)
{
	if (is_array($name)) {
		return app('config')->set($name, $value);
	}

	return 0 === strpos($name, '?') ? app('config')->has(substr($name, 1)) : app('config')->get($name, $value);
}

/**
 * 触发事件
 * @param mixed $event 事件名（或者类名）
 * @param mixed $args  参数
 * @return mixed
 */
function event($event, $args = null)
{
	return pidan\Container::getInstance()->make('event')->trigger($event, $args);
}

/**
 * 调用反射实例化对象或者执行方法 支持依赖注入
 * @param mixed $call 类名或者callable
 * @param array $args 参数
 * @return mixed
 */
function invoke($call, array $args = [])
{
	if (is_callable($call)) {
		return pidan\Container::getInstance()->invoke($call, $args);
	}

	return pidan\Container::getInstance()->invokeClass($call, $args);
}

/**
 * 获取语言变量值
 * @param string $name 语言变量名
 * @param array  $vars 动态变量值
 * @param string $lang 语言
 * @return mixed
 */
function lang(string $name, array $vars = [], string $range = '')
{
    return app('lang')->get($name, $vars, $range);
}

/**
 * 获取当前应用目录
 *
 * @param string $path
 * @return string
 */
function app_path($path = '')
{
	return app()->getAppPath() . ($path ? $path . DIRECTORY_SEPARATOR : $path);
}

/**
 * 获取应用基础目录
 *
 * @param string $path
 * @return string
 */
function base_path($path = '')
{
	return app()->getBasePath() . ($path ? $path . DIRECTORY_SEPARATOR : $path);
}

/**
 * 获取web根目录
 *
 * @param string $path
 * @return string
 */
function public_path($path = '')
{
	return app()->getRootPath() . ($path ? ltrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : $path);
}

/**
 * 获取应用运行时目录
 *
 * @param string $path
 * @return string
 */
function runtime_path($path = '')
{
	return app()->getRuntimePath() . ($path ? $path . DIRECTORY_SEPARATOR : $path);
}
/**
 * 获取项目根目录
 *
 * @param string $path
 * @return string
 */
function root_path($path = '')
{
	return app()->getRootPath() . ($path ? $path . DIRECTORY_SEPARATOR : $path);
}

function app_debug(){
	return app()->isDebug();
}

/**
 * 获取\pidan\response\Redirect对象实例
 * @param string $url  重定向地址
 * @param int    $code 状态码
 * @return \pidan\response\Redirect
 */
function redirect(string $url = '', int $code = 302): Redirect
{
	return Response::create($url, 'redirect', $code);
}

/**
 * 获取当前Request对象实例
 * @return Request
 */
function request(): \pidan\Request
{
	return app('request');
}

/**
 * 创建普通 Response 对象实例
 * @param mixed      $data   输出数据
 * @param int|string $code   状态码
 * @param array      $header 头信息
 * @param string     $type
 * @return Response
 */
function response($data = '', $code = 200, $header = [], $type = 'html'): Response
{
	return Response::create($data, $type, $code)->header($header);
}

/**
 * Session管理
 * @param string $name  session名称
 * @param mixed  $value session值
 * @return mixed
 */
function session($name = '', $value = '')
{
	$session=app('session');
	if (is_null($name)) {
		// 清除
		$session->clear();
	} elseif ('' === $name) {
		return $session->all();
	} elseif (is_null($value)) {
		// 删除
		$session->delete($name);
	} elseif ('' === $value) {
		// 判断或获取
		return 0 === strpos($name, '?') ? $session->has(substr($name, 1)) : $session->get($name);
	} else {
		// 设置
		$session->set($name, $value);
	}
}

/**
 * 用在单线程中 共用redis
 */
function redis(){
	static $redis=NULL;
	if($redis===NULL){
		$config=app('config')->get('cache.stores.redis');
		//无空闲连接，创建新连接
		$redis = new Redis();
		do{
			if ($config['persistent']) {
				$res=$redis->pconnect($config['host'], (int) $config['port'], (int) $config['timeout'], 'persistent_id_' . $config['select']);
			} else {
				$res=$redis->connect($config['host'], (int) $config['port'], (int) $config['timeout']);
			}
		}while(!$res);

		if ('' != $config['password']){
			$redis->auth($config['password']);
		}
		if (0 != $config['select']){
			$redis->select($config['select']);
		}
	}
	return $redis;
}


