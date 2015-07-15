<?php

namespace Naf\Action;

use Naf\App;
use Infiltrate\FilterableInstanceTrait;

class Controller {

	use ViewConfigTrait, FilterableInstanceTrait;

	public $request;

	public $response;

	public $name;

	public static $view = [
		'class' => 'Naf\Action\View',
		'data' => [],
		'layout' => 'default',
		'render' => true,
		'rendered' => false
	];

	protected $overloaded = array();

	public function __construct($request = null, $response = null, $name = null) {
		$this->viewConfig(static::$view + self::$view);
		$this->request = $request ?: Action::request();
		$this->response = $response ?: Action::response();
		if (!isset($this->name)) {
			if (empty($name)) {
				$classPath = explode('\\', get_class($this));
				$name = preg_replace('/Controller$/', '', end($classPath));
			}
			$this->name = $name;
		}
	}

	public function __invoke($action = null, $data = null, $view = null) {
		$params = compact('action', 'data', 'view');
		return $this->_filter(__FUNCTION__, $params, function($self, $params){
			$action = $params['action'] ?: $self->request->params['action'];
			if ($params['data']) {
				$self->viewData($params['data']);
			}
			if ($params['view']) {
				$self->viewConfig($params['view']);
			}
			if (strpos($action, '_') === 0 || method_exists(__CLASS__, $action)) {
				$message = 'Cannot invoke private controller action';
				throw new \Exception($message);
			}
			if (isset($this->overloaded[$action])) {
				$invoke = $this->overloaded[$action];
				return $invoke($self) ?: $self->response;
			}
			if (!method_exists($self, $action)) {
				$message = 'Cannot invoke non-existent controller action';
				throw new \Exception($message);
			}
			$return = $self->{$action}();
			if (!is_string($return) && $this->viewConfig['render']) {
				$this->render();
			}
			return $return ?: $self->response;
		});
	}

	public function overload($action, $invoke) {
		$params = compact('action','invoke');
		$this->_filter(__FUNCTION__, $params, function($self, $params){
			extract($params);
			$self->overloaded[$action] = $invoke;
		});
	}

	public function render($options = []) {
		if ($this->viewConfig['rendered']) {
			return;
		}
		$this->viewConfig['rendered'] = true;
		$options = $options + $this->viewConfig + [
			'view_path' => $this->name
		];
		$class = App::locate($options['class'], 'view');
		if (!$class) {
			$message = 'Cannot locate view class';
			throw new \Exception($message);
		}
		unset($options['class'], $options['rendered'], $options['render']);
		if (!isset($options['type'])) {
			$options['type'] = key(Action::$contentTypes);
		}
		if ($options['type'] == 'negotiate') {
			$acceptsTypes = explode(',', $this->request->accepts);
			foreach (Action::$contentTypes as $type => $mimeTypes) {
				if (array_intersect($mimeTypes, $acceptsTypes)) {
					$options['type'] = $type;
					break;
				}
			}
		}
		$view = new $class($this->request, $this->response, $options);
		$this->response->body($view());
	}

	public function redirect($url, $status = 302, $exit = true) {
		//@todo
	}
}

?>