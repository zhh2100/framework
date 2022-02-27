<?php
declare (strict_types = 1);

namespace pidan\facade;

use pidan\Facade;

/**
 * @see \pidan\Event
 * @package pidan\facade
 * @mixin \pidan\Event
 * @method static \pidan\Event listenEvents(array $events) 批量注册事件监听
 * @method static \pidan\Event listen(string $event, mixed $listener, bool $first = false) 注册事件监听
 * @method static bool hasListener(string $event) 是否存在事件监听
 * @method static void remove(string $event) 移除事件监听
 * @method static \pidan\Event bind(array $events) 指定事件别名标识 便于调用
 * @method static \pidan\Event subscribe(mixed $subscriber) 注册事件订阅者
 * @method static \pidan\Event observe(string|object $observer, null|string $prefix = '') 自动注册事件观察者
 * @method static mixed trigger(string|object $event, mixed $params = null, bool $once = false) 触发事件
 * @method static mixed until($event, $params = null) 触发事件(只获取一个有效返回值)
 */
class Event extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return 'event';
    }
}
