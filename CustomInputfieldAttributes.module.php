<?php namespace ProcessWire;

/**
 * Custom Inputfield Attributes for ProcessWire
 * 
 * Add custom attributes to Inputfields in ProcessWire, FormBuilder,
 * or other Inputfield forms. 
 * 
 * Copyright (C) 2023 Ryan Cramer Design, LLC
 * License: MIT
 * 
 * @property string[] $configRoles
 * @property string[] $allowTypes
 * @property string $appendAttrs
 * 
 * @method bool allowAttribute(Inputfield $inputfield, $attrName, $attrValue, $wrap)
 * 
 */
class CustomInputfieldAttributes extends WireData implements Module, ConfigurableModule {
	
	public static function getModuleInfo() {
		return array(
			'title' => 'Custom Inputfield Attributes',
			'summary' => 'Add custom attributes to Inputfields in ProcessWire, FormBuilder, etc.', 
			'version' => 1,
			'author' => 'Ryan Cramer',
			'autoload' => true, 
			'singular' => true, 
			'icon' => 'rebel',
			'requires' => 'ProcessWire>=3.0.210',
		);
	}
	
	const configName = 'customInputAttrs';
	const wrapPrefix = '^';

	/**
	 * Attribute names we do not allow setting (must be lowercase)
	 * 
	 * @var string[] 
	 * 
	 */
	protected $badNames = [ 'id', 'name', 'on*' ];

	/**
	 * Attribute values we do not allow setting (must be lowercase)
	 *
	 * @var string[]
	 *
	 */
	protected $badValues = [ 'javascript*' ];

	/**
	 * Attributes added at runtime
	 * 
	 * @var array 
	 * 
	 */
	protected $runtimeAttrs = [
		// 'inputfield_name' = [ 'attr_name' => 'attr_value ], 
	];

	/**
	 * Cache for names of attributes that use append mode
	 * 
	 * @var array|null
	 * 
	 */
	protected $appendAttrsCache = null;
	
	/**
	 * Construct
	 * 
	 */
	public function __construct() {
		parent::__construct();
		$this->set('configRoles', []); 
		$this->set('allowTypes', []);
		$this->set('appendAttrs', 'class style'); 
	}

	/**
	 * API ready
	 * 
	 */
	public function ready() {
		$this->addConfigHook();
		$this->addHookBefore('InputfieldWrapper::renderInputfield', $this, 'hookRender');
	}

	/**
	 * Allow given Inputfield type to use custom attributes?
	 * 
	 * @param Inputfield $inputfield
	 * @return bool
	 * 
	 */
	public function allowType(Inputfield $inputfield) {
		$allowTypes = $this->allowTypes;
		return empty($allowTypes) || in_array($inputfield->className(), $allowTypes); 
	}

	/**
	 * Add hook for configuring custom attributes when allowed
	 * 
	 */
	protected function addConfigHook() {
		
		$user = $this->wire()->user;
		$configRoles = $this->configRoles;
		$allow = true;
		
		if(!empty($configRoles)) {
			$roles = $this->wire()->roles;
			$allow = false;
			foreach($configRoles as $roleId) {
				$role = $roles->get($roleId);
				if($role && $user->hasRole($role)) $allow = true;
				if($allow) break;
			}
		}
		
		if($allow) {
			$this->addHookAfter('Inputfield::getConfigInputfields', $this, 'hookConfig');
		}
	}

	/**
	 * Get textarea value for customInputAttrs
	 * 
	 * @param Inputfield $inputfield
	 * @return string
	 * 
	 */
	protected function getConfigValue(Inputfield $inputfield) {
		
		if($inputfield->hasField) {
			// when editing a PW field, pull value from Field object
			// since Fields class won't know to populate it to Inputfield
			$value = (string) $inputfield->hasField->get(self::configName);
		} else {
			$value = (string) $inputfield->get(self::configName);
		}
	
		return $value;
	}
	
	/**
	 * Hook after Inputfield::getConfigInputfields
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookConfig(HookEvent $event) {
		
		$inputfield = $event->object; /** @var Inputfield $inputfield */
		if(!$this->allowType($inputfield)) return;
		
