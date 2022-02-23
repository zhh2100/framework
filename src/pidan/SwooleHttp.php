<?php
declare (strict_types = 1);
namespace pidan;
/**
 * Web应用管理类
 * @package pidan
 */
class SwooleHttp
{
	/**
	 * @var App
	 */
	protected $app;

	/**
	 * 应用名称
	 * @var string
	 */
	protected $name;

	/**
	 * 应用路径   只作设置app->appPath用在multiApp中
	 * @var string
	 */
	protected $path;
	
	/**
	 * 路由路径
	 * @var string
	 */
	protected $routePath;
	/**
	 * 是否绑定应用
	 * @var bool
	 */
	protected $isBind = false;

	public function __construct(App $app)
	{
		$this->app = $app;

	}

	/**
	 * 设置应用名称
	 * @access public
	 * @param string $name 应用名称
	 * @return $this
	 */
	public function name(string $name)
	{
		$this->name = $name;
		return $this;
	}

	/**
	 * 获取应用名称
	 * @access public
	 * @return string
	 */
	public function getName(): string
	{
		return $this->name ?: '';
	}

	/**
	 * 设置应用目录
	 * @access public
	 * @param string $path 应用目录
	 * @return $this
	 */
	public function path(string $path)
	{
		if (substr($path, -1) != DIRECTORY_SEPARATOR) {
			$path .= DIRECTORY_SEPARATOR;
		}

		$this->path = $path;
		return $this;
	}

	/**
	 * 获取应用路径
	 * @access public
	 * @return string
	 */
	public function getPath(): string
	{
		return $this->path ?: '';
	}
	/**
	 * 获取路由目录
	 * @access public
	 * @return string
	 */
	public function getRoutePath(): string
	{
		return $this->routePath;
	}

	/**
	 * 设置路由目录
	 * @access public
	 * @param string $path 路由定义目录
	 */
	public function setRoutePath(string $path): void
	{
		$this->routePath = $path;
	}
	/**
	 * 设置应用绑定
	 * @access public
	 * @param bool $bind 是否绑定
	 * @return $this
	 */
	public function setBind(bool $bind = true)
	{
		$this->isBind = $bind;
		return $this;
	}

	/**
	 * 是否绑定应用
	 * @access public
	 * @return bool
	 */
	public function isBind(): bool
	{
		return $this->isBind;
	}

	/**
	 * 执行应用程序
	 * @access public
	 * @param Request|null $request
	 * @return Response
	 */
	public function run()
	{
		$this->app->initialize();
		$this->app->G('SwooleHttp');
		// 监听HttpRun
		$this->app->event->trigger('SwooleHttpRun');
		//https://www.ma863.com/blog/show/id/220.html  分解
		return $this;
	}

	/**
	 * HttpEnd
	 * @param Response $response
	 * @return void
	 */
	public function end(): void
	{
		$this->app->event->trigger('HttpEnd');
		// 写入日志
		//$this->app->log->save();
	}

}
