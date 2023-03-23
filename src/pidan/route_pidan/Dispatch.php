<?php
declare (strict_types = 1);

namespace pidan\route;

use pidan\App;
use pidan\Container;
use pidan\Request;
use pidan\Response;
use pidan\Validate;

/**
 * 路由调度基础类
 */
abstract class Dispatch
{
	/**
	 * 应用对象
	 * @var \pidan\App
	 */
	protected $app;

	/**
	 * 请求对象
	 * @var Request
	 */
	protected $request;

	/**
	 * 路由规则
	 * @var Rule
	 */
	protected $rule;

	/**
	 * 调度信息
	 * @var mixed
	 */
	protected $dispatch;

	/**
	 * 路由变量
	 * @var array
	 */
	protected $param;

	public function __construct(Request $request, Rule $rule, $dispatch, array $param = [])
	{
		$this->request  = $request;
		$this->rule     = $rule;
		$this->dispatch = $dispatch;
		$this->param    = $param;
	}
	public function init(App $app)
	{
		$this->app = $app;

		// 执行路由后置操作
		$this->doRouteAfter();
	}
	
	/**
	 * 执行路由调度
	 * @access public
	 * @return mixed
	 */
	public function run(): Response
	{
		if ($this->rule instanceof RuleItem && $this->request->method() == 'OPTIONS' && $this->rule->isAutoOptions()) {
			$rules = $this->rule->getRouter()->getRule($this->rule->getRule());
			$allow = [];
			foreach ($rules as $item) {
				$allow[] = strtoupper($item->getMethod());
			}

			return Response::create('', 'html', 204)->header(['Allow' => implode(', ', $allow)]);
		}

		$data = $this->exec();
		return $this->autoResponse($data);
	}

	protected function autoResponse($data): Response
	{
		if ($data instanceof Response) {
			$response = $data;
		} elseif (!is_null($data)) {
			// 默认自动识别响应输出类型
			$type     = $this->request->isJson() ? 'json' : 'html';
			$response = Response::create($data, $type);
		} else {
			$data = ob_get_clean();

			$content  = false === $data ? '' : $data;
			$status   = '' === $content && $this->request->isJson() ? 204 : 200;
			$response = Response::create($content, 'html', $status);
		}

		return $response;
	}


	/**
	 * 检查路由后置操作
	 * @access protected
	 * @return void
	 */
	protected function doRouteAfter(): void
	{
		$option = $this->rule->getOption();

		// 添加中间件
		if (!empty($option['middleware'])) {
			$this->app->middleware->import($option['middleware'], 'route');
		}

		if (!empty($option['append'])) {
			$this->param = array_merge($this->param, $option['append']);
		}

		// 绑定模型数据
		if (!empty($option['model'])) {
			$this->createBindModel($option['model'], $this->param);
		}

		// 记录当前请求的路由规则
		$this->request->setRule($this->rule); 
	   
		// 记录路由变量
		$this->request->setRoute($this->param);

		// 数据自动验证
		if (isset($option['validate'])) {
			$this->autoValidate($option['validate']);
		}
	}

	abstract public function exec();


}
