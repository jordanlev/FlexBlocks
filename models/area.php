<?php

class FlexBlocksAreaModel {

	const areaFieldPrefix = 'area_';

	public static function getAll() {
		return wire('fields')->find('name^=' . self::areaFieldPrefix . ', type=FieldtypePageTableExtended, sort=title');
	}

	public static function makeAllBlocksAvailableToArea($area = null) {
		$alphabetical_block_ids = array();
		$blocks = FlexBlocksBlockModel::getAll();
		foreach ($blocks as $block) {
			$alphabetical_block_ids[] = $block->id;
		}

		$areas = is_null($area) ? self::getAll() : array($area);
		foreach ($areas as $area) {
			$area->set('template_id', $alphabetical_block_ids)->save();
		}
	}

	public static function getHandleFromName($area_name) {
		if (strpos($area_name, self::areaFieldPrefix) !== 0) {
			throw new WireException('FlexBlocksAreaModel::getHandleFromName was called with an invalid name');
		}
		return substr($area_name, strlen(self::areaFieldPrefix));
		
	}

	//Validates that the given handle is not already in use by another area (field).
	//Returns false if the given handle is not empty and already used by another area
	//Returns true if given handle is empty or if it is not in use (or only in use
	// by the area with the given "ignore_id" value).
	public static function validateUniqueHandle($handle, $ignore_id = null) {
		$is_unique = true;
		if (!empty($handle)) {
			$field_name = self::areaFieldPrefix . $handle;
			$existing_field_with_name = wire('fields')->get($field_name);
			if ($existing_field_with_name && ($existing_field_with_name->id != $ignore_id)) {
				$is_unique = false;
			}
		}
		return $is_unique;
	}

	//Pass in an array of validated data (id, handle, and label).
	//If id is empty, we create a new area (field), otherwise we update existing.
	//Returns the area id.
	public static function save($validated_data) {
		$id = empty($validated_data['id']) ? null : (int)$validated_data['id'];
		$handle = $validated_data['handle'];
		$label = $validated_data['label'];
		
		$name = self::areaFieldPrefix . $handle;
		$is_new = empty($id);
		
		//create new field...
		if ($is_new) {
			$field = new Field();
			$field->name = $name;
			$field->label = $label;
			$field->type = wire('modules')->get('FieldtypePageTableExtended');
			$field->parent_id = wire('pages')->get('name='.FlexBlocksBlockModel::blockContainerPageName)->id; //corresponds to "Selet a parent for items" option in dashboard GUI
			$field->trashOnDelete = 2; //corresponds to "Delete Them" option in "Page Behaviors: Delete" section of dashboard GUI
			$field->unpubOnTrash = 1; //corresponds to "Unpublish them" option in "Page Behaviors: Trash" section of dashboard GUI
			$field->unpubOnUnpub = 1; //corresponds to "Unpublish them" option in "Page Behaviors: Unpublish" section of dashboard GUI
			$field->columns = 'title'; //corresponds to "Table fields to display in admin" setting. This is not actually needed for PageTableExtended, but the underlying PageTable field requires something.
			$field->nameFormat = 'area-main-block'; //corresponds to "Automatic Page Name Format" setting. Will result in block instances being names area-main-block-1, area-main-block-2, area-main-block-3, etc.
			$field->renderLayout = 1; //corresponds to checking the "Render Layout instead of table rows?" box in the dashboard GUI
			$field->flags = Field::flagSystem; //no equivalent in dashboard (we do this to hide it from dashboard "Fields" nav menu)
			$field->save();

			self::makeAllBlocksAvailableToArea($field);

		//update existing field...
		} else {
			$field = wire('fields')->get($id);

			//remove the system flag so the field name can be modified
			// (note the 2-step process... see comment on the flagSystemOverride constant
			//  in /wire/core/Field.php for details).
			$field->set('flags', Field::flagSystemOverride)->set('flags', 0)->save();

			$field->name = $name;
			$field->label = $label;
			$field->save();

			//now add back the system flag
			$field->set('flags', Field::flagSystem)->save();
		}
		
		return $field->id;
	}

	public static function delete($id) {
		$field = wire('fields')->get($id);
		//remove the system flag so the field can be deleted
		// (note the 2-step process... see comment on the flagSystemOverride constant
		//  in /wire/core/Field.php for details).
		$field->set('flags', Field::flagSystemOverride)->set('flags', 0)->save();
		wire('fields')->delete($field);
	}

	public static function uninstallAllAreas() {
		foreach (self::getAll() as $area) {
			self::delete($area->id);
		}
	}
}