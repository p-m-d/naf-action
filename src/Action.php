<?php

namespace Naf\Action;

use Naf\Action\Exception\ActionException;
use Naf\Action\Exception\NotFoundException;
use Naf\App;
use Naf\Config;
use Infiltrate\FilterableStaticTrait;

class Action {

	use FilterableStaticTrait;

	public static $classes = [
		'request' => 'Naf\Action\Request',
		'response' => 'Naf\Action\Response',
		'router' => 'Naf\Action\Action'
	];

	public static $defaults = [
		'controller' => 'Naf\Action\PagesController',
		'action' => 'view'
	];

	public static $contentTypes = [
		'html' =>['text/html', 'application/xhtml+xml', '*/*'],
		'htm' => ['text/html', 'application/xhtml+xml', '*/*'],
		'form' => ['application/x-www-form-urlencoded', 'multipart/form-data'],
		'file' => ['multipart/form-data'],
		'json' => ['application/json'],
		'rss' => ['application/rss+xml'],
		'atom' => ['application/atom+xml'],
		'css' => ['text/css'],
		'js' => ['application/javascript', 'text/javascript'],
		'text' => ['text/plain'],
		'txt' => ['text/plain'],
		'xml' => ['application/xml', 'application/soap+xml', 'text/xml'],
		'xhtml' => ['application/xhtml+xml', 'application/xhtml', 'text/xhtml'],
		'xhtml-mobile' => ['application/vnd.wap.xhtml+xml'],
	];

	public static $requests = [];

	protected static $_routes = [];

	protected static $_dispatchFilters = [];

	public static function bootstrap() {
		$namespace = 'Naf\\Action';
		$provides = [
			'controller' => ['Controller', 'Action'],
			'view' => ['View', 'Action']
		];
		$params = compact('namespace', 'provides');
		return static::filterStaticMethod(__FUNCTION__, $params, function($self, $params){
			extract($params);
			App::provide($namespace, $provides);
		});
	}

	public static function route($pattern, $route = [], $match = []) {
		if (($router = static::$classes['router']) != __CLASS__) {
			return $router::route($pattern, $route, static::$defaults);
		}
		if (is_array($pattern)) {
			array_map(__METHOD__, array_keys($pattern), $pattern);
		} else {
			$defaults = static::$defaults;
			$params = compact('pattern', 'route', 'match', 'defaults');
			static::filterStaticMethod(__FUNCTION__, $params, function($self, $params) {
				$route = $params['defaults'];
				if (!is_array($params['route'])) {
					if (is_string($params['route'])) {
						$route['action'] = $params['route'];
					} elseif (is_callable($params['route'])) {
						$route['call'] = $params['route'];
					}
				} else {
					$route = $params['route'] + $params['defaults'];
				}
				static::$_routes[] = compact('route') + $params;
			});
		}
		return static::$_routes;
	}

	public static function dispatchFilter($pattern, $filter = []) {
		if (($router = static::$classes['router']) != __CLASS__) {
			return $router::dispatchFilter($pattern, $filter);
		}
		if (is_array($pattern)) {
			array_map(__METHOD__, array_keys($pattern), $pattern);
		} else {
			$params = compact('pattern', 'filter');
			static::filterStaticMethod(__FUNCTION__, $params, function($self, $params) {
				static::$_dispatchFilters[$params['pattern']] = $params['filter'];
			});
		}
		return static::$_dispatchFilters;
	}

	public static function routes() {
		if (($router = static::$classes['router']) != __CLASS__) {
			return $router::routes();
		}
		return static::$_routes;
	}

	public static function dispatchFilters() {
		if (($router = static::$classes['router']) != __CLASS__) {
			return $router::dispatchFilters();
		}
		return static::$_dispatchFilters;
	}

	public static function match($request, $filterParams) {
		if (($router = static::$classes['router']) != __CLASS__) {
			return $router::match($request);
		}
		$params = compact('request');
		return static::filterStaticMethod(__FUNCTION__, $params, function($self, $params){
			extract($params);
			foreach ($self::$_routes as $_route) {
				$matches = false;
				extract($_route);
				$route = Config::merge($route, ['params' => [], 'args' => []]);
				if (strpos($pattern, '#') === 0) {
					if (preg_match($pattern, $request->url, $params)) {
						array_map(function($v, $k) use(&$route){
							if (is_string($k)) {
								$route[$k] = $v;
							}
						}, $params, array_keys($params));
						$matches = true;
					}
				}
				if ($request->url == $pattern) {
					$matches = true;
				}
				if ($matches && !empty($match)) {
					$matches = static::_match($request, $route, $match);
				}
				if ($matches) {
					return $route;
				}
			}
			return false;
		});
	}

	protected static function _match($request, $route, $match) {
		$matches = $requestMatches = true;
		if (isset($match['request'])) {
			$requestClass = get_class($request);
			$requestMatches = $requestClass::matches($request, $match['request']);
			unset($match['request']);
		}
		if ($requestMatches && !empty($match)) {
			//@todo params/other match types
		}
		return $matches && $requestMatches;
	}

	public static function dispatch() {
		$request = static::request();
		static::filterStaticMethod(__FUNCTION__, compact('request'), function($self, $params){
			extract($params);
			$filterParams = [];
			foreach ($self::$_dispatchFilters as $pattern => $filter) {
				if (preg_match($pattern, $request->url, $params)) {
					if (is_callable($filter)) {
						if ($_filterParams = call_user_func($filter, $request, $filterParams)) {
							$_filterParams = Config::merge($filterParams, $filter);
						}
					} else {
						$filterParams = Config::merge($filterParams, $filter);
						array_map(function($v, $k) use(&$filterParams){
							if (is_string($k)) {
								$filterParams[$k] = $v;
							}
						}, $params, array_keys($params));
					}
				}
			}
			$request->params = Config::merge($request->params, $filterParams);
			if (!($routeParams = $self::match($request, $filterParams))) {
				$message = 'Cannot route request';
				throw new NotFoundException($message);
			}
			$request->params = Config::merge($request->params, $routeParams);
			$self::$requests[] = $request;
		});
		return static::call($request);
	}

	public static function call($request) {
		$response = static::response();
		$params = compact('request', 'response');
		return static::filterStaticMethod(__FUNCTION__, $params, function($self, $params){
			extract($params);
			if (isset($request->params['call'])) {
				$invoke = $request->params['call'];
				if (!is_callable($invoke)) {
					$message = 'Cannot invoke non-callable';
					throw new ActionException($message);
				}
				return $invoke($request, $response);
			} else {
				$controller = App::locate($request->params['controller'], 'controller');
				if (!$controller) {
					$message = 'Cannot locate controller';
					throw new NotFoundException($message);
				}
				$invoke = new $controller($request, $response);
				if (!is_callable([$invoke, $request->params['action']])) {
					$message = 'Cannot invoke non-callable controller action';
					throw new ActionException($message);
				}
				return $invoke();
			}
		});
	}

	public static function request() {
		$class = static::$classes['request'];
		$params = compact('class');
		return static::filterStaticMethod(__FUNCTION__, $params, function($self, $params){
			extract($params);
			return new $class();
		});
	}

	public static function response() {
		$class = static::$classes['response'];
		$params = compact('class');
		return static::filterStaticMethod(__FUNCTION__, $params, function($self, $params){
			extract($params);
			return new $class();
		});
	}
}

?>