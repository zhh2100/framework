<?php
declare (strict_types = 1);

namespace pidan;

/**
 * 多语言管理类
 * @package pidan
 */
class Lang
{
	protected $app;

	/**
	 * 配置参数
	 * @var array
	 */
	protected $config = [];

	/**
	 * 多语言信息
	 * @var array
	 */
	private $lang = [];

	/**
	 * 当前语言
	 * @var string
	 */
	private $range = 'zh-cn';

	/**
	 * apcu缓冲前缀    不为null开启缓冲
	 * @var string
	 */
	protected $apcuPrefix;

	/**
	 * 构造方法
	 * @access public
	 * @param array $config
	 */
	public function __construct(array $config = [])
	{
		$this->config = $config;
		$this->range  = $config['default_lang'];
		$this->setApcuPrefix($config['prefix']);
		$this->app=app();

		//自动侦测设置获取语言
		$cookie=$this->app->make('cookie');
		$langset = $this->detect();
		$this->switchLangSet($langset);
		if ($this->config['use_cookie'] && $cookie->get($this->config['cookie_var']) != $langset){
			$cookie->set($this->config['cookie_var'], $langset);
		}
	}

	public static function __make()
	{
		return new static(app('config')->get('lang'));
	}
	/**
	 * 获取当前语言配置
	 * @access public
	 * @return array
	 */
	public function getConfig(): array
	{
		return $this->config;
	}

