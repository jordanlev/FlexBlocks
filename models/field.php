<?php

class FlexBlocksFieldModel {
	
	const fieldPrefixFormat = 'block%d_'; //%d gets replaced by block (template) id
	
	public static function getByBlockKeyedByHandle($block) {
		$fields = array();
		foreach ($block->fields as $field) {
			$key = self::getHandleFromName($block->id, $field->name);
			$fields[$key] = $field;
		}
		return $fields;
	}

	public static function getHandleFromName($block_id, $field_name) {
		$prefix = sprintf(self::fieldPrefixFormat, $block_id);
		if (strpos($field_name, $prefix) !== 0) {
			throw new WireException('FlexBlocksFieldModel::getHandleFromName was called with an invalid block id and/or field name');
		}
		return substr($field_name, strlen($prefix));
	}

	public static function getNameFromHandle($block_id, $field_handle) {
		return empty($field_handle) ? '' : sprintf(self::fieldPrefixFormat, $block_id) . $field_handle;
	}

	//$process_field_module is an instantiated ProcessField module.
	//$form is whatever that object returns from its buildEditForm() method.
	//$id is the field id, or NULL if creating a new field
	//$block only needs to be provided if creating a new field
	// (so we know what template to assign it to).
	//Returns the created/updated $field object.
	public static function save($process_field_module, $form, $id, $block = null) {
		$is_new = empty($id);
		if (!$is_new) {
			//remove the system flag so the field info can be modified
			// (note the 2-step process... see comment on the flagSystemOverride constant
			//  in /wire/core/Field.php for details).
			//We unfortunately must do this BEFORE calling ProcessField->saveInputfields()
			// in the next step, otherwise that call fails.
			wire('fields')->get($id)->set('flags', Field::flagSystemOverride)->set('flags', 0)->save();
		}
		
		//We need to call $process_field_module->saveInputfields()
		// (which gathers all the form input data into a field object),
		//BUT that method is protected, so we cheat and use Reflection to call it anyway
		$method = new ReflectionMethod('ProcessField', 'saveInputfields');
		$method->setAccessible(true);
		$method->invoke($process_field_module, $form);

		$field = $process_field_module->getField();
		$field->save();

		//Hide this field from the admin dashboard "Templates" nav
		// (via flagSystem) and from the list of fields that can be
		// assigned to templates (via flagPermanent).
		// Note that we are either doing this for the first time
		// if we created a new field, or we're undoing what we did
		// up at the top of this function if editing an existing field.
		$field->set('flags', (Field::flagSystem | Field::flagPermanent))->save();

		if ($is_new) {
			//Assign the new field to the appropriate block (template)
			$block->fieldgroup->add($field)->save();
			
			//Fire off the 'added' event in case others are hooked into it
			$process_field_module->fieldAdded($field);
		} else {
			//Fire off the 'saved' event in case others are hooked into it
			$process_field_module->fieldSaved($field);
		}
		
		return $field;
	}

	public static function sort($block_id, $ordered_field_ids_string) {
		$field_ids = explode(',', $ordered_field_ids_string);
		$field_ids = array_map(function($id) { return (int)$id; }, $field_ids); //ensure integer-ness
		$field_ids = array_filter($field_ids, function($id) { return ($id > 1); }); //ensure positive non-zero numbers

		$fieldgroup = wire('templates')->get($block_id)->fieldgroup;
		foreach ($field_ids as $field_id) {
			$field = $fieldgroup->getField($field_id, true); // get in context
			$fieldgroup->append($field);
		}
		$fieldgroup->save();
	}

	public static function delete($id) {
		$field = wire('fields')->get($id);

		$block = FlexBlocksBlockModel::getByField($field);
		$fieldgroup = $block->fieldgroup;
		$fieldgroup->remove($field);
		$fieldgroup->save(); //we cannot chain this particular call (because Fieldgroup::remove does not return the field object)
		
		//remove the system flag so the field can be deleted
		// (note the 2-step process... see comment on the flagSystemOverride constant
		//  in /wire/core/Field.php for details).
		$field->set('flags', Field::flagSystemOverride)->set('flags', 0)->save();
		wire('fields')->delete($field);
	}
}