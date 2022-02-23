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
class ShowRuntime
{
	/**
	 * 配置参数
	 * @var array
	 */
	protected $config = [
		'SHOW_RUN_TIME'		=> true,   // 运行时间显示
		'SHOW_ADV_TIME'		=> true,   //显示详细运行时间
		'SHOW_DB_TIMES'		=> true,   //显示数据库操作次数
		'SHOW_CACHE_TIMES'	=> true,   
		'SHOW_USE_MEM'		=> true,   //显示实际使用内存
		'SHOW_LOAD_FILE'	=> true,   
		'SHOW_FUN_TIMES'	=> true,   //显示详细运行时间

	];
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
		$response = $next($request);
		//if($request->app->isDebug())return $response;
		
		$content=$response->getContent();
		if($this->config['SHOW_RUN_TIME']){
			if(false !== strpos($content,'{__NORUNTIME__}')) {
				$content   =  str_replace('{__NORUNTIME__}','',$content);
			}else{
				 $runtime = $this->showTime($request);
				 if(strpos($content,'{__RUNTIME__}')!==false)
					$content   =  str_replace('{__RUNTIME__}',$runtime,$content);
				 else
					$content   .=  $runtime;
			}
		}else{
			$content   =  str_replace(array('{__NORUNTIME__}','{__RUNTIME__}'),'',$content);
		}
		$response->content($content);
		
		return $response;
	}
		/**
	 * 显示运行时间、数据库操作、缓存次数、内存使用信息
	 * @access private
	 * @return string
	 */
	private function showTime($request) {
		$app=app();
		// 显示运行时间
		$app->G('begin',$_SERVER['REQUEST_TIME_FLOAT']);
		if(defined('ENTRY'))$app->G('entry',ENTRY);else $app->G('entry',$_SERVER['REQUEST_TIME_FLOAT']);  //入口位置   起点
		$showTime   =   date('Y-m-d H:i:s').' Process: '.$app->G('entry','Middleware').'s ';
		if($this->config['SHOW_ADV_TIME']) {
			$Middleware=(float)$app->G('Http','Middleware')-(float)$app->G('controllerBigin','controllerEnd');
			// 显示详细运行时间
			$showTime .= '( '.(defined('ENTRY')?'entry:'.$app->G('begin','entry').'s':'').' composer:'.$app->G('entry','AppStart').'s initialized:'.$app->G('AppStart','initialized').'s Http:'.$app->G('initialized','Http').'s Middleware:'.number_format($Middleware,5).'s Controller:'.$app->G('controllerBigin','controllerEnd').'s )';
		}
		if($this->config['SHOW_DB_TIMES'] && $app->N('db_query') ) {
			// 显示数据库操作次数
			$showTime .= ' | DB :'.$app->N('db_query').' queries '.$app->N('db_write').' writes ';
		}
		if($this->config['SHOW_CACHE_TIMES'] && $app->exists('cache')) {
			// 显示缓存读写次数
			$showTime .= ' | Cache :'.$app->cache->store()->getReadTimes().' gets '.$app->cache->store()->getWriteTimes().' writes ';
		}
		if($this->config['SHOW_USE_MEM']) {
			// 显示内存开销
			$showTime .= ' | UseMem:'. number_format((memory_get_usage())/1024).' kb';
		}
		if($this->config['SHOW_LOAD_FILE']) {
			$showTime .= ' | LoadFile:'.count(get_included_files());
		}
		if($this->config['SHOW_FUN_TIMES']) {
			$fun  =  get_defined_functions();
			$showTime .= ' | CallFun:'.count($fun['user']).','.count($fun['internal']);
		}
		$showTime.='|Html cache '.($request->requestCache?'On':'Off');
		$showTime.='|app_debug '.(app()->isDebug()?'On':'Off');
		return $showTime;
	}
	
}




