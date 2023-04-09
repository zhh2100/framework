<?php
declare (strict_types = 1);

namespace pidan\service;

use pidan\Paginator;
use pidan\paginator\driver\Bootstrap;
use pidan\Service;

/**
 * 分页服务类
 */
class PaginatorService extends Service
{
    public function register()
    {
        if (!$this->app->bound(Paginator::class)) {
            $this->app->bind(Paginator::class, Bootstrap::class);
        }
    }

    public function boot()
    {
        Paginator::maker(function (...$args) {
            return $this->app->make(Paginator::class, $args, true);
        });

        Paginator::currentPathResolver(function () {
            return $this->app->request->baseUrl();
        });

        Paginator::currentPageResolver(function ($varPage = 'page') {

            $page = $this->app->request->param($varPage);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });
    }
}
