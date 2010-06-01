<?php
/**
*
* @package install
* @version $Id$
* @copyright (c) 2006 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Determine if we are able to load a specified PHP module and do so if possible
*/
function can_load_dll($dll)
{
	// SQLite2 is a tricky thing, from 5.0.0 it requires PDO; if PDO is not loaded we must state that SQLite is unavailable
	// as the installer doesn't understand that the extension has a prerequisite.
	//
	// On top of this sometimes the SQLite extension is compiled for a different version of PDO
	// by some Linux distributions which causes phpBB to bomb out with a blank page.
	//
	// Net result we'll disable automatic inclusion of SQLite support
	//
	// See: r9618 and #56105
	if ($dll == 'sqlite')
	{
		return false;
	}
	return ((@ini_get('enable_dl') || strtolower(@ini_get('enable_dl')) == 'on') && (!@ini_get('safe_mode') || strtolower(@ini_get('safe_mode')) == 'off') && function_exists('dl') && @dl($dll . '.' . PHP_SHLIB_SUFFIX)) ? true : false;
}

/**
* Returns an array of available DBMS with some data, if a DBMS is specified it will only
* return data for that DBMS and will load its extension if necessary.
*/
function get_available_dbms($dbms = false, $return_unavailable = false, $only_20x_options = false)
{
	global $lang;
	$available_dbms = array(
		'firebird'	=> array(
			'LABEL'			=> 'FireBird',
			'SCHEMA'		=> 'firebird',
			'MODULE'		=> 'interbase',
			'DELIM'			=> ';;',
			'COMMENTS'		=> 'remove_remarks',
			'DRIVER'		=> 'firebird',
			'AVAILABLE'		=> true,
			'2.0.x'			=> false,
		),
		'mysqli'	=> array(
			'LABEL'			=> 'MySQL with MySQLi Extension',
			'SCHEMA'		=> 'mysql_41',
			'MODULE'		=> 'mysqli',
			'DELIM'			=> ';',
			'COMMENTS'		=> 'remove_remarks',
			'DRIVER'		=> 'mysqli',
			'AVAILABLE'		=> true,
			'2.0.x'			=> true,
		),
		'mysql'		=> array(
			'LABEL'			=> 'MySQL',
			'SCHEMA'		=> 'mysql',
			'MODULE'		=> 'mysql',
			'DELIM'			=> ';',
			'COMMENTS'		=> 'remove_remarks',
			'DRIVER'		=> 'mysql',
			'AVAILABLE'		=> true,
			'2.0.x'			=> true,
		),
		'mssql'		=> array(
			'LABEL'			=> 'MS SQL Server 2000+',
			'SCHEMA'		=> 'mssql',
			'MODULE'		=> 'mssql',
			'DELIM'			=> 'GO',
			'COMMENTS'		=> 'remove_comments',
			'DRIVER'		=> 'mssql',
			'AVAILABLE'		=> true,
			'2.0.x'			=> true,
		),
		'mssql_odbc'=>	array(
			'LABEL'			=> 'MS SQL Server [ ODBC ]',
			'SCHEMA'		=> 'mssql',
			'MODULE'		=> 'odbc',
			'DELIM'			=> 'GO',
			'COMMENTS'		=> 'remove_comments',
			'DRIVER'		=> 'mssql_odbc',
			'AVAILABLE'		=> true,
			'2.0.x'			=> true,
		),
		'mssqlnative'		=> array(
			'LABEL'			=> 'MS SQL Server 2005+ [ Native ]',
			'SCHEMA'		=> 'mssql',
			'MODULE'		=> 'sqlsrv',
			'DELIM'			=> 'GO',
			'COMMENTS'		=> 'remove_comments',
			'DRIVER'		=> 'mssqlnative',
			'AVAILABLE'		=> true,
			'2.0.x'			=> false,
		),			
		'oracle'	=>	array(
			'LABEL'			=> 'Oracle',
			'SCHEMA'		=> 'oracle',
			'MODULE'		=> 'oci8',
			'DELIM'			=> '/',
			'COMMENTS'		=> 'remove_comments',
			'DRIVER'		=> 'oracle',
			'AVAILABLE'		=> true,
			'2.0.x'			=> false,
		),
		'postgres' => array(
			'LABEL'			=> 'PostgreSQL 7.x/8.x',
			'SCHEMA'		=> 'postgres',
			'MODULE'		=> 'pgsql',
			'DELIM'			=> ';',
			'COMMENTS'		=> 'remove_comments',
			'DRIVER'		=> 'postgres',
			'AVAILABLE'		=> true,
			'2.0.x'			=> true,
		),
		'sqlite'		=> array(
			'LABEL'			=> 'SQLite',
			'SCHEMA'		=> 'sqlite',
			'MODULE'		=> 'sqlite',
			'DELIM'			=> ';',
			'COMMENTS'		=> 'remove_remarks',
			'DRIVER'		=> 'sqlite',
			'AVAILABLE'		=> true,
			'2.0.x'			=> false,
		),
	);

	if ($dbms)
	{
		if (isset($available_dbms[$dbms]))
		{
			$available_dbms = array($dbms => $available_dbms[$dbms]);
		}
		else
		{
			return array();
		}
	}

	// now perform some checks whether they are really available
	foreach ($available_dbms as $db_name => $db_ary)
	{
		if ($only_20x_options && !$db_ary['2.0.x'])
		{
			if ($return_unavailable)
			{
				$available_dbms[$db_name]['AVAILABLE'] = false;
			}
			else
			{
				unset($available_dbms[$db_name]);
			}
			continue;
		}

		$dll = $db_ary['MODULE'];

		if (!@extension_loaded($dll))
		{
			if (!can_load_dll($dll))
			{
				if ($return_unavailable)
				{
					$available_dbms[$db_name]['AVAILABLE'] = false;
				}
				else
				{
					unset($available_dbms[$db_name]);
				}
				continue;
			}
		}
		$any_db_support = true;
	}

	if ($return_unavailable)
	{
		$available_dbms['ANY_DB_SUPPORT'] = $any_db_support;
	}
	return $available_dbms;
}

