<?php
declare (strict_types = 1);

namespace pidan;

/**
 * 配置管理类
 * @package pidan
 */
class Config
{
	/**
	 * 配置参数
	 * @var array
	 */
	protected $config = [];

	/**
	 * 配置文件目录
	 * @var string
	 */
	protected $path;

	/**
	 * 配置文件后缀
	 * @var string
	 */
	protected $ext;

    /**
     * apcu缓冲前缀    不为null开启缓冲
     * @var string
     */
    protected $apcuPrefix;

	/**
	 * 构造方法
	 * @access public
	 */
	public function __construct(string $path = null, string $ext = '.php')
	{
		$this->path = $path ?: '';
		$this->ext  = $ext;
		$this->setApcuPrefix('c_');//不要随意改   影响更新
	}

	public static function __make(App $app)
	{
		return new static($app->getBasePath(),'.php');
	}
	 /**
	 * APCu prefix 决定是否使用apcu缓冲
	 *
	 * @param string|null $apcuPrefix
	 */
	public function setApcuPrefix($apcuPrefix)
	{
        $this->apcuPrefix = defined('APCU_PREFIX') ? $apcuPrefix : null;
		return $this;
	}
	/**
	 * 加载配置文件（多种格式）
	 * @access public
	 * @param  string $file 配置文件名
	 * @param  string $name 一级配置名
	 * @return array
	 */
	public function load(string $file, string $name = ''): array
	{
		if (!is_null($this->apcuPrefix)) {
			$config = apcu_fetch($this->apcuPrefix.$file, $hit);
		}
		if(!isset($hit) || !$hit){
			if (is_file($file)) {
				$filename = $file;
			} elseif (is_file($this->path . $file . $this->ext)) {
				$filename = $this->path . $file . $this->ext;
			}
			$config=isset($filename) ? $this->parse($filename) :[];
			if (!is_null($this->apcuPrefix)) {
				apcu_store($this->apcuPrefix.$file, $config,86400);
			}
		}
		return $this->set($config, strtolower($name));
	}

	/**
	 * 解析配置文件
	 * @access public
	 * @param  string $file 配置文件名
	 * @param  string $name 一级配置名
	 * @return array
	 */
	protected function parse(string $file): array
	{
		$type   = pathinfo($file, PATHINFO_EXTENSION);
		$config = [];
		switch ($type) {
			case 'php':
				$config = include $file;
				break;
			case 'yml':
			case 'yaml':
				if (function_exists('yaml_parse_file')) {
					$config = yaml_parse_file($file);
				}
				break;
			case 'ini':
				$config = parse_ini_file($file, true, INI_SCANNER_TYPED) ?: [];
				break;
			case 'json':
				$config = json_decode(file_get_contents($file), true);
				break;
		}
		return isset($config) && is_array($config) ?$config: [];
	}

	/**
	 * 检测配置是否存在
	 * @access public
	 * @param  string $name 配置参数名（支持多级配置 .号分割）
	 * @return bool
	 */
	public function has(string $name): bool
	{
		if (false === strpos($name, '.') && !isset($this->config[strtolower($name)])) {
			return false;
		}

		return !is_null($this->get($name));
	}

	/**
	 * 获取一级配置
	 * @access protected
	 * @param  string $name 一级配置名
	 * @return array
	 */
	protected function pull(string $name): array
	{
		return $this->config[strtolower($name)] ?? [];
	}

	/**
	 * 获取配置参数 为空则获取所有配置
	 * @access public
	 * @param  string $name    配置参数名（支持多级配置 .号分割）
	 * @param  mixed  $default 默认值
	 * @return mixed
	 */
	public function get(string $name = null, $default = null)
	{
		// 无参数时获取所有
		if (empty($name)) {
			return $this->config;
		}

		if (false === strpos($name, '.')) {
			return $this->pull($name);
		}

		$name    = explode('.', $name);
		$name[0] = strtolower($name[0]);
		$config  = $this->config;

		// 按.拆分成多维数组进行判断
		foreach ($name as $val) {
			if (isset($config[$val])) {
				$config = $config[$val];
			} else {
				return $default;
			}
		}

		return $config;
	}

	/**
	 * 设置配置参数 name为数组则为批量设置
	 * @access public
	 * @param  array  $config 配置参数
	 * @param  string $name 配置名
	 * @return array
	 */
	public function set(array $config, string $name = null): array
	{
		if (!empty($name)) {
			if (isset($this->config[$name])) {
				$result = array_merge($this->config[$name], $config);
			} else {
				$result = $config;
			}
			$this->config[$name] = $result;
		} else {
			$result = $this->config = array_merge($this->config, array_change_key_case($config));
		}

		return $result;
	}

}
