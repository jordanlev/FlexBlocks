<?php

class BlocksController extends ModuleController {

	public function add() {
		$form = $this->buildEditForm();
		$id = $this->processEditForm($form);
		if ($id) {
			$this->session->message('Block created!');
			$this->redirect(); //pass in no args to redirect to top-level module page
		}

		$this->addBreadcrumb('Blocks');
		$this->setHeadline('Add New');
		return $form->render(); //no need for a template
	}

	public function edit() {
		$id = (int)$this->input->get('id');
		if (empty($id)) {
			throw new Wire404Exception('Missing block id');
		}

		$block = $this->templates->get($id);
		if (!$block) {
			throw new Wire404Exception('Unknown block (it may have been recently deleted)');
		}

		$form = $this->buildEditForm($block);
		if ($this->processEditForm($form)) {
			$this->session->message('Block saved!');
			$this->redirect(); //pass in no args to redirect to top-level module page
		}

		$this->addBreadcrumb('Blocks');
		$this->setHeadline($block->label);
		return $form->render(); //no need for a template
	}

	public function delete() {
		$id = (int)$this->input->get('id');
		if (empty($id)) {
			throw new Wire404Exception('Missing block id');
		}

		$block = $this->templates->get($id);
		if (!$block) {
			throw new Wire404Exception('Unknown block (it may have already been deleted)');
		}

		$form = $this->buildDeleteForm($block);
		if ($this->processDeleteForm($id, $form)) {
			$this->session->message('Block (and all of its contents) deleted');
			$this->redirect(); //pass in no args to redirect to top-level module page
		}

		$this->set('block', $block);
		$this->set('form', $form);

		$this->setHeadline('Delete');
		$this->addBreadcrumb('Blocks');
		$this->addBreadcrumb($block->label, 'blocks', 'edit', $block->id);
		return $this->render('delete');
	}

/*****************************************************************************/

	//if $block is null then this is an "add new" form,
	// otherwise it is an "edit existing" form.
	private function buildEditForm($block = null) {
		$form = $this->modules->get("InputfieldForm");
		$action = empty($block) ? $this->url('blocks', 'add') : $this->url('blocks', 'edit', $block->id);
		$form->attr('action', $action);
		$form->attr('method', "post");

		$value = empty($block) ? '' : FlexBlocksBlockModel::getHandleFromName($block->name);
		$form->append($this->modules->get("InputfieldText")
			->set('label', 'Handle')
			->attr('name', 'handle')
			->attr('value', $value)
			->set('required', 1)
		);

		$value = empty($block) ? '' : $block->label;
		$form->append($this->modules->get("InputfieldText")
			->set('label', 'Label')
			->attr('name', 'label')
			->attr('value', $value)
			->set('required', 1)
		);

		$value = empty($block) ? '' : $block->id;
		$form->append($this->modules->get("InputfieldHidden")
			->attr('name', 'id')
			->attr('value', $value)
		);

		$form->append($this->modules->get("InputfieldSubmit")
			->attr('name', 'submit')
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
		if (!FlexBlocksBlockModel::validateUniqueHandle($form->get('handle')->value, $form->get('id')->value)) {
			$form->get('handle')->error('This handle is already in use by another Block');
		}
		
		if ($form->getErrors()) {
			return null;
		}

		$data = array(
			'id' => $form->get('id')->value,
			'handle' => $form->get('handle')->value,
			'label' => $form->get('label')->value,
		);
		$id = FlexBlocksBlockModel::save($data);
		return $id;
	}

/*****************************************************************************/

	private function buildDeleteForm($block) {
		$form = $this->modules->get("InputfieldForm");
		$action = $this->url('blocks', 'delete', $block->id);
		$form->attr('action', $action);
		$form->attr('method', "post");

		$form->append($this->modules->get("InputfieldHidden")
			->attr('name', 'id')
			->attr('value', $block->id)
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

		FlexBlocksBlockModel::delete($id);
		
		return true;
	}
}