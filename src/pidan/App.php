<?php
declare (strict_types = 1);

namespace pidan;

use pidan\helper\Str;
// use pidan\cache\Redis;
/**
 * App 基础类
 * @property Route      $route
 * @property Config     $config
 * @property Cache      $cache
 * @property Request    $request
 * @property Http       $http
 * @property Console    $console
 * @property Event      $event
 * @property Middleware $middleware
 * @property Log        $log
 * @property Lang       $lang
 * @property Db         $db
 * @property Cookie     $cookie
 * @property Session    $session
 * @property Validate   $validate
 */
class App extends Container
{
	/**
	 * 应用模型
	 * @var bool
	 */
	protected $mode = '';//空为正常模型  thin瘦模型做接口  cli   swoole

	const VERSION = '1.0.1';

	/**
	* 应用调试模式
	* @var bool
	*/
	protected $appDebug = false;


	/**
	* 应用开始时间
	* @var float
	*/
	protected $beginTime;

	/**
	* 应用内存初始占用
	* @var integer
	*/
	protected $beginMem;

	
	/**
	 * 当前应用类库命名空间
	 * @var string
	 */
	protected $namespace = 'app';
	/**
	 * 应用根目录
	 * @var string
	 */
	protected $rootPath = '';

	/**
	 * 框架目录
	 * @var string
	 */
	protected $pidanPath = '';

	/**
	 * 应用目录
	 * @var string
	 */
	protected $appPath = '';

	/**
	 * Runtime目录
	 * @var string
	 */
	protected $runtimePath = '';
	/**
	* 路由定义目录
	* @var string
	*/
	protected $routePath = '';

	/**
	 * 应用初始化器
	 * @var array
	 */
	protected $initializers = [
		'pidan\initializer\RegisterService'
	];
	/**
	 * 注册的系统服务
	 * @var array
	 */
	protected $services = [];
	/**
	 * 初始化
	 * @var bool
	 */
	protected $initialized = false;

		/**
	 * 容器绑定标识
	 * @var array
	 */
	protected $bind = [
		'app'                     => App::class,
		'cache'                   => Cache::class,
		'config'                  => Config::class,
		'console'                 => Console::class,
		'cookie'                  => Cookie::class,
		'db'                      => Db::class,
		'dbs'                     => Dbs::class,
		'event'                   => Event::class,
		'http'                    => Http::class,
		'lang'                    => Lang::class,
		'log'                     => Log::class,
		'middleware'              => Middleware::class,
		'request'                 => Request::class,
		'response'                => Response::class,
		'route'                   => Route::class,
		'session'                 => Session::class,
		'validate'                => Validate::class,
		'token'                   => Token::class,
		'pidan\DbManager'         => Db::class,
		'pidan\LogManager'        => Log::class,
		'pidan\CacheManager'      => Cache::class,
		// 接口依赖注入
		'Psr\Log\LoggerInterface' => Log::class,
	];
	/**
	 * 架构方法
	 * @access public
	 * @param string $rootPath 应用根目录
	 */
	public function __construct(string $rootPath = '')
	{
		$this->G('AppStart');
		$this->pidanPath   = realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR;// /jetee/framework/src
		$this->rootPath    = $rootPath ? $rootPath : $this->getDefaultRootPath();//  document_root 网站根目录
		$this->appPath     = $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
		$this->runtimePath = $this->rootPath . 'runtime' . DIRECTORY_SEPARATOR;

		if (is_file($this->appPath . 'provider.php')) {

			$this->bind(include $this->appPath . 'provider.php');
		}

		static::setInstance($this);

		$this->instance('pidan\App', $this);
		$this->instance('pidan\Container', $this); 
	}
	/**
	 * 注册服务
	 * @access public
	 * @param Service|string $service 服务
	 * @param bool           $force   强制重新注册
	 * @return Service|null
	 */
	public function register($service, bool $force = false)
	{
		$registered = $this->getService($service);//已经实例化
		if ($registered && !$force) {
			return $registered;
		}
		if (is_string($service)) {
			$service = new $service($this);
		}
		if (method_exists($service, 'register')) {
			$service->register();
		}
		if (property_exists($service, 'bind')) {
			$this->bind($service->bind);
		}
		$this->services[] = $service;
	}
	
	/**
	 * 引导应用 
	 * @access public
	 * @return void
	 */
	public function bootService(): void
	{
		array_walk($this->services, function ($service) {
			if (method_exists($service, 'boot')) {
				return $this->invoke([$service, 'boot']);
			}
		});
	}
	/**
	 * 给定的服务已经实例化  返回实例  否则返回null  
	 * @param string|Service $service
	 * @return Service|null
	 */
	public function getService($service)
	{
		$name = is_string($service) ? $service : get_class($service);
		return array_values(array_filter($this->services, function ($value) use ($name) {
			return $value instanceof $name;
		}, ARRAY_FILTER_USE_BOTH))[0] ?? null;
	}
	/**
	 * 开启应用调试模式
	 * @access public
	 * @param bool $debug 开启应用调试模式
	 * @return $this
	 */
	public function debug(bool $debug = true)
	{
		$this->appDebug = $debug;
		return $this;
	}

