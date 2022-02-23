<?php
declare (strict_types = 1);

namespace pidan\route;

use Closure;
use pidan\Container;
use pidan\middleware\AllowCrossDomain;
use pidan\middleware\CheckRequestCache;
use pidan\middleware\FormTokenCheck;
use pidan\Request;
use pidan\Route;
use pidan\route\dispatch\Callback as CallbackDispatch;
use pidan\route\dispatch\Controller as ControllerDispatch;

/**
 * 路由规则基础类
 */
abstract class Rule
{
    /**
     * 所在域名
     * @var string
     */
    protected $domain;

    /**
     * 路由对象
     * @var Route
     */
    protected $router;

    /**
     * 路由所属分组
     * @var RuleGroup
     */
    protected $parent;

    /**
     * 路由规则
     * @var mixed
     */
    protected $rule;

    /**
     * 路由参数
     * @var array
     */
    protected $option = [];
    
    /**
     * 设置单个路由参数
     * @access public
     * @param  string $name  参数名
     * @param  mixed  $value 值
     * @return $this
     */
    public function setOption(string $name, $value)
    {
        $this->option[$name] = $value;

        return $this;
    }

    /**
     * 获取路由参数定义
     * @access public
     * @param  string $name 参数名
     * @param  mixed  $default 默认值
     * @return mixed
     */
    public function getOption(string $name = '', $default = null)
    {
        $option = $this->option;

        if ($this->parent) {
            $parentOption = $this->parent->getOption();

            // 合并分组参数
            foreach ($this->mergeOptions as $item) {
                if (isset($parentOption[$item]) && isset($option[$item])) {
                    $option[$item] = array_merge($parentOption[$item], $option[$item]);
                }
            }

            $option = array_merge($parentOption, $option);
        }

        if ('' === $name) {
            return $option;
        }

        return $option[$name] ?? $default;
    }

    /**
     * 获取路由对象
     * @access public
     * @return Route
     */
    public function getRouter(): Route
    {
        return $this->router;
    }

    
    /**
     * 获取路由参数
     * @access public
     * @param  string $name 变量名
     * @return mixed
     */
    public function config(string $name = '')
    {
        return $this->router->config($name);
    }

	/**
     * 是否去除URL最后的斜线
     * @access public
     * @param  bool $remove 是否去除最后斜线
     * @return $this
     */
    public function removeSlash(bool $remove = true)
    {
        return $this->setOption('remove_slash', $remove);
    }
 
     /**
     * 解析URL的pathinfo参数
     * @access public
     * @param  string $url URL地址
     * @return array
     */
    public function parseUrlPath(string $url): array
    {
        // 分隔符替换 确保路由定义使用统一的分隔符
        $url = str_replace('|', '/', $url);
        $url = trim($url, '/');

        if (strpos($url, '/')) {
            // [控制器/操作]
            $path = explode('/', $url);
        } else {
            $path = [$url];
        }

        return $path;
    }
 
 
 
}