/**
* Generate the drop down of available database options
*/
function dbms_select($default = '', $only_20x_options = false)
{
	global $lang;

	$available_dbms = get_available_dbms(false, false, $only_20x_options);
	$dbms_options = '';
	foreach ($available_dbms as $dbms_name => $details)
	{
		$selected = ($dbms_name == $default) ? ' selected="selected"' : '';
		$dbms_options .= '<option value="' . $dbms_name . '"' . $selected .'>' . $lang['DLL_' . strtoupper($dbms_name)] . '</option>';
	}
	return $dbms_options;
}

/**
* Get tables of a database
*/
function get_tables($db)
{
	switch ($db->sql_layer)
	{
		case 'mysql':
		case 'mysql4':
		case 'mysqli':
			$sql = 'SHOW TABLES';
		break;

		case 'sqlite':
			$sql = 'SELECT name
				FROM sqlite_master
				WHERE type = "table"';
		break;

		case 'mssql':
		case 'mssql_odbc':
		case 'mssqlnative':
			$sql = "SELECT name
				FROM sysobjects
				WHERE type='U'";
		break;

		case 'postgres':
			$sql = 'SELECT relname
				FROM pg_stat_user_tables';
		break;

		case 'firebird':
			$sql = 'SELECT rdb$relation_name
				FROM rdb$relations
				WHERE rdb$view_source is null
					AND rdb$system_flag = 0';
		break;

		case 'oracle':
			$sql = 'SELECT table_name
				FROM USER_TABLES';
		break;
	}

	$result = $db->sql_query($sql);

	$tables = array();

	while ($row = $db->sql_fetchrow($result))
	{
		$tables[] = current($row);
	}

	$db->sql_freeresult($result);

	return $tables;
}

