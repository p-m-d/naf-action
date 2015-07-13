<?php
namespace Naf\Action;

trait ViewConfigTrait {

	protected $_view = [];

	public function viewData($data) {
		$this->viewConfig(compact('data'));
		return $this->_view['data'];
	}

	public function viewConfig($options = []) {
		if ($options) {
			if (isset($options['data']) && is_array($options['data'])) {
				if (!isset($this->_view['data'])) {
					$this->_view['data'] = [];
				}
				$this->_view['data'] = $options['data'] + $this->_view['data'];
			}
			unset($options['data']);
			$this->_view = $options + $this->_view;
		}
		return $this->_view;
	}
}

?>