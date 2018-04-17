<?php
namespace app;

define('DS', DIRECTORY_SEPARATOR);

class Application{
	
	/**
	 * 应用根路径.
	 *
	 * @var string
	 */
	private $basePath;
	
	/**
	 * 已加载配置.
	 *
	 * @var array
	 */
	private $configurations = [];
	
	/**
	 * The loaded service providers.
	 *
	 * @var array
	*/
	private $loadedProviders = [];
	
	/**
	 * 已注册路由.
	 *
	 * @var array
	 */
	private $routes = [];	
	
	/**
	 * 已注册全局钩子.
	 *
	 * @var array
	*/
	private $hooks = [];
	
	/**
	 * 已注册路由钩子
	 *
	 * @var array
	*/
	private $routeHooks = [];
	
	/**
	 * 当前请求方式
	 *
	 * @var string
	 */
	private $method	= 'GET';

	/**
	 * 当前请求path
	 *
	 * @var string
	 */
	private $pathinfo = '/';	

	/**
	 * 当前请求位置参数
	 *
	 * @var array
	 */	
	private $positionParams = [];
	
	/**
	 * HTTP查询字段
	 *
	 * @var array
	 */
	private $httpQuery = [];
			

	public function __construct(string $basePath)
	{
		$this->basePath = $basePath;
		
		$this->loadConfiguration($this)
			->setEnvironment()
			->registerHooks();
		
		date_default_timezone_set($this->config('app.timezone', 'UTC'));
	}
	
