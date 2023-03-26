<?php
declare (strict_types = 1);

namespace pidan;
use pidan\helper\Str;

/**
 * TokenSession管理类
 * @package pidan
 * @mixin Store
 */
class Token
{
	private $handle=null;
	public function __construct() {
		// 服务注册
		$type=($app=app())->config->get('access_token.type','apcu');
		$app->bind('token_handle','pidan\\token\\' . Str::studly($type));
		$this->handle=$app->make('token_handle');
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