	/**
	 * 是否为调试模式
	 * @access public
	 * @return bool
	 */
	public function isDebug(): bool
	{
		return $this->appDebug;
	}
	/**
	 * 设置应用模型
	 * @access public
	 * @param string $mode 应用模型
	 * @return $this
	 */
	public function setMode(string $mode)
	{
		$this->mode = $mode;
		return $this;
	}

	/**
	 * 获取应用模型
	 * @access public
	 * @return string
	 */
	public function getMode(): string
	{
		return $this->mode;
	}
	/**
	 * 设置应用命名空间
	 * @access public
	 * @param string $namespace 应用命名空间
	 * @return $this
	 */
	public function setNamespace(string $namespace)
	{
		$this->namespace = $namespace;
		return $this;
	}

	/**
	 * 获取应用类库命名空间
	 * @access public
	 * @return string
	 */
	public function getNamespace(): string
	{
		return $this->namespace;
	}
   /**
	 * 获取应用根目录
	 * @access public
	 * @return string
	 */
	public function getRootPath(): string
	{
		return $this->rootPath;
	}

	/**
	 * 获取应用基础目录
	 * @access public
	 * @return string
	 */
	public function getBasePath(): string
	{
		return $this->rootPath . 'app' . DIRECTORY_SEPARATOR;
	}

	/**
	 * 获取当前应用目录
	 * @access public
	 * @return string
	 */
	public function getAppPath(): string
	{
		return $this->appPath;
	}

	/**
	 * 设置应用目录
	 * @param string $path 应用目录
	 */
	public function setAppPath(string $path)
	{
		$this->appPath = $path;
	}

	/**
	 * 获取应用运行时目录
	 * @access public
	 * @return string
	 */
	public function getRuntimePath(): string
	{
		return $this->runtimePath;
	}

	/**
	 * 设置runtime目录
	 * @param string $path 定义目录
	 */
	public function setRuntimePath(string $path): void
	{
		$this->runtimePath = $path;
	}

	/**
	 * 获取核心框架目录
	 * @access public
	 * @return string
	 */
	public function getPidanPath(): string
	{
		return $this->pidanPath;
	}
	/**
	* 获取应用开启时间
	* @access public
	* @return float
	*/
	public function getBeginTime(): float
	{
		return $this->beginTime;
	}

	/**
	* 获取应用初始内存占用
	* @access public
	* @return integer
	*/
	public function getBeginMem(): int
	{
		return $this->beginMem;
	}
	/**
	 * 初始化应用
	 * @access public
	 * @return $this
	 */
	public function initialize()
	{
		$this->initialized = true;
		
		$this->beginTime = $_SERVER['REQUEST_TIME_FLOAT'];
		$this->beginMem  = memory_get_usage();

		$this->debugModeInit();

		$this->load();

		// 监听AppInit
		$this->event->trigger('AppInit');

		date_default_timezone_set($this->config->get('app.default_timezone'));

		// 初始化
		foreach ($this->initializers as $initializer) {
			$this->make($initializer)->init($this);
		}

		$this->bootService();
		$this->G('initialized');
		return $this;
	}
    /**
     * 是否初始化过
     * @return bool
     */
    public function initialized()
    {
        return $this->initialized;
    }	
	/**
	 * 设置初始化服务
	 * @param array $initializers
	 */
	public function setInitializers(array $initializers)
	{
		$this->initializers = $initializers;
		return $this;
	}

	/**
	 * 加载应用文件和配置
	 * @access protected
	 * @return void
	 */
	public function load(): void
	{
		include_once $this->pidanPath . 'helper.php';

		if (is_file($this->appPath . 'common.php')) {//加载应用函数
			include_once $this->appPath. 'common.php';
		}

		$this->config->load($this->appPath.'config.php');
		$this->config->load($this->appPath.'config_user.php');

		if (is_file($this->appPath . 'event.php')) {
			$this->loadEvent(include $this->appPath . 'event.php');
		}

		if (is_file($this->appPath . 'service.php')) {
			$services = include $this->appPath . 'service.php';
			foreach ($services as $service) {
				$this->register($service);
			}
		}
	}


