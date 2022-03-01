<?php
declare (strict_types = 1);

namespace pidan\contract;

/**
 * 日志驱动接口
 */
interface LogHandlerInterface
{
    /**
     * 日志写入接口
     * @access public
     * @param  array $log 日志信息
     * @return bool
     */
    public function save(array $log): bool;

}
