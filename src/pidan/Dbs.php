<?php
//dbs= database simple
declare (strict_types = 1);

namespace pidan;
use pidan\helper\Str;

/**
 * Session管理类
 * @package pidan
 * @mixin Store
 */
class Dbs
{
    private $handle=null;
    public function __construct() {
        // 服务注册
        $type=($app=app())->config->get('database.default','mysql');
        $app->bind('dbs_handle','pidan\\dbs\\' . Str::studly($type));
        $this->handle=app('dbs_handle');
    }

    /**
     * 动态调用
     * @param string $method
     * @param array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->handle->$method(...$parameters);
    }
}
