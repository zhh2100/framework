<?php
declare (strict_types = 1);
use pidan\Response;
use think\route\Url as UrlBuild;
use think\facade\Cookie;

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
 * 缓存管理
 * @param string $name    缓存名称
 * @param mixed  $value   缓存值
 * @param mixed  $options 缓存参数
 * @param string $tag     缓存标签
 * @return mixed
 */
function cache(string $name = null, $value = '', $options = null, $tag = null)
{
	if (is_null($name)) return app('cache');

	$cache=app('cache');
	if ('' === $value) {
		// 获取缓存
		return 0 === strpos($name, '?') ? $cache->has(substr($name, 1)) : $cache->get($name);
	} elseif (is_null($value)) {
		// 删除缓存
		return $cache->delete($name);
	}

	// 缓存数据
	if (is_array($options)) {
		$expire = $options['expire'] ?? null; //修复查询缓存无法设置过期时间
	} else {
		$expire = $options;
	}

	if (is_null($tag)) {
		return $cache->set($name, $value, $expire);
	} else {
		return $cache->tag($tag)->set($name, $value, $expire);
	}
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
 * Cookie管理
 * @param string $name   cookie名称
 * @param mixed  $value  cookie值
 * @param mixed  $option 参数
 * @return mixed
 */
function cookie(string $name, $value = '', $option = null)
{
	if (is_null($value)) {
		// 删除
		Cookie::delete($name, $option ?: []);
	} elseif ('' === $value) {
		// 获取
		return 0 === strpos($name, '?') ? Cookie::has(substr($name, 1)) : Cookie::get($name);
	} else {
		// 设置
		return Cookie::set($name, $value, $option);
	}
}

/**
 * 获取\think\response\Download对象实例
 * @param string $filename 要下载的文件
 * @param string $name     显示文件名
 * @param bool   $content  是否为内容
 * @param int    $expire   有效期（秒）
 * @return \think\response\File
 */
function download(string $filename, string $name = '', bool $content = false, int $expire = 180): File
{
	return app('response')->create($filename, 'file')->name($name)->isContent($content)->expire($expire);
}

/**
 * 浏览器友好的变量输出
 * @param mixed $vars 要输出的变量
 * @return void
 */
function dump(...$vars)
{
	ob_start();
	var_dump(...$vars);

	$output = ob_get_clean();
	$output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);

	if (PHP_SAPI == 'cli') {
		$output = PHP_EOL . $output . PHP_EOL;
	} else {
		if (!extension_loaded('xdebug')) {
			$output = htmlspecialchars($output, ENT_SUBSTITUTE);
		}
		$output = '<pre>' . $output . '</pre>';
	}

	echo $output;
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
 * 获取输入数据 支持默认值和过滤
 * @param string $key     获取的变量名
 * @param mixed  $default 默认值
 * @param string $filter  过滤方法
 * @return mixed
 */
function input(string $key = '', $default = null, $filter = '')
{
	if (0 === strpos($key, '?')) {
		$key = substr($key, 1);
		$has = true;
	}

	if ($pos = strpos($key, '.')) {
		// 指定参数来源
		$method = substr($key, 0, $pos);
		if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'route', 'param', 'request', 'session', 'cookie', 'server', 'env', 'path', 'file'])) {
			$key = substr($key, $pos + 1);
			if ('server' == $method && is_null($default)) {
				$default = '';
			}
		} else {
			$method = 'param';
		}
	} else {
		// 默认为自动判断
		$method = 'param';
	}

	return isset($has) ?
		app('request')->has($key, $method) :
		app('request')->$method($key, $default, $filter);
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
 * 获取\think\response\Json对象实例
 * @param mixed $data    返回的数据
 * @param int   $code    状态码
 * @param array $header  头部
 * @param array $options 参数
 * @return \think\response\Json
 */
function json($data = [], $code = 200, $header = [], $options = []): Json
{
	return Response::create($data, 'json', $code)->header($header)->options($options);
}

/**
 * 获取\think\response\Jsonp对象实例
 * @param mixed $data    返回的数据
 * @param int   $code    状态码
 * @param array $header  头部
 * @param array $options 参数
 * @return \think\response\Jsonp
 */
