<?php

if (!defined('IN_PHPBB')) exit;

require_once($phpbb_root_path . 'includes/cli/cli_task.php');

class phpbb_cli
{

	private $task = null;
	private $args = array();

	private $attr_reader = null;

	public function __construct($task, $args)
	{
		$this->attr_reader = new phpbb_cli_attr_reader(array());
		
		$task = $this->sanitize_task($task);
		$this->task = $this->find_task($task);
		$this->args = $args;
	}

	public function run()
	{
		return $this->task->run();
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
		

		if (class_exists($class))
		{
			return phpbb_cli_task::construct($class, $this->attr_reader);
		}

		return $this->task_not_found($task);
	}
		
	public function task_not_found($task)
	{
		trigger_error("Task '$task' not found", E_USER_ERROR);
	}

	public function display_errors($code, $errors)
	{
		var_dump($errors);
		return $code;
	}

	public function show_tasks()
	{
		echo "Not Implemented";
	}

	private function sanitize_task($task)
	{
		return preg_replace('/[^a-z:]+/', '', strtolower($task));
	}
}

class phpbb_cli_attr_reader implements phpbb_attr_reader
{

	private $properties = array();

	public function __construct($properties)
	{
		$this->properties = $properties;
	}
	
	public function get($var, $default, $lang = false)
	{
		$ret = null;
		if(isset($this->properties[$var]))
		{
			$ret = $this->properties[$var];
		}
		else
		{
			// Get it from standard in
			$lang_s = ($lang === false) ? $var : $default;
			$lang_s .= "? ($default) ";
			echo($lang_s);
		
			$fp = fopen('php://stdin', 'r');
			$ret = substr(fgets($fp), 0, -1);
			fclose($fp);

			if(empty($ret))
			{
				$ret = $default;
			}
		}
		
		if(is_bool($default))
		{
			$ret = (bool) $defult;
		}
		else if(is_int($default))
		{
			$ret = (int) $ret;
		}
		else if (is_float($default))
		{
			$ret = (float) $ret;
		}
		else
		{
			$ret = (string) $ret;
		}

		return $ret;
	}
}	
