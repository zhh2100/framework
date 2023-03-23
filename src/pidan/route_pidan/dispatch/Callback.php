<?php
declare (strict_types = 1);

namespace pidan\route\dispatch;

use pidan\route\Dispatch;

/**
 * Callback Dispatcher
 */
class Callback extends Dispatch
{
    public function exec()
    {
        // 执行回调方法
        $vars = array_merge($this->request->param(), $this->param);

        return $this->app->invoke($this->dispatch, $vars);
    }

}
