<?php
declare (strict_types = 1);

namespace pidan;

use Closure;
use pidan\route\Dispatch;
use pidan\route\dispatch\Callback;
use pidan\route\dispatch\Url as UrlDispatch;
use pidan\route\Domain;
use pidan\route\RuleName;

/**
 * 路由管理类
 * @package pidan
 */
class Route
{

	/**
	 * 配置参数
	 * @var array
	 */
	protected $config = [
		// pathinfo分隔符
		'pathinfo_depr'         => '/',
		// URL伪静态后缀
		'url_html_suffix'       => 'html',//null允许任何后缀
		// 非路由变量是否使用普通参数方式（用于URL生成）
		'url_common_param'      => true,
		// 是否开启路由延迟解析
		'url_lazy_route'        => false,
		// 是否强制使用路由
		'url_route_must'        => false,
		// 合并路由规则
		'route_rule_merge'      => false,
		// 路由是否完全匹配
		'route_complete_match'  => false,
		// 访问控制器层名称
		'controller_layer'      => 'controller',
		// 空控制器名
		'empty_controller'      => 'Error',
		// 是否使用控制器后缀
		'controller_suffix'     => false,
		// 默认的路由变量规则
		'default_route_pattern' => '[\w\.]+',
		// 默认控制器名
		'default_controller'    => 'Index',
		// 默认操作名
		'default_action'        => 'index',
		// 操作方法后缀
		'action_suffix'         => '',
		
		// 去除斜杠
		'remove_slash'          => false,
		// 使用注解路由
		'route_annotation'      => false,
	];

	/**
	 * 当前HOST
	 * @var string
	 */
	protected $host;
	
	/**
	 * 路由绑定
	 * @var array
	 */
	protected $bind = [];
	
	/**
	 * 当前分组对象
	 * @var RuleGroup
	 */
	protected $group;
	
	/**
	 * 域名对象
	 * @var Domain[]
	 */
	protected $domains = [];
	
	
	public function __construct(App $app)
	{
		$this->app      = $app;
		
		if(config('app.with_route', true))
			$this->ruleName = new RuleName();
		
		$this->group = $this->domains['-'] = new Domain($this);// 注册默认域名

		$this->config = array_merge($this->config, $this->app->config->get('route'));
	}

	protected function init()
	{
		if (!empty($this->config['middleware'])) {
			$this->app->middleware->import($this->config['middleware'], 'route');
		}

		$this->lazy($this->config['url_lazy_route']);
		$this->mergeRuleRegex = $this->config['route_rule_merge'];
		$this->removeSlash    = $this->config['remove_slash'];

		$this->group->removeSlash($this->removeSlash);
	}

	public function config(string $name = null)
	{
		if (is_null($name)) {
			return $this->config;
		}

		return $this->config[$name] ?? null;
	}
	
	/**
	 * 设置路由域名及分组（包括资源路由）是否延迟解析
	 * @access public
	 * @param bool $lazy 路由是否延迟解析
	 * @return $this
	 */
	public function lazy(bool $lazy = true)
	{
		$this->lazy = $lazy;
		return $this;
	}
	
	/**
	 * 读取路由绑定
	 * @access public
	 * @param string $domain 域名
	 * @return string|null
	 */
	public function getDomainBind(string $domain = null)
	{
		//取访问域名
		if (is_null($domain)) {//不给定，取访问域名  pinhuo.cc
			$domain = $this->host;
		} elseif (false === strpos($domain, '.') && $this->request) {//给定一级子域得全域名
			$domain .= '.' . $this->request->rootDomain();
		}
		//取子域名除去rootdomain,可能是多级子域
		if ($this->request) {
			$subDomain = $this->request->subDomain();

			if (strpos($subDomain, '.')) {//a.b=>*.b
				$name = '*' . strstr($subDomain, '.');
			}
		}
		
		if (isset($this->bind[$domain])) {
			$result = $this->bind[$domain];
		} elseif (isset($name) && isset($this->bind[$name])) {
			$result = $this->bind[$name];
		} elseif (!empty($subDomain) && isset($this->bind['*'])) {
			$result = $this->bind['*'];
		} else {
			$result = null;
		}

		return $result;
	}
	
	/**
	 * 路由调度   调试分发
	 * @param Request $request
	 * @param Closure|bool $withRoute
	 * @return Response
	 */
	public function dispatch(Request $request, $withRoute = true)
	{
		$this->request = $request;
		$this->host    = $this->request->host(true);//只获取域名pinhuo.cc a.pinhuo.cc
		$this->init();

		if ($withRoute) {
			//加载路由
			if ($withRoute instanceof Closure) {
				$withRoute();
			}
			$dispatch = $this->check();
		} else {
			$dispatch = $this->url($this->path());
		}
		//主要获取控制器 操作名，会存入$request对象中
		$dispatch->init($this->app);

		return $this->app->middleware->pipeline('route')
			->send($request)
			->then(function () use ($dispatch) {
				return $dispatch->run();
			});
	}

	/**
	 * 获取当前请求URL的pathinfo信息(不含URL后缀)
	 * @access protected
	 * @return string
	 */
	protected function path(): string
	{
		$suffix   = $this->config['url_html_suffix'];//html
		$pathinfo = $this->request->pathinfo();//index/index.html  第一个多应用中去除，作为应用

		if (false === $suffix) {
			// 禁止伪静态访问
			$path = $pathinfo;
		} elseif ($suffix) {
			// 去除正常的URL后缀
			$path = preg_replace('/\.(' . $suffix . ')$/i', '', $pathinfo);//index/index.html=>index/index
		} else {
			// 允许任何后缀访问
			$path = preg_replace('/\.' . $this->request->ext() . '$/i', '', $pathinfo);//同上
		}

		return $path;
	}
	/**
	 * 默认URL解析
	 * @access public
	 * @param string $url URL地址
	 * @return Dispatch
	 */
	public function url(string $url): Dispatch
	{
		if ($this->request->method() == 'OPTIONS') {
			// 自动响应options请求
			return new Callback($this->request, $this->group, function () {
				return Response::create('', 'html', 204)->header(['Allow' => 'GET, POST, PUT, DELETE']);
			});
		}
		return new UrlDispatch($this->request, $this->group, $url);
	}	
}
