<?php
declare (strict_types = 1);

namespace pidan;
use pidan\helper\Str;

/**
 * Session管理类
 * @package pidan
 * @mixin Store
 */
class Session
{
	private $handle=null;
	public function __construct() {
		// 服务注册
		$type=($app=app())->config->get('session.type','Apcu');
		$app->bind('session_handle','pidan\\session\\' . Str::studly($type));
		$this->handle=app('session_handle');
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

