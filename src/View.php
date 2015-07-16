<?php

namespace Naf\Action;

use Naf\App;
use Naf\Config;
use Infiltrate\FilterableInstanceTrait;

class View {

	use ViewConfigTrait, FilterableInstanceTrait;

	public $request;

	public $response;

	public static $view = [
		'view' => null,
		'layout' => null,
		'data' => [],
		'ext' => 'php'
	];

	public function __construct($request = null, $response = null, $options = array()) {
		$this->viewConfig(Config::merge(self::$view, static::$view, $options));
		$this->request = $request ?: Action::request();
		$this->response = $response ?: Action::response();
		if (isset($this->viewConfig['type'])) {
			$this->response->type = $this->viewConfig['type'];
		} else {
			$this->viewConfig['type'] = $this->response->type;
		}
	}

	public function __invoke($options = []) {
		//@todo filter
		if ($options) {
			if (isset($options['data'])) {
				$this->viewData($options['data']);
			}
			unset($options['data']);
			$this->viewConfig = $options + $this->viewConfig;
		}
		$options = $this->viewConfig;
		$content = '';
		if ($options['view']) {
			$content = $this->view($options['view'], $options);
		}
		if ($options['layout']) {
			if (!isset($options['data']['content'])) {
				$options['data']['content'] = '';
			}
			$options['data']['content'].= $content;
			$content = $this->layout($options['layout'], $options);
		}
		return $content;
	}

	public function url($url = '') {
		return $this->request->base . ltrim($url, '/');
	}

	public function asset($file) {
		$path = $file;
		if (strpos($file, '/') !== 0) {
			$path = WWW_ROOT . $path;
		}
		if (file_exists($path)) {
			$file .= '?' . filemtime($path);
		}
		return $this->url($file);
	}

	public function image($file) {
		return $this->asset($file);
	}

	public function view($template, $options = []) {
		return $this->render('view', $template, $options);
	}

	public function element($template, $options = []) {
		return $this->render('element', $template, $options);
	}

	public function layout($template, $options = []) {
		return $this->render('layout', $template, $options);
	}

	public function render($type, $template, $options = []) {
		//@todo filter
		$templates = (array)$template;
		$rendered = '';
		$options+= $this->viewConfig;
		if (!isset($options['data']['content'])) {
			$options['data']['content'] = '';
		}
		while ($template = array_pop($templates)) {
			$templateFile = $this->locate($type, $template, $options);
			$rendered = $this->parse($templateFile, $options);
			$options['data']['content'].= $rendered;
		}
		return $rendered;
	}

	public function locate($type, $template, $options = []) {
		//@todo filter
		$paths = $this->paths($type);
		foreach ($paths as $path) {
			if (!empty($options["{$type}_path"])) {
				$template = $options["{$type}_path"] . DS . $template;
			}
			if (!strpos($template, '.')) {
				if ($options['type']) {
					$template .= '.' . $options['type'];
				}
				if ($options['ext']) {
					$template .= '.' . $options['ext'];
				}
			}
			$path .= $template;
			if (file_exists($path)) {
				return $path;
			}
		}
		$message = "Template file '{{$type}}/{$template}' not found";
		throw new \Exception($message);
	}

	protected function parse($__templateFile__, $options) {
		//@todo filter
		if (!empty($options['data'])) {
			extract($options['data']);
		}
		ob_start();
		include $__templateFile__;
		return ob_get_clean();
	}

	protected function paths($type = null) {
		//@todo filter
		$base = APP . 'Template' . DS;
		$view = [$base];
		$element = [$base . 'Element' . DS];
		$layout = [$base . 'Layout' . DS];
		$paths = compact('view', 'element', 'layout');
		if ($type) {
			if (!isset($paths[$type])) {
				$message = "Template path not defined for '{$type}'";
				throw new \Exception($message);
			}
			return $paths[$type];
		}
		return $paths;
	}
}

?>