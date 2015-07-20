<?php
namespace Naf\Action;

trait ViewConfigTrait {

	protected $viewConfig = ['data' =>[]];

	public function viewData($data) {
		$this->viewConfig(compact('data'));
		return $this->viewConfig['data'];
	}

	public function viewConfig($options = []) {
		if ($options) {
			if (isset($options['data']) && is_array($options['data'])) {
				if (!isset($this->viewConfig['data'])) {
					$this->viewConfig['data'] = [];
				}
				$this->viewConfig['data'] = $options['data'] + $this->viewConfig['data'];
			}
			unset($options['data']);
			$this->viewConfig = $options + $this->viewConfig;
		}
		return $this->viewConfig;
	}

	public function set($option, $value = null) {
		if (!is_array($option)) {
			$option = [$option => $value];
		}
		$this->viewData($option);
	}

	public function get($option, $default = null) {
        if (!isset($this->viewConfig['data'][$option])) {
            return $default;
        }
        return $this->viewConfig['data'][$option];
    }
}

?>