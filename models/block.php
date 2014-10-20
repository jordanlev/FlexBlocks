<?php

class FlexBlocksBlockModel {

	const blockContainerTemplateName = 'flexblocks-storage';
	const blockContainerPageName = 'flexblocks-storage';
	const blockContainerPageTitle = 'FlexBlocks Block Storage';

	const blockTemplatePrefix = 'block_';
	const blockTemplateDirname = 'blocks';
	const blockTemplateFilename = 'view'; //without ".php" extension
	const blockTemplateDispatcherFilename = '_blocks'; //without ".php" extension. Note that we prefix with underscore so PW doesn't think it's available as a template file when user creates new normal templates via the dashboard GUI.

	public static function getAll() {
		return wire('templates')->find('name^=' . self::blockTemplatePrefix . ', sort=label');
	}

	public static function getByField($field) {
		$templates = $field->getTemplates(); //for some reason, $field->templates doesn't work properly (it seems to return all templates)
		foreach ($templates as $template) {
			if (strpos($template->name, self::blockTemplatePrefix) === 0) {
				return $template;
			}
		}
		return null;
	}

	public static function getHandleFromName($block_name) {
		if (strpos($block_name, self::blockTemplatePrefix) !== 0) {
			throw new WireException('FlexBlocksBlockModel::getHandleFromName was called with an invalid name');
		}
		return substr($block_name, strlen(self::blockTemplatePrefix));
	}

	//Validates that the given handle is not already in use by another block (template).
	//Returns false if the given handle is not empty and already used by another block
	//Returns true if given handle is empty or if it is not in use (or only in use
	// by the block with the given "ignore_id" value).
	public static function validateUniqueHandle($handle, $ignore_id = null) {
		$is_unique = true;
		if (!empty($handle)) {
			$template_name = self::blockTemplatePrefix . $handle;
			$existing_template_with_name = wire('templates')->get($template_name);
			if ($existing_template_with_name && ($existing_template_with_name->id != $ignore_id)) {
				$is_unique = false;
			}
		}
		return $is_unique;
	}

	//Pass in an array of validated data (id, handle, and label).
	//If id is empty, we create a new block (template), otherwise we update existing.
	//Returns the block id.
	public static function save($validated_data) {
		$id = empty($validated_data['id']) ? null : (int)$validated_data['id'];
		$handle = $validated_data['handle'];
		$label = $validated_data['label'];
		
		$name = self::blockTemplatePrefix . $handle;
		$is_new = empty($id);
		
		//create new template...
		if ($is_new) {
			$fieldgroup = new Fieldgroup();
			$fieldgroup->set('name', $name);
			$fieldgroup->save();

			// Note that we are not adding a "title" field to this fieldgroup.
			// When creating templates via the API, a title field is not required
			// (unlike the admin dashboard GUI, which does require it).
			
			$template = new Template();
			$template->name = $name;
			$template->label = $label;
			$template->fieldgroup = $fieldgroup;
			$template->noChildren = 1; //Equivalent to checking "No" for the "May pages using this template have children?" setting.
			$template->parentTemplates = array(wire('templates')->get(self::blockContainerTemplateName)->id); //blocks are all stored under the special "area block storage" admin page (which was created by the module upon installation). Setting this has the nice side-effect of keeping the block template out of the list of available templates that users can choose from when adding new pages to the site.
			$template->noChangeTemplate = 1; // don't allow pages using this template to change their template
			$template->noShortcut = 1; // don't allow pages using this template to appear in shortcut "add new page" menu
			$template->altFilename = self::blockTemplateDispatcherFilename;
			$template->noUnpublish = 1; //don't allow unpublished pages
			$template->noSettings = 1;
			$template->flags = Template::flagSystem; //no equivalent in dashboard (we do this to hide it from dashboard "Templates" nav menu)
			$template->save();

			//Note that when setting up directories/files,
			// we retrieve the template name back from the object
			// (don't just use $name from above),
			// because PW may have sanitized it upon save
			// (e.g. replace spaces with underscores, etc).
			self::createTemplateDir($template->name);
			self::createTemplateFile($template->name); //if this fails, we don't throw an exception... user will just have to deal with it (shouldn't be hard to figure out since they have instructions to edit this file anyway).

			FlexBlocksAreaModel::assignAllBlocksToArea(); //re-order all blocks in all areas (by not passing in an $area arg) because we want this new block to be sorted alphabetically within existing ones

		//update existing template...
		} else {
			$template = wire('templates')->get($id);

			//remove the system flag so the template name can be modified
			// (note the 2-step process... see comment on the flagSystemOverride constant
			//  in /wire/core/Template.php for details).
			$template->set('flags', Template::flagSystemOverride)->set('flags', 0)->save();

			$old_name = $template->name;
			$template->name = $name;
			$template->label = $label;
			$template->save();

			//now add back the system flag
			$template->set('flags', Template::flagSystem)->save();

			//rename the block template folder
			$new_name = $template->name; //IMPORTANT: Retrieve the template name back from the system, because PW may have sanitized it upon save! (e.g. replace spaces with underscores)
			if ($old_name != $new_name) {
				self::renameBlockTemplateDir($old_name, $new_name);
			}
		}
		
		return $template->id;
	}

