<?php

class FieldsController extends ModuleController {

	public function before_action($action) {
		$this->addBreadcrumb('Blocks');
	}

	public function index() {
		$id = (int)$this->input->get('id');
		if (empty($id)) {
			throw new Wire404Exception('Missing block id');
		}

		$block = $this->templates->get($id);
		if (!$block) {
			throw new Wire404Exception('Unknown block (it may have been recently deleted)');
		}

		$fields = FlexBlocksFieldModel::getByBlockKeyedByHandle($block);
		
		$this->setHeadline('Fields');
		$this->addBreadcrumb($block->label, 'blocks', 'edit', $block->id);
		$this->set('block', $block);
		$this->set('fields', $fields);
		return $this->render('index');
	}

	public function add() {
		$block_id = (int)$this->input->get('id');
		if (empty($block_id)) {
			throw new Wire404Exception('Missing block id');
		}

		$block = $this->templates->get($block_id);
		if (!$block) {
			throw new Wire404Exception('Unknown block (it may have been recently deleted)');
		}

		$process_field_module = $this->modules->get('ProcessField');
		$form = $this->buildFieldForm($process_field_module, $block);
		$field_id = $this->processFieldForm($process_field_module, $block, $form);
		if ($field_id) {
			$this->session->message('Field created -- now configure additional settings...');
			$this->redirect('fields', 'edit', $field_id);
		}

		$this->setHeadline('Add New');
		$this->addBreadcrumb($block->label, 'blocks', 'edit', $block_id);
		$this->addBreadcrumb('Fields', 'fields', 'index', $block_id);
		return $form->render(); //no need for a template
	}

	public function edit() {
		$field_id = (int)$this->input->get('id');
		if (empty($field_id)) {
			throw new Wire404Exception('Missing field id');
		}

		$field = $this->fields->get($field_id);
		if (!$field) {
			throw new Wire404Exception('Unknown field (it may have been recently deleted)');
		}

		$block = FlexBlocksBlockModel::getByField($field);
		if (!$block) {
			throw new Wire404Exception('Unknown block (it may have been recently deleted)');
		}

		$this->modules->get('JqueryWireTabs'); //do this so that wiretabs javascript gets loaded with the page (otherwise nothing renders!)

		$process_field_module = $this->modules->get('ProcessField');
		$form = $this->buildFieldForm($process_field_module, $block, $field);
		$field_id = $this->processFieldForm($process_field_module, $block, $form);
		if ($field_id) {
			$field = $this->fields->get($field_id); //re-retrieve this so we have newly-updated info
			$this->session->message('Field "' . $field->label . '" in block "' . $block->label . '" updated');
			$this->redirect('fields', 'index', $block->id);
		}

		$this->setHeadline($field->label);
		$this->addBreadcrumb($block->label, 'blocks', 'edit', $block->id);
		$this->addBreadcrumb('Fields', 'fields', 'index', $block->id);
		return $form->render(); //no need for a template
	}

	public function sort() {
		$block_id = (int)$this->input->get('id');
		//Don't bother validating $block_id... if it's invalid the re-sort query will have no effect.
		if ($this->input->post->ids) {
			$this->session->CSRF->validate();
			FlexBlocksFieldModel::sort($block_id, $this->input->post->ids);
		}
		
		exit;
	}

	public function delete() {
		$id = (int)$this->input->get('id');
		if (empty($id)) {
			throw new Wire404Exception('Missing field id');
		}

		$field = $this->fields->get($id);
		if (!$field) {
			throw new Wire404Exception('Unknown field (it may have already been deleted)');
		}

		$block = FlexBlocksBlockModel::getByField($field);
		if (!$block) {
			throw new Wire404Exception('Unknown block (it may have been recently deleted)');
		}

		$form = $this->buildDeleteForm($field, $block->id);
		if ($this->processDeleteForm($id, $form)) {
			$this->session->message('Field "' . $field->label . '" (and all of its contents) in block "' . $block->label . '" deleted');
			$this->redirect('fields', 'index', $block->id);
		}

		$this->set('field', $field);
		$this->set('form', $form);

		$this->addBreadcrumb($block->label, 'blocks', 'edit', $block->id);
		$this->addBreadcrumb('Fields', 'fields', 'index', $block->id);
		$this->addBreadcrumb($field->label, 'fields', 'edit', $field->id);
		$this->setHeadline('Delete');

		return $this->render('delete');
	}

/*****************************************************************************/

