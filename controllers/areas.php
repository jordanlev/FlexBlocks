<?php

class AreasController extends ModuleController {

	public function add() {
		$form = $this->buildEditForm();
		$id = $this->processEditForm($form);
		if ($id) {
			$area = $this->fields->get($id);
			$this->session->message('Area "' . $area->label . '" (PageTableExtended field "' . $area->name . '") created');
			$this->redirect(); //pass in no args to redirect to top-level module page
		}
		
		$this->addBreadcrumb('Areas');
		$this->setHeadline('Add New');
		return $form->render(); //no need for a template
	}

	public function edit() {
		$id = (int)$this->input->get('id');
		if (empty($id)) {
			throw new Wire404Exception('Missing area id');
		}

		$area = $this->fields->get($id);
		if (!$area) {
			throw new Wire404Exception('Unknown area (it may have been recently deleted)');
		}

		$form = $this->buildEditForm($area);
		if ($this->processEditForm($form)) {
			$area = $this->fields->get($id);
			$this->session->message('Area "' . $area->label . '" (PageTableExtended field "' . $area->name . '") updated');
			$this->redirect(); //pass in no args to redirect to top-level module page
		}

		$this->addBreadcrumb('Areas');
		$this->setHeadline($area->label);
		return $form->render(); //no need for a template
	}

	public function delete() {
		$id = (int)$this->input->get('id');
		if (empty($id)) {
			throw new Wire404Exception('Missing area id');
		}

		$area = $this->fields->get($id);
		if (!$area) {
			throw new Wire404Exception('Unknown area (it may have already been deleted)');
		}

		$form = $this->buildDeleteForm($area);
		if ($this->processDeleteForm($id, $form)) {
			$this->session->message('Area "' . $area->label . '" (PageTableExtended field "' . $area->name . '") and all of its contents deleted');
			$this->redirect(); //pass in no args to redirect to top-level module page
		}

		$this->set('area', $area);
		$this->set('form', $form);

		$this->setHeadline('Delete');
		$this->addBreadcrumb('Areas');
		$this->addBreadcrumb($area->label, 'areas', 'edit', $area->id);
		return $this->render('delete');
	}

/*****************************************************************************/

	//if $area is null then this is an "add new" form,
	// otherwise it is an "edit existing" form.
	private function buildEditForm($area = null) {
		$form = $this->modules->get("InputfieldForm");
		$action = empty($area) ? $this->url('areas', 'add') : $this->url('areas', 'edit', $area->id);
		$form->attr('action', $action);
		$form->attr('method', "post");

		$value = empty($area) ? '' : FlexBlocksAreaModel::getHandleFromName($area->name);
		$form->append($this->modules->get("InputfieldText")
			->set('label', 'Handle')
			->attr('name', 'handle')
			->attr('value', $value)
			->set('required', 1)
		);

		$value = empty($area) ? '' : $area->label;
		$form->append($this->modules->get("InputfieldText")
			->set('label', 'Label')
			->attr('name', 'label')
			->attr('value', $value)
			->set('required', 1)
		);

		$value = empty($area) ? '' : $area->id;
		$form->append($this->modules->get("InputfieldHidden")
			->attr('name', 'id')
			->attr('value', $value)
		);

		$form->append($this->modules->get("InputfieldSubmit")
			->attr('name', 'submit')
			->attr('icon', 'check-circle')
			->attr('value', 'Save')
		);

		$form->append($this->modules->get("InputfieldButton")
			->attr('name', 'cancel')
			->attr('href', $this->url())
			->attr('icon', 'times-circle')
			->attr('value', 'Cancel')
			->attr('class', 'ui-button ui-widget ui-corner-all ui-priority-secondary')
		);

		return $form;
	}

	private function processEditForm($form) {
		if (!$this->input->post->submit) {
			return null;
		}
		
		$form->processInput($this->input->post);
		if (!FlexBlocksAreaModel::validateUniqueHandle($form->get('handle')->value, $form->get('id')->value)) {
			$form->get('handle')->error('This handle is already in use by another Area');
		}
		
		if ($form->getErrors()) {
			return null;
		}

		$data = array(
			'id' => $form->get('id')->value,
			'handle' => $form->get('handle')->value,
			'label' => $form->get('label')->value,
		);
		$id = FlexBlocksAreaModel::save($data);
		return $id;
	}

/*****************************************************************************/

	private function buildDeleteForm($area) {
		$form = $this->modules->get("InputfieldForm");
		$action = $this->url('areas', 'delete', $area->id);
		$form->attr('action', $action);
		$form->attr('method', "post");

		$form->append($this->modules->get("InputfieldHidden")
			->attr('name', 'id')
			->attr('value', $area->id)
		);
		
		$form->append($this->modules->get("InputfieldSubmit")
			->attr('name', 'submit')
			->attr('icon', 'trash-o')
			->attr('value', 'Delete')
		);

		$form->append($this->modules->get("InputfieldButton")
			->attr('name', 'cancel')
			->attr('href', $this->url())
			->attr('icon', 'reply')
			->attr('value', 'Cancel')
			->attr('class', 'ui-button ui-widget ui-corner-all ui-priority-secondary')
		);

		return $form;

	}

	private function processDeleteForm($id, $form) {
		if (!$this->input->post->submit) {
			return null;
		}

		$form->processInput($this->input->post);

		if ($form->getErrors()) {
			return null;
		}

		FlexBlocksAreaModel::delete($id);
		
		return true;
	}

}