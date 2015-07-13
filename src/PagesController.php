<?php

namespace Naf\Action;

class PagesController extends Controller {

	public function view() {
		$this->viewConfig([
			'view' => $this->request->params['path'] ?: 'default'
		]);
	}
}

?>