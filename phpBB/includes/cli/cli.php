<?php

class phpbb_cli
{

	private $tasks = array();
	private $args = array();

	public function __construct($task, $args)
	{
		$task = $this->sanitize_task($task);
		$this->tasks[] = array($task, $this->find_task($task));
		$this->args = $args;
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
		
		if (class_exists($class)) return new $class();

		return false;
	}

	public function run()
	{
		$complete = array();

		while (!empty($this->tasks))
		{
			$task_s = array_pop($this->tasks);

			if(!$task_s[1])
			{
				return $this->task_not_found($task_s[0]);
			}

			if(isset($complete[$task_s[0]]))
			{
				continue;
			}

			if (!empty($task_s[1]->dependencies))
			{
				$unsatisfied = array();
				foreach ($task_s[1]->dependencies as $dep)
				{
					if (!isset($complete[$dep]))
					{
						$unsatisfied[] = $dep;
					}
				}

				if(!empty($unsatisfied))
				{
					$this->tasks[] = $task_s;
					foreach($unsatisfied as $dep)
					{
						$this->tasks[] = array($dep, $this->find_task($dep));
					}
				}

				continue;
			}

			if($code = $task_s[1]->run())
			{
				return $this->display_errors($code, $task_s[1]->errors);
			}
			else
			{
				$complete[$task_s[0]] = $task_s[1];
			}
		}

		// Completed without errors

		return 0;
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
