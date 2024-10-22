<?php
//dbs= database simple
declare (strict_types = 1);

namespace pidan;

/**
 * 管理类
 * @package pidan
 * @mixin Store
 */
class Dbs
{
    private $handle=null;
    public function __construct() {
        // 服务注册
		app()->bind('dbs_handle','pidan\\dbs\\Mysql');
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
