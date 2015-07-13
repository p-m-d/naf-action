<?php

namespace Naf\Action;

use Naf\App;

class ErrorHandler extends \Errand\ErrorHandler {

	public static function renderError(array $error) {
		if (!App::config('debug')) {
			return;
		}
		$view = App::locate('View', 'view');
		$request = end(Action::$requests) ?: Action::request();
		$viewObj = new $view($request);
		$data = compact('error');
		$view_path = 'Error';
		echo $viewObj->render('view', 'debug_error', compact('data', 'view_path'));
	}

	public static function renderException(\Exception $exception) {
		$request = end(Action::$requests) ?: Action::request();
		$errorController = new Controller($request);
		$errorController->overload('error', function($self){
			return $self->render();
		});
		$view = [
			'view' => App::config('debug') ? 'debug_exception' : 'error',
			'view_path' => 'Error'
		];
		$data = compact('exception');
		echo $errorController('error', $data, $view);
	}
}

?>