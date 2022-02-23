<?php
declare (strict_types = 1);

namespace pidan\route;

use pidan\helper\Str;
use pidan\Request;
use pidan\Route;

/**
 * 域名路由
 */
class Domain extends RuleGroup
{
    /**
     * 架构函数
     * @access public
     * @param  Route       $router   路由对象
     * @param  string      $domain     路由域名
     * @param  mixed       $rule     域名路由
     */
    public function __construct(Route $router, string $domain = null, $rule = null)
    {
        $this->router = $router;
        $this->domain = $domain;
        $this->rule   = $rule;
    }


}
