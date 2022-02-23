<?php
declare (strict_types = 1);

namespace pidan\middleware;

use Closure;
use pidan\App;
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

    public function __construct(App $app, Lang $lang)
    {
        $this->app  = $app;
        $this->lang = $lang;
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
        // 自动侦测当前语言
        $langset = $this->lang->detect($request);

        if ($this->lang->defaultLangSet() != $langset) {
            // 加载系统语言包
            $this->lang->load([
                $this->app->getPidanPath() . 'lang' . DIRECTORY_SEPARATOR . $langset . '.php',
            ]);

            $this->app->LoadLangPack($langset);
        }

        $this->lang->saveToCookie($this->app->cookie);

        return $next($request);
    }
}
