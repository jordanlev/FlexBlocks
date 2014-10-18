<?php

class MainController extends ModuleController {

	public function index() {
		$this->set('blocks', FlexBlocksBlockModel::getAll());
		$this->set('areas', FlexBlocksAreaModel::getAll());
		return $this->render('index');
	}

}