	public static function delete($id) {
		$template = wire('templates')->get($id);

		//unassign the block from all areas
		FlexBlocksAreaModel::unassignBlockFromArea($template);

		//delete all of this block's fields
		foreach ($template->fields as $field) {
			FlexBlocksFieldModel::delete($field->id);
		}

		//grab a reference to the fieldgroup before we delete the template
		$fieldgroup = $template->fieldgroup;

		//remove the system flag so the template can be deleted
		// (note the 2-step process... see comment on the flagSystemOverride constant
		//  in /wire/core/Template.php for details).
		$template->set('flags', Template::flagSystemOverride)->set('flags', 0)->save();
		wire('templates')->delete($template);
		
		wire('fieldgroups')->delete($fieldgroup);
	}

	//If no template_name is provided, we return the path to the top-level /templates/blocks directory.
	//If a block's template_name is provided, we return the path to that block's specific subdirectory within /templates/blocks/.
	public static function getBlockTemplateDirectoryPath($template_name = '') {
		$path = wire('config')->paths->templates . self::blockTemplateDirname;
		if (!empty($template_name)) {
			$path .= '/' . self::getHandleFromName($template_name);
		}

		return $path;
	}

	public static function getBlockTemplateViewFilePath($template_name) {
		return self::getBlockTemplateDirectoryPath($template_name) . '/' . self::blockTemplateFilename . '.' . wire('config')->templateExtension;
	}
	
	public static function getBlockTemplateDispatcherFilePath() {
		$config = wire('config');
		return $config->paths->templates . self::blockTemplateDispatcherFilename . '.' . $config->templateExtension;
	}	

	public static function installBlockInstanceContainerTemplateAndPage() {
		//Template...
		$fieldgroup = new Fieldgroup();
		$fieldgroup->set('name', self::blockContainerTemplateName);
		$fieldgroup->add(wire('fields')->get('title'));
		$fieldgroup->save();
		$template = new Template();
		$template->name = self::blockContainerTemplateName;
		$template->fieldgroup = $fieldgroup;
		$template->noParents = 1; //Equivalent to checking "No" for the "Can this template be used for new pages?" setting.
		$template->parentTemplates = array(2); //hide this template from the list of choosable templates when editing existing pages. Do this by saying that only pages using the 'admin' template can have pages of this template as children.
		$template->flags = Template::flagSystem; //no equivalent in dashboard (we do this to hide it from dashboard "Templates" nav menu)
		$template->noChangeTemplate = 1; // don't allow pages using this template to change their template
		$template->noShortcut = 1; // don't allow pages using this template to appear in shortcut "add new page" menu
		$template->save();

		//Page...
		$adminRoot = wire('pages')->get(wire('config')->adminRootPageID);
		$page = new Page($template);
		$page->parent = $adminRoot;
		$page->status = Page::statusHidden | Page::statusLocked;
		$page->name = self::blockContainerPageName;
		$page->title = self::blockContainerPageTitle;
		$page->sort = $adminRoot->numChildren;
		$page->save();

		return array($page->path, $page->title);
	}

	public function installBlockTemplateDispatcherAndDirectory() {
		$file_path = self::getBlockTemplateDispatcherFilePath();
		$contents = self::getBlockTemplateDispatcherFileContents();
		if (!self::createFile($file_path, $contents)) {
			if (is_file($file_path)) {
				throw new WireException('Could not create dispatcher file "' . $file_path . '" because a file already exists there. You must delete or rename the existing file before this module can be installed.');
			} else {
				throw new WireException('Could not create dispatcher file "' . $file_path . '" (possibly due to file permissions on the server).');
			}
		} else if (file_get_contents($file_path) == '') {
			throw new WireException('Dispatcher file "' . $file_path . '" was created but generated code could not be added to it (possibly due to file permissions on the server).');
		}

		$dir = self::getBlockTemplateDirectoryPath(); //<--this is for reporting purposes (we don't need it for functionality, because the createTemplateDir() function gets it on its own)
		if (!self::createTemplateDir()) {
			throw new WireException('Installation failed: could not create block template directory at "' . $dir . '".');
		}

		return array($file_path, $dir);
	}


	public static function uninstallBlockInstanceContainerPageAndTemplate() {
		//grab page properties now before it gets deleted so we can return them
		$page_title = $page->title;
		$page_path = $page->path;

		//Delete the page...
		$page = wire('pages')->get('name='.self::blockContainerPageName);
		if ($page->id) {
			$page->delete();
		}
		
		//Delete the template and fieldgroup...
		$template = wire('templates')->get(self::blockContainerTemplateName);
		//remove the system flag so the template can be deleted
		// (note the 2-step process... see comment on the flagSystemOverride constant
		//  in /wire/core/Template.php for details).
		$template->set('flags', Template::flagSystemOverride)->set('flags', 0)->save();
		wire('templates')->delete($template);

		$fieldgroup = wire('fieldgroups')->get(self::blockContainerTemplateName);
		wire('fieldgroups')->delete($fieldgroup);

		return array($page_path, $page_title);
	}

