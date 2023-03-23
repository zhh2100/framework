<?php
declare (strict_types = 1);

namespace pidan;

use ArrayAccess;
use Countable;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use RuntimeException;
use InvalidArgumentException;


/**
 * App 基础类
 */
class Container implements ArrayAccess,  Countable
{
	/**
	 * 容器对象实例
	 * @var Container|Closure
	 */
	protected static $instance;

	/**
	 * 容器中的对象实例
	 * @var array
	 */
	protected $instances = [];
	/**
	 * 容器绑定标识
	 * @var array
	 */
	protected $bind = [];
	/**
	 * 容器回调
	 * @var array
	 */
	protected $invokeCallback = [];


	/**
	 * 设置当前容器的实例
	 * @access public
	 * @param object|Closure $instance
	 * @return void
	 */
	public static function setInstance($instance): void
	{
		static::$instance = $instance;
	}
	/**
	 * 获取当前容器的实例（单例）
	 * @access public
	 * @return static
	 */
	public static function getInstance()
	{
		if (is_null(static::$instance)) {
			static::$instance = new static;
		}elseif (static::$instance instanceof Closure) {
			return (static::$instance)();
		}
		return static::$instance;
	}
	/**
	 * 绑定一个类实例到容器
	 * @access public
	 * @param string $abstract 类名或者标识
	 * @param object $instance 类的实例
	 * @return $this
	 */
	public function instance(string $abstract, $instance)
	{
        $abstract = $this->getAlias($abstract);
		$this->instances[$abstract] = $instance;
		return $this;
	}
    /**
     * 判断容器中是否存在对象实例
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function exists(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);

        return isset($this->instances[$abstract]);
    }
    /**
     * 根据别名获取真实类名    返回最后一个字串
     * @param  string $abstract
     * @return string
     */
    public function getAlias(string $abstract): string
    {
        if (isset($this->bind[$abstract])) {
            $bind = $this->bind[$abstract];

            if (is_string($bind)) {
                return $this->getAlias($bind);
            }
        }

        return $abstract;
    }
    /**
	 * 注册一个容器对象回调
	 *
	 * @param string|Closure $abstract
	 * @param Closure|null   $callback
	 * @return void
	 */
	public function resolving($abstract, Closure $callback = null): void
	{
		if ($abstract instanceof Closure) {
			$this->invokeCallback['*'][] = $abstract;
			return;
		}
        $abstract = $this->getAlias($abstract);
		$this->invokeCallback[$abstract][] = $callback;
	}
	/**
	 * 绑定一个类、闭包、实例、接口实现到容器
	 * @access public
	 * @param string|array $abstract 类标识、接口
	 * @param mixed        $concrete 要绑定的类、闭包或者实例
	 * @return $this
	 */
	public function bind($abstract, $concrete = null)
	{
		if (is_array($abstract)) {
			foreach ($abstract as $key => $val) {
				$this->bind($key, $val);
			}
		} elseif ($concrete instanceof Closure) {
			$this->bind[$abstract] = $concrete;
		} elseif (is_object($concrete)) {
			$this->instance($abstract, $concrete);
		} else {
            $abstract = $this->getAlias($abstract);
			if ($abstract != $concrete) {
				$this->bind[$abstract] = $concrete;
			}
		}
		return $this;
	}
	/**
	 * 创建类的实例 已经存在则直接获取
	 * @access public
	 * @param string $abstract    类名或者标识
	 * @param array  $vars        变量
	 * @param bool   $newInstance 是否每次创建新的实例
	 * @return mixed
	 */
	public function make(string $abstract, array $vars = [], bool $newInstance = false)
	{
        $abstract = $this->getAlias($abstract);
		if (isset($this->instances[$abstract]) && !$newInstance) {
			return $this->instances[$abstract];
		}

		if (isset($this->bind[$abstract]) && $this->bind[$abstract] instanceof Closure) {
			$object = $this->invokeFunction($this->bind[$abstract], $vars);
		} else {
		  $object = $this->invokeClass($abstract, $vars);
		}

		if (!$newInstance) {
			$this->instances[$abstract] = $object;
		}

		return $object;
	}
	/**
     * 删除容器中的对象实例
     * @access public
     * @param string $name 类名或者标识
     * @return void
     */
    public function delete($name)
    {
        $name = $this->getAlias($name);

        if (isset($this->instances[$name])) {
            unset($this->instances[$name]);
        }
    }
    /**
     * 调用反射执行类的方法 支持参数绑定
     * @access public
     * @param object $instance 对象实例
     * @param mixed  $reflect  反射类
     * @param array  $vars     参数
     * @return mixed
     */
    public function invokeReflectMethod($instance, $reflect, array $vars = [])
    {
        $args = $this->bindParams($reflect, $vars);

        return $reflect->invokeArgs($instance, $args);
    }

	/**
	 * 调用反射执行callable 支持参数绑定
	 * @access public
	 * @param mixed $callable
	 * @param array $vars       参数
	 * @param bool  $accessible 设置是否可访问
	 * @return mixed
	 */
	public function invoke($callable, array $vars = [], bool $accessible = false)
	{
		if ($callable instanceof Closure) {
			return $this->invokeFunction($callable, $vars);
		} elseif (is_string($callable) && false === strpos($callable, '::')) {
			return $this->invokeFunction($callable, $vars);
		} else {
			return $this->invokeMethod($callable, $vars, $accessible);
		}
	}

