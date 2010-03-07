<?php

abstract class phpbb_cli_task
{
	
	public $errors = array(
		array(
			'code' => 0,
			'msg' => ''
		)
	);

	private $properties = array();
	
	public $dependencies = array();

	public static function describe()
	{
		return 'Description of the task, preferably from the language system';
	}
	
	public function __construct()
	{
	}
	
	public function __set($var, $val)
	{
		// Set a property
		$this->properties[$var] = $val;
	}
	
	public function __get($var)
	{
		// Get a property
		if(isset($this->properties[$var]))
		{
			return $this->properties[$var];
		}
		else
		{
			return null;
		}
	}
	
	public function __unset($var)
	{
		// Get a property
		if(isset($this->properties[$var]))
		{
			unset($this->properties[$var]);
		}
	}
	
	public function initialize()
	{
		// Implemented by task
	}
	
	public function run()
	{
		$task = str_replace('_', ':', substr(get_class($this), strlen('phpbb_cli_task_')));
		echo("Executing $task\n");
		$this->errors = array();
		$this->initialize();
		$code = $this->_run();
		
		if(!$code && !empty($this->errors))
		{
			$code = 1;
		}

		return $code;
	}
	
	public function _run()
	{
		// Implemented by task
	}
	
}