	/**
	 * 调试模式设置
	 * @access protected
	 * @return void
	 */
	public function debugModeInit(): void
	{
		$this->appDebug = defined('DEBUG') && DEBUG ? true : false;
		ini_set('display_errors', $this->appDebug ? 'On' : 'Off');

		if (!$this->runningInConsole()) {
			//重新申请一块比较大的buffer
			if (ob_get_level() > 0) {
				$output = ob_get_clean();
			}
			ob_start();
			if (!empty($output)) {
				echo $output;
			}
		}
	}
	/**
	 * 处理事件配置文件 app/event.php
	 * @access protected
	 * @param array $event 事件数据
	 * @return void
	 */
	public function loadEvent(array $event): void
	{
		if (isset($event['bind'])) {
			$this->event->bind($event['bind']);
		}

		if (isset($event['listen'])) {
			$this->event->listenEvents($event['listen']);
		}

		if (isset($event['subscribe'])) {
			$this->event->subscribe($event['subscribe']);
		}
	}



	/**
	 * 解析应用类的类名
	 * @access public
	 * @param string $layer 层名 controller model ...
	 * @param string $name  类名
	 * @return string
	 */
	public function parseClass(string $layer, string $name): string
	{
		$name  = str_replace(['/', '.'], '\\', $name);
		$array = explode('\\', $name);
		$class = Str::studly(array_pop($array));
		$path  = $array ? implode('\\', $array) . '\\' : '';

		return $this->namespace . '\\' . $layer . '\\' . $path . $class;
	}
	/**
	 * 是否运行在命令行下
	 * @return bool
	 */
	public function runningInConsole(): bool
	{
		return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
	}
    /**
     * 获取应用根目录
     * @access protected
     * @return string
     */
    protected function getDefaultRootPath(): string
    {
        return dirname($this->pidanPath, 4) . DIRECTORY_SEPARATOR;
    }
	/**
	 * 设置和获取统计数据
	 * 使用方法:
	 * <code>
	 * N('db',1,86400); // 记录数据库操作次数 持久1天
	 * N('read',1); // 记录读取次数
	 * echo N('db',0,86400); // 获取当前页面数据库的所有操作次数  从缓冲中读
	 * echo N('read'); // 获取当前页面读取次数
	 * </code> 
	 * @param string  $key 标识位置
	 * @param integer $step 步进值
	 * @param integer $save 持久缓冲时间 
	 * @return mixed
	 */
	public function N($key, $step=0,$save=false) {
		static $_num    = array();
		if (!isset($_num[$key])) {
			$_num[$key] = (false !== $save)? app('cache')->get('N_'.$key) :  0;
		}
		if (empty($step))
			return $_num[$key];
		else
			$_num[$key] = $_num[$key] + (int) $step;
		if(false !== $save){ // 保存结果
			app('cache')->set('N_'.$key,$_num[$key]);
		}
	}
	/**
	 * 记录和统计时间（微秒）和内存使用情况
	 * 使用方法:
	 * <code>
	 * G('begin',floatval)  // 记录标记位
	 * echo G('begin','end',8)  //end可以不定义 精确到小数后8位
	 * G('begin'); // 记录开始标记位
	 * // ... 区间运行代码
	 * G('end'); // 记录结束标签位
	 * echo G('begin','end',6); // 统计区间运行时间 精确到小数后6位
	 * echo G('begin','end','m'); // 统计区间内存使用情况
	 * 如果end标记位没有定义，则会自动以当前作为标记位
	 * 其中统计内存使用需要 MEMORY_LIMIT_ON 常量为true才有效
	 * </code>
	 * @param string $start 开始标签
	 * @param string $end 结束标签
	 * @param integer|string $dec 小数位或者m 
	 * @return mixed
	 */
	function G($start,$end='',$dec=5,$ttl=0) {
		if(!$this->appDebug){
			return;//部属环境不运行
		}
		$pre=defined('APCU_PREFIX') ?APCU_PREFIX:'';
		$_info       =   $pre.'g_info_';
		$_mem        =   $pre.'g_mem_';
		if($end===null){
			//return count($_mem);
			apcu_delete($_info.$start);
			apcu_delete($_mem.$start);
		}elseif(is_float($end)) { // 记录时间        G('begin',3889999999.12) 
			apcu_store($_info.$start,$end,$ttl);
		}elseif(!empty($end)){ // 统计时间和内存使用          G('begin','end')   end可以没记录过
			if(!apcu_exists($_info.$end)) apcu_store($_info.$end, microtime(TRUE),$ttl);
			if($dec=='m'){
				if(!apcu_exists($_mem.$end)) apcu_store($_mem.$end, memory_get_usage(),$ttl);
				return number_format((apcu_fetch($_mem.$end)-apcu_fetch($_mem.$start))/1024);  
			}else{
				return number_format((apcu_fetch($_info.$end)-apcu_fetch($_info.$start)),$dec);
			}       
				
		}elseif(strpos($start,'?')===0){                            //G('?begin') 
			return $dec=='m'?  apcu_fetch($_mem.substr($start,1)) : apcu_fetch($_info.substr($start,1));
		}else{ // 记录时间和内存使用      G('begin') 
			apcu_store($_info.$start, microtime(TRUE),$ttl);
			apcu_store($_mem.$start, memory_get_usage(),$ttl);
		}
	}

}