	/**
	 * 设置当前语言
	 * @access public
	 * @param string $lang 语言
	 * @return void
	 */
	public function setLangSet(string $lang): void
	{
		$this->range = $lang;
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
	 * 获取当前语言
	 * @access public
	 * @return string
	 */
	public function getLangSet(): string
	{
		return $this->range;
	}

	/**
	 * 获取默认语言
	 * @access public
	 * @return string
	 */
	public function defaultLangSet()
	{
		return $this->config['default_lang'];
	}

	/**
	 * 切换语言
	 * @access public
	 * @param string $langset 语言
	 * @return void
	 */
	public function switchLangSet(string $langset)
	{
		if (empty($langset)) {
				return;
		}

		$this->setLangSet($langset);


		$this->load([
			$this->app->getPidanPath() . 'pidan/lang/'  . $langset . '.php' //加载系统语言包  vendor/pidan/lang/en.php
			,$this->app->getBasePath() . 'lang/'  . $langset . '.php'//整站通用   app/lang/en.php
			,$this->app->getBasePath() . 'lang/'  . $langset . '-base.php'//整站通用   app/lang/en-base.php
			,$this->app->getAppPath() . 'lang/' .  $langset . '.php' //应用语言包	 app/admin/lang/en.php
		]);

		// 加载扩展（自定义）语言包
		$list = $this->app->config->get('lang.extend_list', []);

		if (!empty($list[$langset])) {
				$this->load($list[$langset]);
		}
	}

	/**
	 * 加载语言定义(不区分大小写)
	 * @access public
	 * @param string|array $file  语言文件
	 * @param string       $range 语言作用域
	 * @return array
	 */
	public function load($file, $range = ''): array
	{
		$range = $range ?: $this->range;
		if (!isset($this->lang[$range])) {
				$this->lang[$range] = [];
		}

		$lang = [];

		foreach ((array) $file as $name) {
				if (is_file($name)) {
						$result = $this->parse($name);
						$lang   = $result + $lang;
				}
		}

		if (!empty($lang)) {
			$this->lang[$range] = $lang + $this->lang[$range];
		}

		return $this->lang[$range];
	}

	/**
	 * 解析语言文件
	 * @access protected
	 * @param string $file 语言文件名
	 * @return array
	 */
	protected function parse(string $file): array
	{
		if (!is_null($this->apcuPrefix)) {
			$result = apcu_fetch($this->apcuPrefix.$file, $hit);
		}
		if(!isset($hit) || !$hit){
			$type = pathinfo($file, PATHINFO_EXTENSION);
			switch ($type) {
				case 'php':
					$result = include $file;
					break;
				case 'yml':
				case 'yaml':
					if (function_exists('yaml_parse_file')) {
						$result = yaml_parse_file($file);
					}
					break;
				case 'json':
					$data = file_get_contents($file);

					if (false !== $data) {
						$data = json_decode($data, true);

						if (json_last_error() === JSON_ERROR_NONE) {
							$result = $data;
						}
					}

					break;
			}
			$result=isset($result) && is_array($result) ? array_change_key_case($result) : [];
			if (!is_null($this->apcuPrefix)) {
				apcu_store($this->apcuPrefix.$file,  $result,86400);
			}
		}

		return $result;
	}

	/**
	 * 判断是否存在语言定义(不区分大小写)
	 * @access public
	 * @param string|null $name  语言变量
	 * @param string      $range 语言作用域
	 * @return bool
	 */
	public function has(string $name, string $range = ''): bool
	{
		$range = $range ?: $this->range;

		if ($this->config['allow_group'] && strpos($name, '.')) {
			[$name1, $name2] = explode('.', $name, 2);
			return isset($this->lang[$range][strtolower($name1)][$name2]);
		}

		return isset($this->lang[$range][strtolower($name)]);
	}

	/**
	 * 获取语言定义(不区分大小写)
	 * @access public
	 * @param string|null $name  语言变量
	 * @param array       $vars  变量替换
	 * @param string      $range 语言作用域
	 * @return mixed
	 */
	public function get(string $name = null, array $vars = [], string $range = '')
	{
		$range = $range ?: $this->range;

		if (!isset($this->lang[$range])) {
			$this->switchLangSet($range);
		}

		// 空参数返回所有定义
		if (is_null($name)) {
			return $this->lang[$range] ?? [];
		}

		if ($this->config['allow_group'] && strpos($name, '.')) {
			[$name1, $name2] = explode('.', $name, 2);

			$value = $this->lang[$range][strtolower($name1)][$name2] ?? $name;
		} else {
			$value = $this->lang[$range][strtolower($name)] ?? $name;
		}

		// 变量解析
		if (!empty($vars) && is_array($vars)) {
			/**
			 * Notes:
			 * 为了检测的方便，数字索引的判断仅仅是参数数组的第一个元素的key为数字0
			 * 数字索引采用的是系统的 sprintf 函数替换，用法请参考 sprintf 函数
			 */
			if (key($vars) === 0) {
				// 数字索引解析
				array_unshift($vars, $value);
				$value = call_user_func_array('sprintf', $vars);
			} else {
				// 关联索引解析
				$replace = array_keys($vars);
				foreach ($replace as &$v) {
					$v = "{:{$v}}";
				}
				$value = str_replace($replace, $vars, $value);
			}
		}

		return $value;
	}

    /**
     * 自动侦测设置获取语言选择
     * @access protected
     * @param Request $request
     * @return string
     */
    protected function detect(): string
    {
    	$request=$this->app->request;
        // 自动侦测设置获取语言选择
        $langSet = '';

        if ($request->get($this->config['detect_var'])) {
            // url中设置了语言变量
            $langSet = $request->get($this->config['detect_var']);
        } elseif ($request->header($this->config['header_var'])) {
            // Header中设置了语言变量
            $langSet = $request->header($this->config['header_var']);
        } elseif ($request->cookie($this->config['cookie_var'])) {
            // Cookie中设置了语言变量
            $langSet = $request->cookie($this->config['cookie_var']);
        } elseif ($request->server('HTTP_ACCEPT_LANGUAGE')) {
            // 自动侦测浏览器语言
            $langSet = $request->server('HTTP_ACCEPT_LANGUAGE');
        }

        if (preg_match('/^([a-z\d\-]+)/i', $langSet, $matches)) {
            $langSet = strtolower($matches[1]);
            if (isset($this->config['accept_language'][$langSet])) {
                $langSet = $this->config['accept_language'][$langSet];
            }
        } else {
            $langSet = $this->getLangSet();
        }

        if (empty($this->config['allow_lang_list']) || in_array($langSet, $this->config['allow_lang_list'])) {
            // 合法的语言
            $this->setLangSet($langSet);
        } else {
            $langSet = $this->getLangSet();
        }

        return $langSet;
    }
}
