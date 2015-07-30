<?php
namespace Naf\Action\Exception;

class ActionException extends \Exception {

	protected $status = 500;

	/**
	 * @param string $message
	 * @param integer $code
	 * @param \Exception $previous
	 * @param integer $status
	 */
	public function __construct($message = '', $code = 0, \Exception $previous = null) {
		$args = func_get_args();
		if (isset($args[3])) {
			$this->setStatus($args[3]);
		}
		parent::__construct($message, $code, $previous);
	}

	public function getStatus() {
		return $this->status;
	}

	public function setStatus($status) {
		$this->status = (int) $status;
	}
}