<?php

namespace Naf\Action;

use Naf\Action\Exception\NotFoundException;
use Naf\App;
use Naf\Config;
use Infiltrate\FilterableInstanceTrait;

class View {

	use ViewConfigTrait, FilterableInstanceTrait;

	public $request;

	public $response;

	/**
	 * Outpuck block content
	 *
	 * @var array
	 */
	protected $blocks = [];

	/**
	 * Outpuck capture stack
	 *
	 * @var array
	 */
	protected $capture = [];

	/**
	 * Output storage stack
	 *
	 * @var array
	 */
	protected $stack = [];

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
		return $this->renderAll($options);
	}

	public function url($url = '') {
		if (preg_match('#^https?://#', $url)) {
			return $url;
		}
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
		while ($template = array_pop($templates)) {
			$templateFile = $this->locate($type, $template, $options);
			$rendered.= $this->parse($templateFile, $options);
		}
		return $rendered;
	}

	public function renderAll($options = []) {
		//@todo filter
		$options = $this->viewConfig($options);
		$content = '';
		if ($options['view']) {
			$content = $this->view($options['view'], $options);
			$this->append('content', $content);
		}
		$options = $this->viewConfig();
		if ($options['layout']) {
			$content = $this->layout($options['layout'], $options);
		}
		return $content;
	}

	/**
	 * Extend another template, i.e. render another view after the current one
	 * must be context aware, i.e. append to correct level of nested template
	 *
	 * @todo
	 */
	public function extend($name) {}

	/**
	 * Get the names of all the existing blocks.
	 *
	 * @return array An array containing the blocks.
	 */
	public function blocks() {
		return array_keys($this->blocks);
	}

	/**
	 *
	 * Begins output buffering for a named block.
	 *
	 * @param string $name Block name.
	 * @return null
	 *
	 */
	public function start($name) {
		$this->capture[] = $name;
		ob_start();
	}

	/**
	 *
	 * Ends buffering and assigness output for the most-recent block.
	 * @return null
	 *
	 */
	public function end() {
		$body = ob_get_clean();
		$name = array_pop($this->capture);
		if (!empty($this->stack[$name])) {
			$body.= array_pop($this->stack[$name]);
		}
		$this->assign($name, $body);
	}

	/**
	 * Append to an existing or new block.
	 *
	 * Appending to a new block will create the block.
	 *
	 * @param string $name Name of the block
	 * @param mixed $value The content for the block.
	 * @return void
	 */
	public function append($name, $value = null) {
		if ($value !== null) {
			$this->assign($name, $this->fetch($name) . $value);
			return;
		}
		$this->start($name);
		echo $this->fetch($name);
	}

	/**
	 * Prepend to an existing or new block.
	 *
	 * Prepending to a new block will create the block.
	 *
	 * @param string $name Name of the block
	 * @param mixed $value The content for the block.
	 * @return null
	 */
	public function prepend($name, $value = null) {
		if ($value !== null) {
			$this->assign($name, $value . $this->fetch($name));
			return;
		}
		$this->stack[$name][] = $this->fetch($name);
		$this->start($name);
	}

	/**
	 *
	 * Sets the body of a block directly, as opposed to buffering and
	 * capturing output.
	 *
	 * @param string $name The section name.
	 * @param string $body The section body.
	 * @return null
	 *
	 */
	public function assign($name, $body) {
		$this->blocks[$name] = $body;
	}

	/**
	 *
	 * Gets the body of a block.
	 *
	 * @param string $name The block.
	 * @param string $default Default text
	 * @return string
	 */
	public function fetch($name, $default = '') {
		if ($this->exists($name)) {
			return $this->blocks[$name];
		}
		return $default;
	}

	/**
	 * Check if a block exists
	 *
	 * @param string $name Name of the block
	 * @return bool
	 */
	public function exists($name) {
		return isset($this->blocks[$name]);
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
		throw new NotFoundException($message);
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
		//@todo filter, other paths
		$base = APP . 'Template' . DS;
		$view = [$base];
		$element = [$base . 'Element' . DS];
		$layout = [$base . 'Layout' . DS];
		$paths = compact('view', 'element', 'layout');
		if ($type) {
			if (!isset($paths[$type])) {
				$message = "Template path not defined for '{$type}'";
				throw new NotFoundException($message);
			}
			return $paths[$type];
		}
		return $paths;
	}
}

?>