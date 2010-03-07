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

	protected $run = false;

	protected $attr;

	public static function describe()
	{
		return 'Description of the task, preferably from the language system';
	}


	public static function construct($class, $attr_reader, $force_new = false)
	{
		static $tasks = array();

		if(!isset($tasks[$class]) || $force_new)
		{
			$tasks[$class] = new $class($attr_reader);
		}

		return $tasks[$class];
	}

	protected function __construct($attr_reader)
	{
		$this->attr = $attr_reader;
	}
	
	public function find_task($task)
	{
		global $phpbb_root_path, $phpEx;

		$task_parts = explode(':', $task);
		$class = 'phpbb_cli_task_' . str_replace(':', '_', $task);
		
		while(sizeof($task_parts) > 0)
		{
			if(class_exists($class)) break;

			$file = $phpbb_root_path . 'includes/cli/cli_task_' . implode('_', $task_parts) . '.' . $phpEx;

			if (file_exists($file))
			{
				include($file);
			}
			
			array_pop($task_parts);
		}
		
		if (class_exists($class)) return phpbb_cli_task::construct($task, $this->attr);

		return $this->task_not_found($task);
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
	
	public function run($force_run = false)
	{

		if($this->run && !$force_run)
		{
			return;
		}

		$this->initialize();
		$this->run_dependencies();

		$task = str_replace('_', ':', substr(get_class($this), strlen('phpbb_cli_task_')));
		echo("Executing $task\n");
		$this->errors = array();
		$code = $this->_run();
		
		if(!$code && !empty($this->errors))
		{
			$code = 1;
		}

		return $code;
	}

	protected function run_dependencies()
	{
		foreach($this->dependencies as $dep)
		{
			$task = $this->find_task($dep);
			
			if(!$task)
			{
				return $this->task_not_found($task);
			}

			$task->run();
		}
	}
	
	public function _run()
	{
		// Implemented by task
	}
	
}

interface phpbb_attr_reader
{
	public function get($var, $default, $lang = false);
}