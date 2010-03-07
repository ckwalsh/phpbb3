<?php

if(!defined('IN_PHPBB')) exit;

require_once($phpbb_root_path . 'includes/cli/cli_task.' . $phpEx);

class phpbb_cli_task_foo extends phpbb_cli_task
{
	
	public static function describe()
	{
		return 'Foo';
	}
	
	public function initialize()
	{
		echo("Initializing Foo\n");
	}
	
	public function _run()
	{
		echo("Hello, World!\n");
		
		return 0;
	}
	
}