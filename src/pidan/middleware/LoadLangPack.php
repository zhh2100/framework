<?php
declare (strict_types = 1);

namespace pidan\middleware;

use Closure;
use pidan\App;
use pidan\Config;
use pidan\Cookie;
use pidan\Lang;
use pidan\Request;
use pidan\Response;

/**
 * 多语言加载
 */
class LoadLangPack
{
    protected $app;

    protected $lang;
    protected $config;

    public function __construct()
    {
        $this->app    = app();
        $this->lang   = $this->app->make('lang');
        $this->config = $this->lang->getConfig();
    }

    /**
     * 路由初始化（路由规则注册）
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        $cookie=$this->app->make('cookie');
        // 自动侦测当前语言
        $langset = $this->detect($request);

        $this->lang->switchLangSet($langset);

        if ($this->config['use_cookie'] && $cookie->get($this->config['cookie_var']) != $langset){          
            $cookie->set($this->config['cookie_var'], $langset);
        } 

        return $next($request);
    }

    /**
     * 自动侦测设置获取语言选择
     * @access protected
     * @param Request $request
     * @return string
     */
    protected function detect(Request $request): string
    {
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
            $langSet = $this->lang->getLangSet();
        }

        if (empty($this->config['allow_lang_list']) || in_array($langSet, $this->config['allow_lang_list'])) {
            // 合法的语言
            $this->lang->setLangSet($langSet);
        } else {
            $langSet = $this->lang->getLangSet();
        }

        return $langSet;
    }



}