	public static function uninstallBlockTemplateDispatcher() {
		$path = self::getBlockTemplateDispatcherFilePath();
		unlink($path);
		return $path;
	}

	public static function uninstallAllBlocksAndFields() {
		foreach (self::getAll() as $block) {
			self::delete($block->id);
		}
	}


/*****************************************************************************/

	//Attempts to create empty directory for block templates in the site templates dir.
	//Pass an empty string to create the top-level "blocks" directory,
	// or pass in the name of a block template (the full prefixed name, not the "handle")
	// to create a subdirectory for a new block.
	//Returns true upon success (or if a directory already exists); false upon failure.
	private static function createTemplateDir($template_name = '') {
		$path = self::getBlockTemplateDirectoryPath($template_name);
		return wireMkdir($path);
	}
	
	private static function createTemplateFile($template_name) {
		$path = self::getBlockTemplateViewFilePath($template_name);
		$contents = self::getBlockTemplateViewFileContents();
		return self::createFile($path, $contents);
	}

	private static function renameBlockTemplateDir($old_template_name, $new_template_name) {
		$old_dir = self::getHandleFromName($old_template_name);
		$new_dir = self::getHandleFromName($new_template_name);
		$path = self::getBlockTemplateDirectoryPath();
		rename("{$path}/{$old_subdir}", "{$path}/{$new_subdir}");
	}

	//Attempts to create a new file at the given path with the given contents
	// (and set permissions on it based on processwire cmhod settings).
	//Returns true if file was successfully created.
	//Returns false if file wasn't created or if a file already existed at the path.
	private static function createFile($path, $contents = '') {
		if (file_exists($path)) {
			return false;
		}

		file_put_contents($path, $contents);
		if (!file_exists($path)) {
			return false;
		}

		wireChmod($path);
		
		return file_exists($path);
	}

	private static function getBlockTemplateViewFileContents() {
		$contents = <<<CONTENTS
<?php
/**
 * This file was generated by the FlexBlocks module.
 *
 * Within this file you can access field data via the \$block variable,
 * for example (assuming you have fields with handles "title" and "description"):
 *
 *     <h2><?php echo \$block->title; ?></h2>
 *     <div><?php echo \$block->description; ?></div>
 *
 * This markup will be outputted for every block
 * that the site editor adds to an "area" on a page.
 *
 * You can also check the \$is_page_table variable to determine
 * if this is the admin edit mode (as opposed to the front-end display).
 * You might want to use this to output truncated content
 * for the edit table (for example) -- but it is completely optional.
 */

if (\$is_page_table) {
	echo '[to display this block\'s content, edit this file on your server: ' . __FILE__ . ']';
}
CONTENTS;
		return $contents;
	}

	private static function getBlockTemplateDispatcherFileContents() {
		$block_prefix = self::blockTemplatePrefix;
		$field_prefix_format = FlexBlocksFieldModel::fieldPrefixFormat;
		$templates_dirname = self::blockTemplateDirname;
		$templates_filename = self::blockTemplateFilename . '.' . wire('config')->templateExtension;

		$contents = <<<CONTENTS
<?php
/**
 * This template is for the "FlexBlocks" module.
 * It routes each block display to its own mini-template in the /blocks/ directory,
 * and provides a \$block variable to the mini-templates for easily accessing fields
 * via their "handle" (e.g. \$block->my_field instead of \$page->block44_my_field).
 */

//settings
\$block_prefix = '{$block_prefix}';
\$field_prefix_format = '{$field_prefix_format}'; //%d gets replaced by block id
\$templates_dirname = '{$templates_dirname}';
\$templates_filename = '{$templates_filename}';

//un-prefix all field names so they can be used more easily in the block template
\$block = new StdClass;
foreach (\$page->fields as \$field) {
	\$page_field_prefix = sprintf(\$field_prefix_format, \$page->template->id);
	\$page_field_name = \$field->name;
	if (strpos(\$page_field_name, \$page_field_prefix) === 0) {
		\$block_field_name = substr(\$page_field_name, strlen(\$page_field_prefix));
		\$block->\$block_field_name = \$page->\$page_field_name;
	}
}

//include the block template file, providing it with some useful variables
\$vars = array(
	'block' => \$block,
	'is_page_table' => (bool)\$options['pageTableExtended'], //feature of the PageTableExtended fieldtype that lets us know if this is being displayed in edit form (as opposed to page view)
);
\$block_template_dirname = substr(\$page->template->name, strlen(\$block_prefix));
wireIncludeFile("{\$templates_dirname}/{\$block_template_dirname}/{\$templates_filename}", \$vars);
CONTENTS;
		return $contents;
	}

}