		$fs = $event->return; /** @var InputfieldWrapper $fs */
		$f = $fs->InputfieldTextarea;
		$f->attr('name', self::configName);
		$f->label = $this->_('Add custom attributes');
		$f->icon = 'rebel';
		$f->description = 
			$this->_('Enter one per line of `name=value` for attribute name and value you want to add.') . ' ' . 
			$this->_('Attributes are added to the `<input>` element.') . ' ' .
			$this->_('To add to the wrapping `.Inputfield` element, use `^name=value` instead.');
		$f->collapsed = Inputfield::collapsedBlank;
		$f->val((string) $this->getConfigValue($inputfield));
		$fs->add($f);
	}

	/**
	 * Hook before InputfieldWrapper::renderInputfield
	 * 
	 * @param HookEvent $event
	 * 
	 */
	public function hookRender(HookEvent $event) {
		
		/** @var Inputfield $inputfield */
		$inputfield = $event->arguments(0);
		if(!$this->allowType($inputfield)) return;
	
		$name = $inputfield->attr('name');
		$value = $this->getConfigValue($inputfield);
		
		if(!empty($value)) {
			foreach(explode("\n", $value) as $line) {
				if(strpos($line, '=') === false) continue;
				list($attrName, $attrValue) = explode('=', $line, 2);
				$this->applyAttribute($inputfield, $attrName, $attrValue);
			}
		}

		if(!empty($this->runtimeAttrs[$name])) {
			foreach($this->runtimeAttrs[$name] as $attrName => $attrValue) {
				$this->applyAttribute($inputfield, $attrName, $attrValue, false);
			}
		}
	}

	/**
	 * Allow given attribute name and value?
	 * 
	 * @param Inputfield $inputfield
	 * @param string $attrName
	 * @param string $attrValue
	 * @param bool $wrap Attribute for wrapping .Inputfield element?
	 * @return bool
	 * 
	 */
	protected function ___allowAttribute(Inputfield $inputfield, $attrName, $attrValue, $wrap) {
		if(empty($attrName)) return false;
		$allow = true;
		$reason = '';
		if($this->valueMatches($attrName, $this->badNames)) {
			$allow = false;
			$reason = "Attribute name '$attrName' is disallowed";
		} else if($this->valueMatches($attrValue, $this->badValues)) {
			$allow = false;
			$reason = "Attribute value '$attrValue' is disallowed";
		} else if($wrap && $inputfield) {
			// these argments are present only if hooks need them
		} 
		if($reason) {
			$info = self::getModuleInfo();
			$inputfield->detail = trim("$inputfield->detail\n$info[title] Warning: $reason");
		}
		return $allow;
	}

	/**
	 * Does given value match any of the given patterns?
	 * 
	 * @param string $value
	 * @param array $patterns Patterns of 'text' (exact) or 'text*' (wildcard) or /test/ (regex)
	 * @return bool
	 * 
	 */
	protected function valueMatches($value, array $patterns) {
		$value = trim(strtolower($value));
		$matches = false;
		foreach($patterns as $pattern) {
			$pattern = strtolower(trim($pattern));
			if(strpos($pattern, '/') === 0) {
				// regex match i.e. /^on*+/
				$matches = preg_match($pattern, $value);
			} else if(strpos($pattern, '*')) {
				// trailing wildcard i.e. 'on*' to match onclick, onfocus, on[anything]
				$pattern = rtrim($pattern, '*');
				$matches = strpos($value, $pattern) === 0;
			} else { 
				// exact match
				$matches = $value === $pattern;
			}
			if($matches) break;
		}
		return $matches;
	}

	/**
	 * Add attribute to Inputfield
	 * 
	 * @param Inputfield $inputfield
	 * @param string $attrName
	 * @param string $attrValue
	 * @param bool $validate Validate attrName and attrValue against built-in roles? (default=true)
	 * @return bool
	 * 
	 */
	protected function applyAttribute(Inputfield $inputfield, $attrName, $attrValue, $validate = true) {
		
		$wrap = false;
		
		if(strpos($attrName, self::wrapPrefix) === 0) {
			$wrap = true;
			list(,$attrName) = explode(self::wrapPrefix, $attrName, 2);
		}

		$attrName = $this->wire()->sanitizer->attrName(trim($attrName));
		if(empty($attrName)) return false;
	
		// two trim calls so that leading/trailing space is allowed if quoted
		$attrValue = trim($attrValue);
		$attrValue = trim($attrValue, '"'); 
		
		if($wrap) {
			$existingValue = (string) $inputfield->wrapAttr($attrName);
		} else {
			$existingValue = (string) $inputfield->attr($attrName);
		}

		if($validate && !$this->allowAttribute($inputfield, $attrName, $attrValue, $wrap)) {
			return false;
		}

		if(strlen($existingValue) && $this->isAppendAttr($attrName)) {
			// append existing value
			if($attrName === 'style') {
				$attrValue = rtrim($existingValue, '; ') . "; $attrValue";
			} else {
				$attrValue = trim($existingValue) . " $attrValue";
			}
		}

		if($wrap) {
			$inputfield->wrapAttr($attrName, $attrValue);
		} else {
			$inputfield->attr($attrName, $attrValue);
		}
		
		return true;
	}

	/**
	 * Is given attribute name on that should use append mode? 
	 * 
	 * @param string $attrName
	 * @return bool
	 * 
	 */
	protected function isAppendAttr($attrName) {
		if($this->appendAttrsCache === null) {
			foreach(explode(' ', strtolower($this->appendAttrs)) as $name) {
				if(strlen($name)) $this->appendAttrsCache[$name] = $name;
			}
		}
		return isset($this->appendAttrsCache[strtolower($attrName)]); 
	}

	/**
	 * Add an attribute at runtime
	 * 
	 * Note that attributes added with this method are NOT validated. 
	 * Meaning they can be used to add Javascript attributes. 
	 * 
	 * ~~~~~
	 * // example for /site/ready.php
	 * $module = $modules->get('CustomInputfieldAttributes');
	 * $module->addAttribute('headline', 'hello', 'world'); // add to input element
	 * $module->addAttribute('headline', '^hello', 'world'); // add to wrapping .Inputfield element
	 * ~~~~~
	 * 
	 * @param string $name Inputfield name
	 * @param string $attrName Attribute name (optionally prefix with `^` to add it to the wrapping element)
	 * @param string $attrValue Attribute value
	 * 
	 */
	public function addAttribute($name, $attrName, $attrValue) {
		if(!isset($this->runtimeAttrs[$name])) $this->runtimeAttrs[$name] = [];
		$this->runtimeAttrs[$name][$attrName] = $attrValue;
	}

	/**
	 * Module config
	 * 
	 * @param InputfieldWrapper $inputfields
	 *
	 */
	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		
		$f = $inputfields->InputfieldCheckboxes; 
		$f->attr('name', 'configRoles'); 
		$f->label = $this->_('What roles are allowed to configure custom attributes?'); 
		$f->description = $this->_('If no roles are selected then any role that can configure form fields is allowed.');
		$f->notes = $this->_('You may want to be selective as custom attributes can have potential to break output in some cases.'); 
		$permission = $this->wire()->permissions->get('page-edit');
		foreach($this->wire()->roles as $role) {
			if(!$role->hasPermission($permission)) continue;
			$f->addOption($role->id, $role->name);
		}
		$f->val($this->configRoles);
		$inputfields->add($f);

		$f = $inputfields->InputfieldCheckboxes;
		$f->attr('name', 'allowTypes');
		$f->label = $this->_('What Inputfield types are allowed to have custom attributes?') ;
		$f->description = $this->_('If no types are selected then the option will be available for any type.');
		$f->notes = $this->_('Not all types may support custom attributes. Always test to confirm support.');
		foreach($this->wire()->modules->findByPrefix('Inputfield') as $moduleName) {
			$f->addOption($moduleName, str_replace('Inputfield', '', $moduleName)); 
		}
		$f->optionColumns = 3;
		$f->val($this->allowTypes);
		$inputfields->add($f);
		
		$f = $inputfields->InputfieldText; 
		$f->attr('name', 'appendAttrs'); 
		$f->label = $this->_('Attribute names that should append existing attributes (when present)'); 
		$f->description = $this->_('Enter a space-separated list of attribute names.'); 
		$f->val($this->appendAttrs); 
		$inputfields->add($f);
	}

}