/**
* Used to test whether we are able to connect to the database the user has specified
* and identify any problems (eg there are already tables with the names we want to use
* @param	array	$dbms should be of the format of an element of the array returned by {@link get_available_dbms get_available_dbms()}
*					necessary extensions should be loaded already
*/
function connect_check_db($error_connect, &$error, $dbms_details, $table_prefix, $dbhost, $dbuser, $dbpasswd, $dbname, $dbport, $prefix_may_exist = false, $load_dbal = true, $unicode_check = true)
{
	global $phpbb_root_path, $phpEx, $config, $lang;

	$dbms = $dbms_details['DRIVER'];

	if ($load_dbal)
	{
		// Include the DB layer
		include($phpbb_root_path . 'includes/db/' . $dbms . '.' . $phpEx);
	}

	// Instantiate it and set return on error true
	$sql_db = 'dbal_' . $dbms;
	$db = new $sql_db();
	$db->sql_return_on_error(true);

	// Check that we actually have a database name before going any further.....
	if ($dbms_details['DRIVER'] != 'sqlite' && $dbms_details['DRIVER'] != 'oracle' && $dbname === '')
	{
		$error[] = $lang['INST_ERR_DB_NO_NAME'];
		return false;
	}

	// Make sure we don't have a daft user who thinks having the SQLite database in the forum directory is a good idea
	if ($dbms_details['DRIVER'] == 'sqlite' && stripos(phpbb_realpath($dbhost), phpbb_realpath('../')) === 0)
	{
		$error[] = $lang['INST_ERR_DB_FORUM_PATH'];
		return false;
	}

	// Check the prefix length to ensure that index names are not too long and does not contain invalid characters
	switch ($dbms_details['DRIVER'])
	{
		case 'mysql':
		case 'mysqli':
			if (strspn($table_prefix, '-./\\') !== 0)
			{
				$error[] = $lang['INST_ERR_PREFIX_INVALID'];
				return false;
			}

		// no break;

		case 'postgres':
			$prefix_length = 36;
		break;

		case 'mssql':
		case 'mssql_odbc':
		case 'mssqlnative':
			$prefix_length = 90;
		break;

		case 'sqlite':
			$prefix_length = 200;
		break;

		case 'firebird':
		case 'oracle':
			$prefix_length = 6;
		break;
	}

	if (strlen($table_prefix) > $prefix_length)
	{
		$error[] = sprintf($lang['INST_ERR_PREFIX_TOO_LONG'], $prefix_length);
		return false;
	}

	// Try and connect ...
	if (is_array($db->sql_connect($dbhost, $dbuser, $dbpasswd, $dbname, $dbport, false, true)))
	{
		$db_error = $db->sql_error();
		$error[] = $lang['INST_ERR_DB_CONNECT'] . '<br />' . (($db_error['message']) ? $db_error['message'] : $lang['INST_ERR_DB_NO_ERROR']);
	}
	else
	{
		// Likely matches for an existing phpBB installation
		if (!$prefix_may_exist)
		{
			$temp_prefix = strtolower($table_prefix);
			$table_ary = array($temp_prefix . 'attachments', $temp_prefix . 'config', $temp_prefix . 'sessions', $temp_prefix . 'topics', $temp_prefix . 'users');

			$tables = get_tables($db);
			$tables = array_map('strtolower', $tables);
			$table_intersect = array_intersect($tables, $table_ary);

			if (sizeof($table_intersect))
			{
				$error[] = $lang['INST_ERR_PREFIX'];
			}
		}

	}

	if ($error_connect && (!isset($error) || !sizeof($error)))
	{
		return true;
	}
	return false;
}

/**
* remove_remarks will strip the sql comment lines out of an uploaded sql file
*/
function remove_remarks(&$sql)
{
	$sql = preg_replace('/\n{2,}/', "\n", preg_replace('/^#.*$/m', "\n", $sql));
}

/**
* split_sql_file will split an uploaded sql file into single sql statements.
* Note: expects trim() to have already been run on $sql.
*/
function split_sql_file($sql, $delimiter)
{
	$sql = str_replace("\r" , '', $sql);
	$data = preg_split('/' . preg_quote($delimiter, '/') . '$/m', $sql);

	$data = array_map('trim', $data);

	// The empty case
	$end_data = end($data);

	if (empty($end_data))
	{
		unset($data[key($data)]);
	}

	return $data;
}

/**
* For replacing {L_*} strings with preg_replace_callback
*/
function adjust_language_keys_callback($matches)
{
	if (!empty($matches[1]))
	{
		global $lang, $db;

		return (!empty($lang[$matches[1]])) ? $db->sql_escape($lang[$matches[1]]) : $db->sql_escape($matches[1]);
	}
}

?>