	//SIDE-EFFECT WARNING: This function overwrites wire('input')->get->id !
	private function buildFieldForm($process_field_module, $block, $field = null) {
		//Unfortunately the only way we can communicate the field id (or lack thereof)
		// to the 'ProcessField' module is via $this->input->get.
		$this->input->get->id = empty($field) ? null : $field->id; //important: explicitly set to null if no field was provided, in case there was an actual 'id' querystring arg for some other purpose (e.g. the parent id of an add-new operation)
		$form = $process_field_module->buildEditForm();

		//overwrite form action (so it posts back to ourself, not 'save')
		$action = empty($field) ? $this->url('fields', 'add', $block->id) : $this->url('fields', 'edit', $field->id);
		$form->attr('action', $action);

		//repurpose the "name" field as our "handle" field
		$input_name = $form->get('name');
		$input_name->label = 'Handle';

		//If this is an "add new" form,
		// rename the save button so it's not as confusing
		// when we redirect back to the same page for further configuration
		if (empty($field)) {
			$input_save = $form->get('submit_save_field');
			$input_save->value = 'Save and go to configuration...';
		}

		//Add a cancel button
		$form->append($this->modules->get("InputfieldButton")
			->attr('name', 'cancel')
			->attr('href', $this->url('fields', 'index', $block->id))
			->attr('icon', 'times-circle')
			->attr('value', 'Cancel')
			->attr('class', 'ui-button ui-widget ui-corner-all ui-priority-secondary')
		);


		//if editing an existing field...
		if ($field) {
			//convert the field name to the "handle" (remove our prefix)
			$input_name->value = FlexBlocksFieldModel::getHandleFromName($block->id, $field->name);

			//remove the "Advanced", "Info", and "Delete" tabs to avoid all sorts of complication when processing the form
			$this->removeWireTabFromForm($form, 'advanced');
			$this->removeWireTabFromForm($form, 'info');
			$this->removeWireTabFromForm($form, 'delete');
		}

		return $form;
	}

	private function removeWireTabFromForm($form, $tab_id) {
		//Wire tabs cannot be removed from the form by key,
		// so we must loop through all form elements
		// and when we find the right one we remove by array index.
		foreach ($form->children as $index => $element) {
			if ($element->attr('id') == $tab_id) {
				$form->remove($index);
			}
		}
	}

	private function processFieldForm($process_field_module, $block, $form) {
		if (!$this->input->post->submit_save_field) {
			return null;
		}
		
		//convert the handle to a full field name
		$this->input->post->name = FlexBlocksFieldModel::getNameFromHandle($block->id, $this->input->post->name);

		$form->processInput($this->input->post);

		if ($form->getErrors()) {
			return null;
		}
		
		$id = $form->get('id')->value;
		$field = FlexBlocksFieldModel::save($process_field_module, $form, $id, $block);

		//check if user changed field type of an existing field...
		$chosen_field_type = $form->get('type')->value;
		if (!empty($id) && ($field->type->className() != $chosen_field_type)) {
			//Note that we are redirecting outside our module,
			// so don't use $this->url() or $this->redirect().
			$this->session->redirect($this->config->urls->admin . "setup/field/changeType?id={$field->id}&type={$chosen_field_type}");
		}

		return $field->id;
	}

/*****************************************************************************/

	private function buildDeleteForm($field, $block_id) {
		$form = $this->modules->get("InputfieldForm");
		$action = $this->url('fields', 'delete', $field->id);
		$form->attr('action', $action);
		$form->attr('method', "post");

		$form->append($this->modules->get("InputfieldHidden")
			->attr('name', 'id')
			->attr('value', $field->id)
		);
		
		$form->append($this->modules->get("InputfieldSubmit")
			->attr('name', 'submit')
			->attr('icon', 'trash-o')
			->attr('value', 'Delete')
		);

		$form->append($this->modules->get("InputfieldButton")
			->attr('name', 'cancel')
			->attr('href', $this->url('fields', 'index', $block_id))
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

		FlexBlocksFieldModel::delete($id);
		
		return true;
	}

}