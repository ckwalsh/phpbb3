#!/usr/bin/env php
<?php

if(!isset($argv))
{
	exit;
}

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1); 

require($phpbb_root_path . 'includes/cli/cli.' . $phpEx);

$args = $argv;
array_shift($args); // Off with ./phpbb.php
$task = array_shift($args);

$cli = new phpbb_cli($task, $args);

$cli->run();

exit;