	private function setEnvironment(){
		switch ($this->config('app.environment')) {
			case 'development':
				error_reporting(-1);
				ini_set('display_errors', 1);
				break;
			case 'production':
				ini_set('display_errors', 0);
				error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_STRICT & ~E_USER_NOTICE & ~E_USER_DEPRECATED);
				break;
			default:
				header('HTTP/1.1 503 Service Unavailable.', TRUE, 503);
				echo 'The application environment is not set correctly.';
				exit(1);
		}
		return $this;		
	}
	
	public function run()
	{	
		try {
			//finding route and handling the route
			$result = $this->handleFoundRoute($this->getUsableRoute());
			
			//executing the termination hook after the app finishing
			array_walk($this->hooks, function($hook) use ($result) 
			{
				if (is_callable([$hook, 'terminate'])) $hook->terminate($this, $result);
			});
			
			//responsing json data if the res is not null
			if (! is_null($result)) {
				$this->toJson($result);
			}			
		} catch (\Exception $e) {
			$this->writeLoginfo($e);
		}
	}
	
	public function getUsableRoute()
	{
		try {
			$this->parseIncomingRequest();
			$route = $this->method.$this->pathinfo;
			$has = false;
				
			foreach ($this->routes as $regex => $action) {
				if (preg_match('#^'.$regex.'$#', $route, $this->positionParams)) {
					$has = true; break;
				}		
			}
				
			if (! $has) {
				throw new \Exception("The route {$route} is not found");
			}
			
			if (false !== stripos($action['action'], '@', 1)) {
				list($classname, $method) = explode('@', $action['action'], 2);
			} else {
				list($classname, $method) = [$action['action'], 'index'];
			}
			
			$action['action'] = [$this->getInstance('handlers', $classname), $method];
			
			if (! is_callable($action['action'])) {
				throw new \Exception("The method {$method} of {$classname} is not callable");
			}
			
			return $action;
			
		} catch (\Exception $e) {
			$this->writeLoginfo($e);
			header("HTTP/1.1 404 Not Found"); exit;
		}	
	}
	
	public function handleFoundRoute(array $action = [])
	{					
		try {
			$this->positionParams[0] = $this;
			
			//executing the global hook after the app finding route
			array_walk($this->hooks, function($hook)
			{
				if (is_callable([$hook, 'handle'])) $hook->handle(...$this->positionParams);
			});
			
			//executing the route hook after the app finding route
			$this->routeHooks = $this->routeHooks[$action['method'].$action['uri']] ?? [];
			array_walk($this->routeHooks, function($hook)
			{
				$params = explode(':', $hook);
				$this->getInstance('hooks', array_shift($params))->handle($this, ...$params);
			});	
					
			return call_user_func_array($action['action'], $this->positionParams);
 		} catch (\Error $e) {//ArgumentCountError
			throw new \Exception($e->getMessage(), $e->getCode(), $e->getPrevious());
		}
	}
	
	public function getInstance(string $ns, string $classname, array $args = [])
	{
		$ns = trim($ns, '\\').'\\';
		$class = "\app\\{$ns}".ucfirst($classname);
		return new $class($this, $args);
	}
		
	public function get(string $uri, string $action, array $hooks = [])
	{
		$this->addRoute('GET', $uri, $action, $hooks);	
		return $this;
	}
	
	public function post(string $uri, string $action, array $hooks = [])
	{
		$this->addRoute('POST', $uri, $action, $hooks);	
		return $this;
	}
	
	public function put(string $uri, string $action, array $hooks = [])
	{
		$this->addRoute('PUT', $uri, $action, $hooks);	
		return $this;
	}
	
	public function patch(string $uri, string $action, array $hooks = [])
	{
		$this->addRoute('PATCH', $uri, $action, $hooks);	
		return $this;
	}
	
	public function delete(string $uri, string $action, array $hooks = [])
	{
		$this->addRoute('DELETE', $uri, $action, $hooks);	
		return $this;
	}
	
	public function options(string $uri, string $action, array $hooks = [])
	{
		$this->addRoute('OPTIONS', $uri, $action, $hooks);	
		return $this;
	}

	private function addRoute(string $method, string $uri, string $action, array $hooks)
	{
		$uri = '/'.trim($uri, '/');
		
		$this->routes[$method.$uri] = ['method' => $method, 'uri' => $uri, 'action' => $action];
		
		if (count($hooks) > 0) {
			$this->routeHooks[$method.$uri] = $hooks;
		}
	}
	
	private function loadConfiguration(&$app)
	{
		require $this->getConfigFile('route');
		
		$this->configurations['app'] = require $this->getConfigFile('app');
		$this->configurations['db']  = require $this->getConfigFile('db');

		return $this;
	}
	
	private function registerHooks()
	{
		$hooks = (array)$this->config('app.hooks', []);
		
		array_walk($hooks, function($hook){
			$this->hooks[] = $this->getInstance('hooks', $hook);		
		});
		
		return $this;
	}	

	public function config(string $path = '', $default = [])
	{
		if (empty($path)) {
			return $this->configurations;
		}
		
		if (false !== strpos($path, '.', 1)) {
			list($ns, $key) = explode('.', $path, 2);
			return $this->configurations[$ns][$key] ?? $default; 
		}
		
		return $this->configurations[$path] ?? $default;
	}

	public function input(string $key = '', $default = [], bool $strip = true)
	{
		if (empty($key)) {
			return $this->httpQuery;
		}
		
		if (isset($this->httpQuery[$key])) {
			return $this->httpQuery[$key];
		}
		
		if ( ! $strip) {
			throw new \InvalidArgumentException('The argument is required.', 0);
		}
			
		return $default;
	}
	
	public function stream(bool $json = true)
	{
		$data = file_get_contents('php://input');
		
		if (strlen($data) > 0) {
			return $json ? json_decode($data, true) : $data;
		}
		
		return $default;
	}
	
	public function getConfigFile(string $filename)
	{
		return $this->basePath.DS.'app'.DS.'config'.DS.$filename.'.php';
	}

	public function getCurrentURL()
	{
		$url = $this->isHttps() ? 'https://' : 'http://';
		$url.= $_SERVER['HTTP_HOST'];
		
		if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
			$url.= ':'.$_SERVER['SERVER_PORT'];
		}
		
		return $url.= $_SERVER['REQUEST_URI'];
	}
	
	private function parseIncomingRequest()
	{	
		$info = parse_url($this->getCurrentURL());
		$this->pathinfo = rtrim($info['path'], '/');
		$this->method = strtoupper($_SERVER['REQUEST_METHOD']);
		$this->httpQuery = $_REQUEST;
		
		if (isset($info['query'])) {
			parse_str($info['query'], $request);
			$this->httpQuery = array_merge($this->httpQuery, $request);
		}
					
		return $this;
	}	
	
	public function toJson($data = [], int $code = 200, string $msg = 'success')
	{
		header('Content-type: application/json; charset=utf-8');
	
		if (empty($data)) {
			$data = new \stdClass();
		}
	
		exit(json_encode(['code' => $code, 'msg' => $msg, 'data' => $data],
				JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
	}
	
	public function isHttps()
	{
		if ( ! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
			return true;
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
			return true;
		}
		elseif ( ! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
			return true;
		}
		return false;
	}	
	
	public function writeLoginfo(\Exception $e)
	{
		if ($this->config('app.environment') == 'development') {
			echo $e->getMessage();
		} else {
			//写入日志	
		}
	}	

}