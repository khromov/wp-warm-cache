<?php	
	/**
	 * OOP version of php-microtemplate
	 * 
	 * Version 2
	 * - Added support for nested templates from any template through $template->t();
	 * 
	 * For more information see:
	 * http://khromov.wordpress.com/2012/08/09/micro-templates-for-rapid-web-design-prototyping-and-development-in-php/
	 **/
	class MicroTemplate_v2
	{
		private $prefix;
		private $suppress_errors;
		
		function __construct($prefix='templates/', $suppress_errors = true)
		{
			$this->prefix = $prefix;
			$this->suppress_errors = $suppress_errors;
			
			if(!$this->short_open_tag_enabled() && !$this->suppress_errors)
				throw new Exception('PHP short tags are disabled, please set the short_open_tag directive to "On" in your php.ini');
		}
		
		/**
		 * Main templating function
		 **/
		function template($template, $v = array(), $prefix = null)
		{
			if(is_null($prefix))
				$prefix = $this->prefix;
				
			return $this->build($template, $v, $this->prefix);
		}
		
		function build($template_name, $_v, $prefix)
		{
			//FIXME: Non-array $v can't do nested templates
						
			/** Attach template class to array or object **/
			/*
			if(is_array($v))
				$v['_template'] = &$this;
			if(is_object($v))
				$v->_template = &$this; //TODO: Check
			*/
			
			/** Set variables from $v array as actual variables. **/
			//if(!$this->is_associative_array($v))
			
			/**
			 * http://davidwalsh.name/convert-key-value-arrays-standard-variables-php
			 * 
			 * TODO: Check for edge cases. what happens if you enter a string? Or other weird stuff.
			 */
			foreach($_v as $key => $value)
				${$key} = $value; //$$key = $value;
			
			/** 
			 * Attach template class as variable $t - allows for OOP-fashion nested templating 
			 * 
			 * NOTE: This overrides any 'template' array key that was sent in $_v. 
			 **/
			
			$template = &$this;
			
			ob_start();

			if(file_exists($prefix.$template_name.'.php'))
			{
				if(($this->short_open_tag_enabled() === false))
				{
					//Short tags not enabled let's do some magic. Taken from CodeIgniter core.
					echo eval('?>'.preg_replace("/;*\s*\?>/", "; ?>", str_replace('<?=', '<?php echo ', file_get_contents($prefix.$template.'.php'))));
				}
				else
				{
					include($prefix.$template_name.'.php');
				}
			}
			else
			{
				if(!$this->suppress_errors)
					throw new Exception('Template file '. $prefix . $template_name .'.php does not exist.');	
				
				//If suppress_errors = true, don't do anything
			}		

			return ob_get_clean();			
		}

		/**
		 * Checks if a template exists
		 */
		function template_exists($template_name, $prefix = null)
		{
			if(is_null($prefix))
				$prefix = $this->prefix;
			
			return file_exists($prefix.$template_name.'.php');
		}
		
		/** Shorthand functions **/
		
		/**
		 * Alternative shorthand for template()
		 **/
		function t($template, $v = array(), $prefix = null)
		{
			return $this->template($template, $v, $prefix);
		}
		
		/**
		 * Alternative function for template()
		 **/
		function show($template, $v = array(), $prefix = null)
		{
			return $this->template($template, $v, $prefix);
		}
		
		/**
		 * Alternative function for template()
		 **/
		function display($template, $v = array(), $prefix = null)
		{
			return $this->template($template, $v, $prefix);
		}
		
		/** Helper functions **/
		
		/**
		 * Checks if short_open_tag is enabled
		 */
		function short_open_tag_enabled()
		{
			return (bool)@ini_get('short_open_tag');
		}
		
		/**
		 * http://stackoverflow.com/questions/173400/php-arrays-a-good-way-to-check-if-an-array-is-associative-or-numeric/4254008#4254008
		 */
		function is_associative_array($array)
		{
			return (bool)count(array_filter(array_keys($array), 'is_string'));
		}
	}
	
	/**
	 * Static shorthand version of the above class.
	 * Less flexible but easier to type:
	 * MT::t('template-name');
	 **/
	class MT_v2
	{
		private $MicroTemplateInstance;
		
		function t($template, $v, $prefix = 'templates/', $suppress_errors = true, $rewrite_short_tags = true)
		{
			$t = new MicroTemplate_v2($prefix, $suppress_errors, $rewrite_short_tags);
			return $t->template($template, $v, $prefix);			
		}	
	}