<?php
declare (strict_types = 1);

namespace pidan\route\dispatch;

use pidan\helper\Str;
use pidan\Request;
use pidan\route\Rule;

/**
 * Url Dispatcher
 */
class Url extends Controller
{
    public function __construct(Request $request, Rule $rule, $dispatch)
    {
        $this->request = $request;
        $this->rule    = $rule;

        // 解析控制器操作,其它参数存入$this->param
        $dispatch = $this->parseUrl($dispatch);

        parent::__construct($request, $rule, $dispatch, $this->param);
    }
    
    /**
     * 解析控制器操作,其它参数存入$this->param 
     * @access protected
     * @param  string $url URL
     * @return array  [$controller, $action]
     */
    protected function parseUrl(string $url): array
    {
        $depr = $this->rule->config('pathinfo_depr');//  路径分隔符  一般是 /
        if($with_route=config('app.with_route')){
			$bind = $this->rule->getRouter()->getDomainBind();// 取域名绑定

			if ($bind && preg_match('/^[a-z]/is', $bind)) {
				$bind = str_replace('/', $depr, $bind);
				// 如果有域名绑定
				$url = $bind . ('.' != substr($bind, -1) ? $depr : '') . ltrim($url, $depr);
			}
        }

        $path = $this->rule->parseUrlPath($url);//返回[控制器,操作,其他参数]
        if (empty($path)) {
            return [null, null];
        }

        // 解析控制器
        $controller = !empty($path) ? array_shift($path) : null;

        if ($controller && !preg_match('/^[A-Za-z0-9][\w|\.]*$/', $controller)) {
            throw new \RuntimeException('controller not exists:' . $controller);
        }

        // 解析操作
        $action = !empty($path) ? array_shift($path) : null;
        $var    = [];

        // 解析额外参数
        if ($path) {
            preg_replace_callback('/(\w+)\|([^\|]+)/', function ($match) use (&$var) {
                $var[$match[1]] = strip_tags($match[2]);
            }, implode('|', $path));
        }

        $panDomain = $this->request->panDomain();
        if ($panDomain && $key = array_search('*', $var)) {
            // 泛域名赋值
            $var[$key] = $panDomain;
        }

        // 设置当前请求的参数
        $this->param = $var;

        // 封装路由
        $route = [$controller, $action];

        if ($with_route && $this->hasDefinedRoute($route)) {
            throw new \RuntimeException('invalid request:' . str_replace('|', $depr, $url));
        }

        return $route;
    }
    
}