	/**
	 * 执行函数或者闭包方法 支持参数调用
	 * @access public
	 * @param string|Closure $function 函数或者闭包
	 * @param array          $vars     参数
	 * @return mixed
	 */
	public function invokeFunction($function, array $vars = [])
	{
		try {
			$reflect = new ReflectionFunction($function);
		} catch (ReflectionException $e) {
			throw new RuntimeException("function not exists: {$function}()");
		}

		$args = $this->bindParams($reflect, $vars);

		return $function(...$args);
	}
	/**
	 * 调用反射执行类的实例化 支持依赖注入
	 * @access public
	 * @param string $class 类名
	 * @param array  $vars  参数
	 * @return mixed
	 */
	public function invokeClass(string $class, array $vars = [])
	{
		try {
			$reflect = new ReflectionClass($class);
		} catch (ReflectionException $e) {
			throw new RuntimeException('class not exists: ' . $class);
		}

		if ($reflect->hasMethod('__make')) {
			$method = $reflect->getMethod('__make');
			if ($method->isPublic() && $method->isStatic()) {
				$args = $this->bindParams($method, $vars);
				return $method->invokeArgs(null, $args);
			}
		}

		$constructor = $reflect->getConstructor();

		$args = $constructor ? $this->bindParams($constructor, $vars) : [];
		$object = $reflect->newInstanceArgs($args);
		$this->invokeAfter($class, $object);

		return $object;
	}
	/**
	 * 调用反射执行类的方法 支持参数绑定
	 * @access public
	 * @param mixed $method     方法
	 * @param array $vars       参数
	 * @param bool  $accessible 设置是否可访问
	 * @return mixed
	 */
	public function invokeMethod($method, array $vars = [], bool $accessible = false)
	{
		if (is_array($method)) {
			[$class, $method] = $method;

			$class = is_object($class) ? $class : $this->invokeClass($class);
		} else {
			// 静态方法
			[$class, $method] = explode('::', $method);
		}

		try {
			$reflect = new ReflectionMethod($class, $method);
		} catch (ReflectionException $e) {
			$class = is_object($class) ? get_class($class) : $class;
			throw new RuntimeException('method not exists: ' . $class . '::' . $method . '()');
		}

		$args = $this->bindParams($reflect, $vars);

		if ($accessible) {
			$reflect->setAccessible($accessible);
		}

		return $reflect->invokeArgs(is_object($class) ? $class : null, $args);
	}
	/**
	 * 执行invokeClass回调
	 * @access protected
	 * @param string $class  对象类名
	 * @param object $object 容器对象实例
	 * @return void
	 */
	protected function invokeAfter(string $class, $object): void
	{
		if (isset($this->invokeCallback['*'])) {
			foreach ($this->invokeCallback['*'] as $callback) {
				$callback($object, $this);
			}
		}

		if (isset($this->invokeCallback[$class])) {
			foreach ($this->invokeCallback[$class] as $callback) {
				$callback($object, $this);
			}
		}
	}
    /**
     * 绑定参数
     * @access protected
     * @param ReflectionFunctionAbstract $reflect 反射类
     * @param array                      $vars    参数
     * @return array
     */
    protected function bindParams(ReflectionFunctionAbstract $reflect, array $vars = []): array
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $name           = $param->getName();
            $lowerName      = \pidan\helper\Str::snake($name);
            $reflectionType = $param->getType();

            if ($param->isVariadic()) {
                return array_merge($args, array_values($vars));
            } elseif ($reflectionType && $reflectionType instanceof \ReflectionNamedType && $reflectionType->isBuiltin() === false) {
                $args[] = $this->getObjectParam($reflectionType->getName(), $vars);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && array_key_exists($name, $vars)) {
                $args[] = $vars[$name];
            } elseif (0 == $type && array_key_exists($lowerName, $vars)) {
                $args[] = $vars[$lowerName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new InvalidArgumentException('method param miss:' . $name);
            }
        }

        return $args;
    }
	/**
	 * 获取对象
	 * @access protected
	 * @param string $className 类名
	 * @param array  $vars      参数
	 * @return mixed
	 */
	protected function getObjectParam(string $className, array &$vars)
	{
		$array = $vars;
		$value = array_shift($array);

		if ($value instanceof $className) {
			$result = $value;
			array_shift($vars);
		} else {
			$result = $this->make($className);
		}

		return $result;
	}
    public function __set($name, $value)
    {
        $this->bind($name, $value);
    }

    public function __get($name)
    {
    	if(isset($this->bind[$name]) || isset($this->instances[$name]))
        	return $this->make($name);
    }

    public function __isset($name): bool
    {
        return $this->exists($name);
    }

    public function __unset($name)
    {
        $this->delete($name);
    }

    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    public function offsetGet($key)//可以app()['pidan\Request']
    {
        return $this->make($key);
    }

    public function offsetSet($key, $value)
    {
        $this->bind($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->delete($key);
    }
    //Countable
    public function count()
    {
        return count($this->instances);
    }

 }