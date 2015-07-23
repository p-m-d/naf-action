<?php

namespace Naf\Action;

class Request {

	public $url = '';

	public $base = '';

	public $method = '';

	public $type = '';

	public $accepts = '';

	public $body = '';

	public $params = [];

	public $data = [];

	public $post = [];

	public $files = [];

	public $query = [];

	public $env = [];

	public static function parse($request, $params = []) {
		static::parseGlobals($request);
		static::parseUrl($request);
		static::parseContentTypes($request);
		static::parseMethod($request);
		static::parseData($request);
	}

	public static function parseGlobals($request) {
		$request->query = $_GET;
		$request->post = $_POST;
		$request->files = $_FILES;
		$request->cookies = $_COOKIE;
		$request->env = $_SERVER + $_ENV;
	}

	public static function parseUrl($request) {
		$base = dirname($request->env['SCRIPT_NAME']);
		$wl = strlen(WEBROOT_DIR);
		if (substr($base, 0-$wl, $wl) == WEBROOT_DIR) {
			$base = substr($base, 0, 0-$wl);
		}
		$request->base = rtrim($base, '/') . '/';
		$url = '';
		if (isset($request->env['REQUEST_URI'])) {
			$url = $request->env['REQUEST_URI'];
			if (strpos($url, '?') !== false) {
				list($url) = explode('?', $url, 2);
			}
		}
		$bases = [
			preg_quote($request->env['SCRIPT_NAME'], '/'),
			preg_quote(dirname($request->env['SCRIPT_NAME']), '/'),
			preg_quote($request->base, '/')
		];
		$basePattern = implode('|', array_unique($bases));
		$request->url = trim(preg_replace("/^({$basePattern})/", '', $url), '/');
	}

	public static function parseContentTypes($request) {
		$request->type = key(Action::$contentTypes);
		if (isset($request->env['CONTENT_TYPE'])) {
			foreach (Action::$contentTypes as $type => $mimeTypes) {
				if (in_array($request->env['CONTENT_TYPE'], $mimeTypes)) {
					$request->type = $type;
					break;
				}

			}
		}
		if (empty($request->accepts)) {
			if (isset($request->env['HTTP_ACCEPT'])) {
				$request->accepts = $request->env['HTTP_ACCEPT'];
			} else {
				$request->accepts = '*/*';
			}
		}
	}

	public static function parseMethod($request) {
		if (isset($request->env['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
			$method = $request->env['HTTP_X_HTTP_METHOD_OVERRIDE'];
		} else {
			$method = @$request->post['_method'] ?: '';
		}
		if (!empty($method)) {
			$request->env['REQUEST_METHOD'] = $method;
		}
		$method = @$request->env['REQUEST_METHOD'] ?: 'GET';
		$request->method = strtoupper($method);
	}

	public static function parseData($request) {
		if (in_array($request->method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
			$stream = fopen('php://input', 'r');
			$request->body = stream_get_contents($stream);
			fclose($stream);
			switch ($request->type) {
				case 'form':
					parse_str($request->body, $request->data);
					break;
				case 'json':
					$request->data = @json_decode($request->body, true) ?: [];
					break;
			}
		}
	}

	public static function matches($request, $params = []) {
		foreach ($params as $param => $value) {
			if (isset($request->{$param})) {
				if ($request->{$param} !== $value) {
					return false;
				}
			} else if (isset($request->env[$param])) {
				if ($request->env[$param] !== $value) {
					return false;
				}
			}
		}
		return true;
	}
}

?>