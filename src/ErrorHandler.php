<?php

namespace Naf\Action;

use Naf\Action\Action;
use Naf\App;
use Naf\Config;

class ErrorHandler extends \Errand\ErrorHandler {

	public function __construct() {
		$this->addMethodFilter('handleError', [$this, 'renderError']);
		$this->addMethodFilter('handleException', [$this, 'renderException']);
	}

	public function renderError($self, $params, $chain) {
		if (!Config::get('debug')) {
			return;
		}
		extract($params);
		$view = App::locate('View', 'view');
		$request = end(Action::$requests) ?: Action::request();
		$viewObj = new $view($request);
		$data = compact('error');
		$view_path = 'Error';
		$type = $request->type ?: key(Action::$contentTypes);
		$options = compact('data', 'view_path', 'type');
		echo $viewObj->render('view', 'debug_error', $options);
		$params['default'] = false;
		return $chain->next($self, $params, $chain);
	}

	public function renderException($self, $params, $chain) {
		extract($params);
		$request = end(Action::$requests) ?: Action::request();
		$errorController = new Controller($request);
		$errorController->overload('error', function($self){
			return $self->render();
		});
		$view = [
			'view' => Config::get('debug') ? 'debug_exception' : 'error',
			'view_path' => 'Error'
		];
		$data = compact('exception');
		echo $errorController('error', $data, $view);
		$params['exit'] = true;
		return $chain->next($self, $params, $chain);
	}
}

?>