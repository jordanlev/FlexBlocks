<?php

/***
 * ModuleController class, v1.0 (2014-10-15), by Jordan Lev <processwire@jordanlev.com>
 *
 * Provides a more Rails/MVC-esque architecture to a Process Module.
 * 
 * Routing is handled via one urlSegment for the "controller"
 *  (which corresponds to an `executeSomeThing()` method in the main module file)
 *  and then querystring args for 'action' and optionally 'id' (we don't use "pretty urls"
 *  because we cannot assume an infinite number of urlSegments in a PW site).
 *
 * In your module file, each `executeSomeThing()` method corresponds to 1 controller.
 *  Within the `executeSomeThing` method, call `ModuleController::execute($this, 'some_thing')`
 *  which will load the `some_thing.php` file in the module's `controllers` directory,
 *  instantiate a class named `SomeThingController`, and call a method having the name
 *  of the `action` querystring arg (or `index()` if no `action` exists in the querystring).
 *
 * Within the controller's action methods, you can call `$this->set('name', $value)`
 *  to pass data to the template. All of the template files for a controller should be
 *  in a corresponding subdirectory of the module's `views` directory (e.g. `/views/some_thing/`).
 *  Your action should then `return $this->render('template')`.
 * If you don't want to or need to render a template file, you can just return a string
 *  which will be outputted directly (in which case the `$this->set()` values are ignored).
 *
 * In addition to that primary function of routing to an action and rendering a template,
 * there are a few more helpful features you can use within your controllers:
 *  ~ Controllers can have a `before_action` method which gets called before all actions.
 *    This is a good place to put logic that is common to all controller actions.
 *    The `before_action` method is passed 1 arg: the action name, which is useful
 *    if you want to run some code for "every action except XYZ".
 *  ~ Many common 'wire' objects are available in controllers via `$this->`
 *    (e.g. `$this->session`, `$this->input`, etc -- see $preload_wire_objects below for full list).
 *  ~ Some helper functions are also available to your contoller methods:
 *    `$this->setHeadline('Your Headline')`
 *    `$this->url($controller, $action, $id_or_args)` [$id_or_args can be an integer which will be assigned to the 'id' arg, or an array of arbitrary key:value pairs]
 *    `$this->redirect($controller, $action, $id_or_args)`
 *    `$this->addBreadcrumb('Item Label', $controller, $action, $id_or_args)`
 *  ~ And some shortcut helper functions are available to templates:
 *    `$h($string)`: shortcut for `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`
 *    `$button($label, $href = null)`: shortcut for 'InputfieldButton' get+set+render
 *    `$url($controller, $action, $id_or_args)`: same as controller helper function above
 *    (Note that these template helper functions start with a `$` because they are
 *    anonymous functions passed as variables to the templates).
 */

class ModuleController {
	const relativePathToControllersDir = '../controllers';
	
	private $controller_handle = '';
	private $module_class_name = '';
	private $vars = array();

	//mames of 'wire' objects to store for easy access from controllers (e.g. $this->input, $this->session, etc.)
	private $preload_wire_objects = array(
		'session',
		'sanitizer',
		'fields',
		'templates',
		'pages',
		'page',
		'modules',
		'permissions',
		'roles',
		'users',
		'user',
		'input',
		'config',
	);

	public static function execute(&$module, $controller_handle) {
		require __DIR__ . '/' . trim(self::relativePathToControllersDir, '/') . '/' . $controller_handle . '.php';
		
		$controller_class = str_replace(' ', '', ucwords(str_replace('_', ' ', $controller_handle))) . 'Controller';
		$controller = new $controller_class($module, $controller_handle);

		$action = empty($module->input->get->action) ? 'index' : $module->input->get->action;

		if (self::isActionForbidden($action) || !is_callable(array($controller, $action))) {
			throw new Wire404Exception('Page not found');
		}

		if (is_callable(array($controller, 'before_action'))) {
			$controller->before_action($action);
		}

		return $controller->$action();
	}

	private static function isActionForbidden($action) {
		//this list contains public methods that should not be considered valid "actions"
		$forbidden_actions = array(
			'__construct',
			'before_action',
		);
		return in_array($action, $forbidden_actions);
	}

	final public function __construct($module, $controller_handle) {
		$this->controller_handle = $controller_handle;
		$this->module_class_name = get_class($module);
		foreach ($this->preload_wire_objects as $name) {
			$this->$name = $module->$name;
		}
		
		if ($controller_handle != 'main') {
			$module_info = $module->modules->getModuleInfo($this->module_class_name); //yeesh
			$this->addBreadcrumb($module_info['title']);
		}
	}

	final protected function set($name, $value) {
		$this->vars[$name] = $value;
	}

	final protected function setArray($arr) {
		foreach ($arr as $name => $value) {
			$this->set($name, $value);
		}
	}

	final protected function url($controller = null, $action = null, $id_or_args = null) {
		$url = $this->page->url;
		if (!empty($controller)) {
			$url .= $controller;
			if (!empty($action)) {
				$url .= "?action={$action}";
				if (!empty($id_or_args)) {
					$args = is_array($id_or_args) ? $id_or_args : array('id' => $id_or_args);
					foreach ($args as $key => $val) {
						$url .= '&' . $key . '=' . urlencode($val);
					}
				}
			}
		}
		return $url;
	}

	final protected function setHeadline($headline) {
		Wire::setFuel('processHeadline', $headline);
	}

	final protected function redirect($controller = null, $action = null, $id_or_args = null) {
		$url = $this->url($controller, $action, $id_or_args);
		$this->session->redirect($url);
	}

	final protected function addBreadcrumb($label, $controller = null, $action = null, $id_or_args = null) {
		$url = $this->url($controller, $action, $id_or_args);
		wire('breadcrumbs')->add(new Breadcrumb($url, $label));
	}

	//$template_handle is filename (without trailing .php) within the directory having the name
	// of this controller_handle within the module's /views/ directory.
	// For example, if the controller handle is "some_thing" and the given template_handle is "edit",
	// then the template file would be `/site/modules/TheModuleName/views/some_thing/edit`.
	final protected function render($template_handle) {
		$template_file_name = $template_handle . '.' . $this->config->templateExtension;
		$template_file_path = $this->config->paths->siteModules . "{$this->module_class_name}/views/{$this->controller_handle}/{$template_file_name}";
		$template_file = new TemplateFile($template_file_path);
		$template_file->setArray($this->vars);
		
		//helper functions...
		$template_file->set('h', function($s) {
			return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
		});

		$that = $this; //php doesn't allow use($this), so put it into a differently-named var

		$template_file->set('button', function($label, $icon = '', $controller = null, $action = null, $id_or_args = null) use ($that) {
			$button = $that->modules->get('InputfieldButton');
			$button->href = $that->url($controller, $action, $id_or_args);
			$button->value = $label;
			$button->icon = $icon;
			return $button->render();
		});
		
		$template_file->set('url', function($controller = null, $action = null, $id_or_args = null) use ($that) {
			return $that->url($controller, $action, $id_or_args);
		});
		
		return $template_file->render();
	}

}
