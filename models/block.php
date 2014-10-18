<?php

class FlexBlocksBlockModel {

	const blockContainerTemplateName = 'area-blocks-container';
	const blockContainerPageName = 'area-blocks-container';
	const blockContainerPageTitle = 'Area Blocks';

	const blockTemplatePrefix = 'block_';
	const blockTemplateDirname = 'blocks';
	const blockTemplateFilename = 'view'; //without ".php" extension
	const blockTemplateDispatcherFilename = '_blocks'; //without ".php" extension. Note that we prefix with underscore so PW doesn't think it's available as a template file when user creates new normal templates via the dashboard GUI.

	public static function getAll() {
		return wire('templates')->find('name^=' . self::blockTemplatePrefix . ', sort=name');
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

			$handle = self::getHandleFromName($template->name); //IMPORTANT: Retrieve the template name back from the system, because PW may have sanitized it upon save! (e.g. replace spaces with underscores)
			self::createTemplateDir($handle);
			self::createTemplateFile($handle);

			FlexBlocksAreaModel::makeAllBlocksAvailableToArea(); //re-order all blocks in all areas (by not passing in an $area arg) because we want this new block to be sorted alphabetically within existing ones

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
		$fieldgroup = $template->fieldgroup;

		//remove the system flag so the template can be deleted
		// (note the 2-step process... see comment on the flagSystemOverride constant
		//  in /wire/core/Template.php for details).
		$template->set('flags', Template::flagSystemOverride)->set('flags', 0)->save();
		wire('templates')->delete($template);
		
		wire('fieldgroups')->delete($fieldgroup);
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
	}

	public function installBlockTemplateDispatcherAndDirectory() {
		$config = wire('config');
		$path = $config->paths->templates . self::blockTemplateDispatcherFilename . '.' . $config->templateExtension;
		$contents = self::getBlockTemplateDispatcherFileContents();
		self::createFile($path, $contents);
		self::createTemplateDir();
	}


	public static function uninstallBlockInstanceContainerPageAndTemplate() {
		//Page...
		$page = wire('pages')->get('name='.self::blockContainerPageName);
		if ($page->id) {
			$page->delete();
		}
		
		//Template...
		$template = wire('templates')->get(self::blockContainerTemplateName);
		//remove the system flag so the template can be deleted
		// (note the 2-step process... see comment on the flagSystemOverride constant
		//  in /wire/core/Template.php for details).
		$template->set('flags', Template::flagSystemOverride)->set('flags', 0)->save();
		wire('templates')->delete($template);

		$fieldgroup = wire('fieldgroups')->get(self::blockContainerTemplateName);
		wire('fieldgroups')->delete($fieldgroup);
	}

/*****************************************************************************/

	private static function createTemplateDir($subdir = '') {
		$path = wire('config')->paths->templates . self::blockTemplateDirname;
		if (!empty($subdir)) {
			$path .= '/' . trim($subdir, '/');
		}

		wireMkdir($path);
	}
	
	private static function createTemplateFile($block_handle) {
		$config = wire('config');
		$path = $config->paths->templates . self::blockTemplateDirname . '/' . $block_handle . '/' . self::blockTemplateFilename . '.' . $config->templateExtension;
		$contents = '<?php echo "[to display this block\'s content, edit this file on your server: ".__FILE__."]"; ?>';
		self::createFile($path, $contents);
	}

	private static function renameBlockTemplateDir($old_template_name, $new_template_name) {
		$old_dir = self::getHandleFromName($old_template_name);
		$new_dir = self::getHandleFromName($new_template_name);
		$path = wire('config')->paths->templates . self::blockTemplateDirname;
		rename("{$path}/{$old_subdir}", "{$path}/{$new_subdir}");
	}

	private static function createFile($path, $contents = '') {
		if (!file_exists($path)) {
			file_put_contents($path, $contents);
			wireChmod($path);
		}
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
 * It "routes" each block's data to a sub-template in the /blocks/ directory.
 * In this way, each block has its own mini-template file,
 * and their fields are easily accessible via the `\$block` variable
 * (so instead of `\$page->my_field`, you will use `\$block->my_field`).
 */ 

//settings
\$block_prefix = '{$block_prefix}';
\$field_prefix_format = '{$field_prefix_format}'; //%d gets replaced by block id
\$templates_dirname = '{$templates_dirname}';
\$templates_filename = '{$templates_filename}';

//un-prefix all field names so they can be used more easily in the block template
\$block = new StdClass;
foreach (\$page->fields as \$field) {
	\$field_prefix = sprintf(\$field_prefix_format, \$page->template->id);
	if (strpos(\$field->name, \$field_prefix) === 0) {
		\$field_name = substr(\$field->name, strlen(\$field_prefix));
		\$block->\$field_name = \$field;
	}
}

//include the block template file, providing it with some useful variables
\$vars = array(
	'block' => \$block,
	'is_edit_mode' => (bool)\$options['pageTableExtended'], //feature of the PageTableExtended fieldtype that lets us know if this is being displayed in edit form (as opposed to page view)
);
\$block_template_dirname = substr(\$page->template->name, strlen(\$block_prefix));
wireIncludeFile("{\$templates_dirname}/{\$block_template_dirname}/{\$templates_filename}", \$vars);
CONTENTS;
		return $contents;
	}

}