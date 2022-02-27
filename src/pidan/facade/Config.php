<?php
declare (strict_types = 1);

namespace pidan\facade;

use pidan\Facade;

/**
 * @see \pidan\Config
 * @package pidan\facade
 * @mixin \pidan\Config
 * @method static array load(string $file, string $name = '') 加载配置文件（多种格式）
 * @method static bool has(string $name) 检测配置是否存在
 * @method static mixed get(string $name = null, mixed $default = null) 获取配置参数 为空则获取所有配置
 * @method static array set(array $config, string $name = null) 设置配置参数 name为数组则为批量设置
 */
class Config extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'config';
    }
}
