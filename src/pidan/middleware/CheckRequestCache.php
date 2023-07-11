<?php
declare (strict_types = 1);

namespace pidan\middleware;

use Closure;
use pidan\Cache;
use pidan\Request;
use pidan\Response;

/**
 * 请求缓存处理
 */
class CheckRequestCache
{
	/**
	 * 配置参数
	 * @var array
	 */
	protected $config = [
		// 请求缓存规则  得出缓存键名
		'request_cache_key'    => true, //Closure true 除了上两个以下可同时带 最后带|$func 可带__CONTROLLER__ __ACTION__ __URL__   :key用param中的值取代
		// 请求缓存有效期
		'request_cache_expire' => 3600,	//缓冲时间秒  必须要有
		// 全局请求缓存排除规则
		'request_cache_except' => [],
		// 请求缓存的key的前缀
		'request_cache_tag'    => 'rc_',
	];

	public function __construct()
	{
		$route=app('config')->get('route');
		if($route)$this->config = array_merge($this->config,$route) ;
	}

	/**
	 * 设置当前地址的请求缓存
	 * @access public
	 * @param Request $request
	 * @param Closure $next
	 * @param mixed   $cache
	 * @return Response
	 */
	public function handle(Request $request, Closure $next)
	{
		if ($request->isGet() && false !== $cache) {
			if (false === $this->config['request_cache_key']) {
				// 关闭当前缓存
				$cache = false;
			}

			$cache = $cache ?? $this->getRequestCache($request);

			if ($cache) {
				$request->requestCache=true;
				if (is_array($cache)) {
                    [$key, $expire, $tag] = array_pad($cache, 3, '');
                } else {
                    $key    = md5($request->url(true));
                    $expire = $cache;
                    $tag    = '';
                }

				$key = $this->parseCacheKey($request, $key);

				if (strtotime($request->server('HTTP_IF_MODIFIED_SINCE', '')) + $expire > $request->server('REQUEST_TIME')) {
					// 读取缓存
					return Response::create()->code(304);
				} elseif (($hit = app('cache')->get($key)) !== null) {
					[$content, $header, $when] = $hit;
					if (null === $expire || $when + $expire > $request->server('REQUEST_TIME')) {
						return Response::create($content)->header($header);
					}
				}
			}
		}

		$response = $next($request);

		if (isset($cache) && $cache  && 200 == $response->getCode() && $request->isAllowCache()) {
			$header                  = $response->getHeader();
			$header['Cache-Control'] = 'max-age=' . $expire . ',must-revalidate';
			$header['Last-Modified'] = gmdate('D, d M Y H:i:s') . ' GMT';
			$header['Expires']       = gmdate('D, d M Y H:i:s', time() + $expire) . ' GMT';

			app('cache')->set($tag.$key, [$response->getContent(), $header, time()], $expire);
		}

		return $response;
	}

    /**
     * 读取当前地址的请求缓存信息
     * @access protected
     * @param Request $request
     * @return mixed
     */
    protected function getRequestCache($request)
    {
        $key    = $this->config['request_cache_key'];
        $expire = $this->config['request_cache_expire'];
        $except = $this->config['request_cache_except'];
        $tag    = $this->config['request_cache_tag'];

        foreach ($except as $rule) {
            if (0 === stripos($request->url(), $rule)) {
                return;
            }
        }

        return [$key, $expire, $tag];
    }

	/**
	 * 读取当前地址的请求缓存信息   
	 * @access protected
	 * @param Request $request
	 * @param mixed   $key  Closure true 除了上两个以下可同时带 最后带|$func 可带__CONTROLLER__ __ACTION__ __URL__   :key用param中的值取代
	 * @return null|string
	 */
	protected function parseCacheKey($request, $key)
	{
		if ($key instanceof \Closure) {
			$key = call_user_func($key, $request);
		}

		if (false === $key) {
			// 关闭当前缓存
			return;
		}

		if (true === $key) {
			// 自动缓存功能
			$key = '__URL__';
		}
		//带|函数取
		elseif (str_contains($key, '|')) {
			[$key, $fun] = explode('|', $key);
		}

		// 特殊规则替换
		if (false !== str_contains($key, '__')) {
			$key = str_replace(['__CONTROLLER__', '__ACTION__', '__URL__'], [$request->controller(), $request->action(), md5($request->url(true))], $key);
		}
		//配置:key从param中取     
		if (false !== str_contains($key, ':')) {
			$param = $request->param();

			foreach ($param as $item => $val) {
				if (is_string($val) && false !== str_contains($key, ':' . $item)) {//key中包含键名   用值取代   如是$key='111:username'  $item='username'  $val='xxxxx'   结果为111xxxx 
					$key = str_replace(':' . $item, (string) $val, $key);
				}
			}
		} elseif (str_contains($key, ']')) {
            if ('[' . $request->ext() . ']' == $key) {
                // 缓存某个后缀的请求
                $key = md5($request->url());
            } else {
                return;
            }
        }


		if (isset($fun)) {
			$key = $fun($key);
		}

		return $key;
	}
}