function jsonp($data = [], $code = 200, $header = [], $options = []): Jsonp
{
	return Response::create($data, 'jsonp', $code)->header($header)->options($options);
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
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 * @param string $name    字符串
 * @param int    $type    转换类型
 * @param bool   $ucfirst 首字母是否大写（驼峰规则）
 * @return string
 */
function parse_name(string $name, int $type = 0, bool $ucfirst = true): string
{
	if ($type) {
		$name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
			return strtoupper($match[1]);
		}, $name);

		return $ucfirst ? ucfirst($name) : lcfirst($name);
	}

	return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
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

function route()
{
	return pidan\App::getInstance()->make('route');
}

/**
 * Session管理
 * @param string $name  session名称
 * @param mixed  $value session值
 * @return mixed
 */
function session($name = '', $value = '',$driver='session')
{
	$session=app($driver);
	if (is_null($name)) {
		// 清除
		return $session->clear();
	} elseif ('' === $name) {
		return $session->all();
	} elseif (is_null($value)) {
		// 删除
	   return $session->delete($name);
	} elseif ('' === $value) {
		// 判断或获取
		return 0 === strpos($name, '?') ? $session->has(substr($name, 1)) : $session->get($name);
	} else {
		// 设置
	   return $session->set($name, $value);
	}
}
//与表单令牌不一样，这个用传参来替代cookie传session_id
function token_session($name = '', $value = '')
{
	if($name=='getAccessToken'){
		return app('token')->getId();
	}elseif($name=='getAccessTokenExpire'){
		return app('token')->getExpire();
	}else{
		return session($name, $value,'token');
	}	
}

/**
 * 获取Token令牌
 * @param string $name 令牌名称
 * @param mixed  $type 令牌生成方法
 * @return string
 */
function token(string $name = '__token__', string $type = 'md5'): string
{
	return app('request')->buildToken($name, $type);
}

/**
 * 生成令牌隐藏表单
 * @param string $name 令牌名称
 * @param mixed  $type 令牌生成方法
 * @return string
 */
function token_field(string $name = '__token__', string $type = 'md5'): string
{
	$token = Request::buildToken($name, $type);

	return '<input type="hidden" name="' . $name . '" value="' . $token . '" />';
}

/**
 * 生成令牌meta
 * @param string $name 令牌名称
 * @param mixed  $type 令牌生成方法
 * @return string
 */
function token_meta(string $name = '__token__', string $type = 'md5'): string
{
	$token = Request::buildToken($name, $type);

	return '<meta name="csrf-token" content="' . $token . '">';
}

/**
 * Url生成
 * @param string      $url    路由地址
 * @param array       $vars   变量
 * @param bool|string $suffix 生成的URL后缀
 * @param bool|string $domain 域名
 * @return UrlBuild
 */
function url(string $url = '', array $vars = [], $suffix = true, $domain = false): UrlBuild
{
	return app('route')->buildUrl($url, $vars)->suffix($suffix)->domain($domain);
}

/**
 * 生成验证对象
 * @param string|array $validate      验证器类名或者验证规则数组
 * @param array        $message       错误提示信息
 * @param bool         $batch         是否批量验证
 * @param bool         $failException 是否抛出异常
 * @return Validate
 */
function validate($validate = '', array $message = [], bool $batch = false, bool $failException = true)//: Validate
{
	if (is_array($validate) || '' === $validate) {
		$v = new Validate();
		if (is_array($validate)) {
			$v->rule($validate);
		}
	} else {
		if (strpos($validate, '.')) {
			// 支持场景
			[$validate, $scene] = explode('.', $validate);
		}

		$class = false !== strpos($validate, '\\') ? $validate : app()->parseClass('validate', $validate);

		$v = new $class();

		if (!empty($scene)) {
			$v->scene($scene);
		}
	}

	return $v->message($message)->batch($batch)->failException($failException);
}

/**
 * 获取\think\response\Xml对象实例
 * @param mixed $data    返回的数据
 * @param int   $code    状态码
 * @param array $header  头部
 * @param array $options 参数
 * @return \think\response\Xml
 */
function xml($data = [], $code = 200, $header = [], $options = []): Xml
{
	return Response::create($data, 'xml', $code)->header($header)->options($options);
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


