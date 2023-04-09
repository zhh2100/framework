<?php
namespace pidan;

use Closure;
use Exception;
use Throwable;

class Pipeline
{
    protected $passable;

    protected $pipes = [];

    protected $exceptionHandler;

    /**
     * 初始数据
     * @param $passable
     * @return $this
     */
    public function send($passable)
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * 调用栈
     * @param $pipes
     * @return $this
     */
    public function through($pipes)
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();
        return $this;
    }

    /**
     * 执行
     * @param Closure $destination
     * @return mixed
     */
    public function then(Closure $destination)
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes),
            $this->carry(),
            function ($passable) use ($destination) {
                try {
                    return $destination($passable);
                } catch (Throwable | Exception $e) {
                    return $this->handleException($passable, $e);
                }
            }
        );

        return $pipeline($this->passable);
    }
	/*简化后伪代码
	public function then(Closure $destination)
	{
		$pipeline = array_reduce(
			array_reverse($this->pipes),
			function ($stack, $pipe) {
				return function ($passable) use ($stack, $pipe) {
					return $pipe($passable, $stack);
				};
			},
			function ($passable) use ($destination) {
				return $destination($passable);
			});
		return $pipeline($this->passable);
	}
	through得到一个闭包数组（伪代码）pipes
	function ($request, $next) {
		$response = handle($request, $next, $param);
		return $response;
	}
	*/

    /**
     * 设置异常处理器
     * @param callable $handler
     * @return $this
     */
    public function whenException($handler)
    {
        $this->exceptionHandler = $handler;
        return $this;
    }

    protected function carry()
    {
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    return $pipe($passable, $stack);//闭包($request,$next)
                } catch (Throwable | Exception $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    /**
     * 异常处理
     * @param $passable
     * @param $e
     * @return mixed
     */
    protected function handleException($passable, Throwable $e)
    {
        if ($this->exceptionHandler) {
            return call_user_func($this->exceptionHandler, $passable, $e);
        }
        throw $e;
    }

}
