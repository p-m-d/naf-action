<?php

namespace Naf\Action;

class Response {

	public $type = '';

	public $body = '';

	public $protocol = 'HTTP/1.1';

	public $status = 200;

	public $charset = 'UTF-8';

	public $headers = array();

	public $cookies = array();

	public $statuses = [
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Time-out',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Large',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Method Failure',
		428 => 'Precondition Required',
		451 => 'Unavailable For Legal Reasons',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Time-out',
		507 => 'Insufficient Storage'
	];

	public function __toString() {
		return $this->send();
	}

	public function send($echo = false, $exit = false) {
		if (isset($this->_headers['Location']) && $this->status === 200) {
			$this->status = 302;
		}
		$this->_setCookies();
		$message = $this->statuses[$this->status];
		$this->_setHeader("{$this->protocol} {$this->status} {$message}");
		$this->_setContent();
		$this->_setHeaders();
		if ($echo) {
			echo $this->body();
		}
		if ($exit) {
			exit;
		}
		return $this->body();
	}

	public function header($name = null, $value = null) {
		if (isset($name)) {
			if ($value === false) {
				unset($this->headers[$name]);
			} elseif ($value === true) {
				return isset($this->headers[$name]) ? $this->headers[$name] : false;
			} else {
				if ($value) {
					$this->headers[$name] = $value;
				} else {
					$this->headers[] = $name;
				}
			}
		}
		return $this->headers;
	}

	public function cookie($name = null, $value = null) {
		if (isset($name)) {
			if ($value === false) {
				unset($this->cookies[$name]);
			} elseif ($value === true) {
				return isset($this->cookies[$name]) ? $this->cookies[$name] : false;
			} else {
				$defaults =  [
					'value' => '',
					'expire' => 0,
					'path' => '/',
					'domain' => '',
					'secure' => false,
					'httpOnly' => false
				];
				if (!is_array($value)) {
					$value = compact('value');
				}
				$value+= $defaults;
				$this->cookies[$name] = $value;
			}
		}
		return $this->cookies;
	}

	public function body($body = null) {
		if (isset($body)) {
			$this->body = $body;
		}
		return $this->body;
	}

	protected function _setCookies() {
		foreach ($this->cookies as $name => $cookie) {
			extract($cookie);
			setcookie($name, $value, $expire, $path, $domain, $secure, $httpOnly);
		}
	}

	protected function _setContent() {
		if (in_array($this->status, array(304, 204))) {
			$this->body('');
		} else {
			$this->_setContentLength();
			$this->_setContentType();
		}
	}

	protected function _setContentLength() {

	}

	protected function _setContentType() {
		$set = isset($this->headers['Content-Type']);
		if (!$set) {
			$set = !empty(preg_grep('/^Content-Type:/', $this->headers));
		}
		if (!$set && isset(Action::$contentTypes[$this->type])) {
			$contentType = current(Action::$contentTypes[$this->type]);
			$whitelist = [
				'application/javascript',
				'application/json',
				'application/xml',
				'application/rss+xml'
			];
			if ($charset = $this->charset) {
				if (!strpos($contentType, 'text/') === 0 && !in_array($contentType, $whitelist)) {
					$charset = false;
				}
			}
			if ($charset) {
				$this->header('Content-Type', "{$contentType}; charset={$charset}");
			} else {
				$this->header('Content-Type', "{$contentType}");
			}
		}
	}

	protected function _setHeaders() {
		foreach ($this->headers as $header => $values) {
			foreach ((array)$values as $value) {
				$this->_setHeader($header, $value);
			}
		}
	}

	protected function _setHeader($name, $value = null) {
		if (!headers_sent()) {
			if ($value === null) {
				header($name);
			} else {
				header("{$name}: {$value}");
			}
		}
	}
}

?>