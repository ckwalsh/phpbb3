<?php
/**
*
* @package acp
* @version $Id$
* @copyright (c) 2005 phpBB Group
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
* @package acp
*/
class acp_database
{
	var $u_action;

	function main($id, $mode)
	{
		global $cache, $db, $user, $auth, $template, $table_prefix;
		global $config, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		$user->add_lang('acp/database');

		$this->tpl_name = 'acp_database';
		$this->page_title = 'ACP_DATABASE';

		$action	= request_var('action', '');
		$submit = (isset($_POST['submit'])) ? true : false;

		$template->assign_vars(array(
			'MODE'	=> $mode
		));

		switch ($mode)
		{
			case 'backup':

				$this->page_title = 'ACP_BACKUP';

				switch ($action)
				{
					case 'download':
						$type	= request_var('type', '');
						$table	= request_var('table', array(''));
						$format	= request_var('method', '');
						$where	= request_var('where', '');

						if (!sizeof($table))
						{
							trigger_error($user->lang['TABLE_SELECT_ERROR'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						$store = $download = $structure = $schema_data = false;

						if ($where == 'store_and_download' || $where == 'store')
						{
							$store = true;
						}

						if ($where == 'store_and_download' || $where == 'download')
						{
							$download = true;
						}

						if ($type == 'full' || $type == 'structure')
						{
							$structure = true;
						}

						if ($type == 'full' || $type == 'data')
						{
							$schema_data = true;
						}

						@set_time_limit(1200);
						@set_time_limit(0);

						$time = time();

						$filename = 'backup_' . $time . '_' . unique_id();
						switch ($db->sql_layer)
						{
							case 'mysqli':
							case 'mysql4':
							case 'mysql':
								$extractor = new mysql_extractor($download, $store, $format, $filename, $time);
							break;

						}

						$extractor->write_start($table_prefix);

						foreach ($table as $table_name)
						{
							// Get the table structure
							if ($structure)
							{
								$extractor->write_table($table_name);
							}

							// Data
							if ($schema_data)
							{
								$extractor->write_data($table_name);
							}
						}

						$extractor->write_end();

						add_log('admin', 'LOG_DB_BACKUP');

						if ($download == true)
						{
							exit;
						}

						trigger_error($user->lang['BACKUP_SUCCESS'] . adm_back_link($this->u_action));
					break;

					default:
						include($phpbb_root_path . 'includes/functions_install.' . $phpEx);
						$tables = get_tables($db);
						asort($tables);
						foreach ($tables as $table_name)
						{
							if (strlen($table_prefix) === 0 || stripos($table_name, $table_prefix) === 0)
							{
								$template->assign_block_vars('tables', array(
									'TABLE'	=> $table_name
								));
							}
						}
						unset($tables);

						$template->assign_vars(array(
							'U_ACTION'	=> $this->u_action . '&amp;action=download'
						));

						$available_methods = array('gzip' => 'zlib');

						foreach ($available_methods as $type => $module)
						{
							if (!@extension_loaded($module))
							{
								continue;
							}

							$template->assign_block_vars('methods', array(
								'TYPE'	=> $type
							));
						}

						$template->assign_block_vars('methods', array(
							'TYPE'	=> 'text'
						));
					break;
				}
			break;

			case 'restore':

				$this->page_title = 'ACP_RESTORE';

				switch ($action)
				{
					case 'submit':
						$delete = request_var('delete', '');
						$file = request_var('file', '');

						if (!preg_match('#^backup_\d{10,}_[a-z\d]{16}\.(sql(?:\.(?:gz))?)$#', $file, $matches))
						{
							trigger_error($user->lang['BACKUP_INVALID'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						$file_name = $phpbb_root_path . 'store/' . $matches[0];

						if (!file_exists($file_name) || !is_readable($file_name))
						{
							trigger_error($user->lang['BACKUP_INVALID'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						if ($delete)
						{
							if (confirm_box(true))
							{
								unlink($file_name);
								add_log('admin', 'LOG_DB_DELETE');
								trigger_error($user->lang['BACKUP_DELETE'] . adm_back_link($this->u_action));
							}
							else
							{
								confirm_box(false, $user->lang['DELETE_SELECTED_BACKUP'], build_hidden_fields(array('delete' => $delete, 'file' => $file)));
							}
						}
						else
						{
							$download = request_var('download', '');

							if ($download)
							{
								$name = $matches[0];

								switch ($matches[1])
								{
									case 'sql':
										$mimetype = 'text/x-sql';
									break;
									case 'sql.gz':
										$mimetype = 'application/x-gzip';
									break;
								}

								header('Pragma: no-cache');
								header("Content-Type: $mimetype; name=\"$name\"");
								header("Content-disposition: attachment; filename=$name");

								@set_time_limit(0);

								$fp = @fopen($file_name, 'rb');

								if ($fp !== false)
								{
									while (!feof($fp))
									{
										echo fread($fp, 8192);
									}
									fclose($fp);
								}

								flush();
								exit;
							}

							switch ($matches[1])
							{
								case 'sql':
									$fp = fopen($file_name, 'rb');
									$read = 'fread';
									$seek = 'fseek';
									$eof = 'feof';
									$close = 'fclose';
									$fgetd = 'fgetd';
								break;

								case 'sql.gz':
									$fp = gzopen($file_name, 'rb');
									$read = 'gzread';
									$seek = 'gzseek';
									$eof = 'gzeof';
									$close = 'gzclose';
									$fgetd = 'fgetd';
								break;
							}

							switch ($db->sql_layer)
							{
								case 'mysql':
								case 'mysql4':
								case 'mysqli':
								case 'sqlite':
									while (($sql = $fgetd($fp, ";\n", $read, $seek, $eof)) !== false)
									{
										$db->sql_query($sql);
									}
								break;

							}

							$close($fp);

							// Purge the cache due to updated data
							$cache->purge();

							add_log('admin', 'LOG_DB_RESTORE');
							trigger_error($user->lang['RESTORE_SUCCESS'] . adm_back_link($this->u_action));
							break;
						}

					default:
						$methods = array('sql');
						$available_methods = array('sql.gz' => 'zlib');

						foreach ($available_methods as $type => $module)
						{
							if (!@extension_loaded($module))
							{
								continue;
							}
							$methods[] = $type;
						}

						$dir = $phpbb_root_path . 'store/';
						$dh = @opendir($dir);

						$backup_files = array();

						if ($dh)
						{
							while (($file = readdir($dh)) !== false)
							{
								if (preg_match('#^backup_(\d{10,})_[a-z\d]{16}\.(sql(?:\.(?:gz))?)$#', $file, $matches))
								{
									if (in_array($matches[2], $methods))
									{
										$backup_files[(int) $matches[1]] = $file;
									}
								}
							}
							closedir($dh);
						}

						if (!empty($backup_files))
						{
							krsort($backup_files);

							foreach ($backup_files as $name => $file)
							{
								$template->assign_block_vars('files', array(
									'FILE'		=> $file,
									'NAME'		=> $user->format_date($name, 'd-m-Y H:i:s', true),
									'SUPPORTED'	=> true,
								));
							}
						}

						$template->assign_vars(array(
							'U_ACTION'	=> $this->u_action . '&amp;action=submit'
						));
					break;
				}
			break;
		}
	}
}

/**
* @package acp
*/
class base_extractor
{
	var $fh;
	var $fp;
	var $write;
	var $close;
	var $store;
	var $download;
	var $time;
	var $format;
	var $run_comp = false;

	function base_extractor($download = false, $store = false, $format, $filename, $time)
	{
		$this->download = $download;
		$this->store = $store;
		$this->time = $time;
		$this->format = $format;

		switch ($format)
		{
			case 'text':
				$ext = '.sql';
				$open = 'fopen';
				$this->write = 'fwrite';
				$this->close = 'fclose';
				$mimetype = 'text/x-sql';
			break;
			case 'gzip':
				$ext = '.sql.gz';
				$open = 'gzopen';
				$this->write = 'gzwrite';
				$this->close = 'gzclose';
				$mimetype = 'application/x-gzip';
			break;
		}

		if ($download == true)
		{
			$name = $filename . $ext;
			header('Pragma: no-cache');
			header("Content-Type: $mimetype; name=\"$name\"");
			header("Content-disposition: attachment; filename=$name");

			switch ($format)
			{
				case 'gzip':
					if ((isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) && strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') === false)
					{
						ob_start('ob_gzhandler');
					}
					else
					{
						$this->run_comp = true;
					}
				break;
			}
		}

		if ($store == true)
		{
			global $phpbb_root_path;
			$file = $phpbb_root_path . 'store/' . $filename . $ext;

			$this->fp = $open($file, 'w');

			if (!$this->fp)
			{
				trigger_error('FILE_WRITE_FAIL', E_USER_ERROR);
			}
		}
	}

	function write_end()
	{
		static $close;

		if ($this->store)
		{
			if ($close === null)
			{
				$close = $this->close;
			}
			$close($this->fp);
		}

	}

	function flush($data)
	{
		static $write;
		if ($this->store === true)
		{
			if ($write === null)
			{
				$write = $this->write;
			}
			$write($this->fp, $data);
		}

		if ($this->download === true)
		{
			if ($this->format === 'text' || ($this->format === 'gzip' && !$this->run_comp))
			{
				echo $data;
			}

			// we can write the gzip data as soon as we get it
			if ($this->format === 'gzip')
			{
				if ($this->run_comp)
				{
					echo gzencode($data);
				}
				else
				{
					ob_flush();
					flush();
				}
			}
		}
	}
}

/**
* @package acp
*/
class mysql_extractor extends base_extractor
{
	function write_start($table_prefix)
	{
		$sql_data = "#\n";
		$sql_data .= "# phpBB Backup Script\n";
		$sql_data .= "# Dump of tables for $table_prefix\n";
		$sql_data .= "# DATE : " . gmdate("d-m-Y H:i:s", $this->time) . " GMT\n";
		$sql_data .= "#\n";
		$this->flush($sql_data);
	}

	function write_table($table_name)
	{
		global $db;
		static $new_extract;

		if ($new_extract === null)
		{
			$new_extract = false;
		}

		if ($new_extract)
		{
			$this->new_write_table($table_name);
		}
		else
		{
			$this->old_write_table($table_name);
		}
	}

	function write_data($table_name)
	{
		global $db;
		$this->write_data_mysql($table_name);
	}

	function write_data_mysql($table_name)
	{
		global $db;
		$sql = "SELECT *
			FROM $table_name";
		$result = mysql_unbuffered_query($sql, $db->db_connect_id);

		if ($result != false)
		{
			$fields_cnt = mysql_num_fields($result);

			// Get field information
			$field = array();
			for ($i = 0; $i < $fields_cnt; $i++)
			{
				$field[] = mysql_fetch_field($result, $i);
			}
			$field_set = array();

			for ($j = 0; $j < $fields_cnt; $j++)
			{
				$field_set[] = $field[$j]->name;
			}

			$search			= array("\\", "'", "\x00", "\x0a", "\x0d", "\x1a", '"');
			$replace		= array("\\\\", "\\'", '\0', '\n', '\r', '\Z', '\\"');
			$fields			= implode(', ', $field_set);
			$sql_data		= 'INSERT INTO ' . $table_name . ' (' . $fields . ') VALUES ';
			$first_set		= true;
			$query_len		= 0;
			$max_len		= get_usable_memory();

			while ($row = mysql_fetch_row($result))
			{
				$values = array();
				if ($first_set)
				{
					$query = $sql_data . '(';
				}
				else
				{
					$query  .= ',(';
				}

				for ($j = 0; $j < $fields_cnt; $j++)
				{
					if (!isset($row[$j]) || is_null($row[$j]))
					{
						$values[$j] = 'NULL';
					}
					else if ($field[$j]->numeric && ($field[$j]->type !== 'timestamp'))
					{
						$values[$j] = $row[$j];
					}
					else
					{
						$values[$j] = "'" . str_replace($search, $replace, $row[$j]) . "'";
					}
				}
				$query .= implode(', ', $values) . ')';

				$query_len += strlen($query);
				if ($query_len > $max_len)
				{
					$this->flush($query . ";\n\n");
					$query = '';
					$query_len = 0;
					$first_set = true;
				}
				else
				{
					$first_set = false;
				}
			}
			mysql_free_result($result);

			// check to make sure we have nothing left to flush
			if (!$first_set && $query)
			{
				$this->flush($query . ";\n\n");
			}
		}
	}

	function new_write_table($table_name)
	{
		global $db;

		$sql = 'SHOW CREATE TABLE ' . $table_name;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrow($result);

		$sql_data = '# Table: ' . $table_name . "\n";
		$sql_data .= "DROP TABLE IF EXISTS $table_name;\n";
		$this->flush($sql_data . $row['Create Table'] . ";\n\n");

		$db->sql_freeresult($result);
	}

	function old_write_table($table_name)
	{
		global $db;

		$sql_data = '# Table: ' . $table_name . "\n";
		$sql_data .= "DROP TABLE IF EXISTS $table_name;\n";
		$sql_data .= "CREATE TABLE $table_name(\n";
		$rows = array();

		$sql = "SHOW FIELDS
			FROM $table_name";
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$line = '   ' . $row['Field'] . ' ' . $row['Type'];

			if (!is_null($row['Default']))
			{
				$line .= " DEFAULT '{$row['Default']}'";
			}

			if ($row['Null'] != 'YES')
			{
				$line .= ' NOT NULL';
			}

			if ($row['Extra'] != '')
			{
				$line .= ' ' . $row['Extra'];
			}

			$rows[] = $line;
		}
		$db->sql_freeresult($result);

		$sql = "SHOW KEYS
			FROM $table_name";

		$result = $db->sql_query($sql);

		$index = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$kname = $row['Key_name'];

			if ($kname != 'PRIMARY')
			{
				if ($row['Non_unique'] == 0)
				{
					$kname = "UNIQUE|$kname";
				}
			}

			if ($row['Sub_part'])
			{
				$row['Column_name'] .= '(' . $row['Sub_part'] . ')';
			}
			$index[$kname][] = $row['Column_name'];
		}
		$db->sql_freeresult($result);

		foreach ($index as $key => $columns)
		{
			$line = '   ';

			if ($key == 'PRIMARY')
			{
				$line .= 'PRIMARY KEY (' . implode(', ', $columns) . ')';
			}
			else if (strpos($key, 'UNIQUE') === 0)
			{
				$line .= 'UNIQUE ' . substr($key, 7) . ' (' . implode(', ', $columns) . ')';
			}
			else if (strpos($key, 'FULLTEXT') === 0)
			{
				$line .= 'FULLTEXT ' . substr($key, 9) . ' (' . implode(', ', $columns) . ')';
			}
			else
			{
				$line .= "KEY $key (" . implode(', ', $columns) . ')';
			}

			$rows[] = $line;
		}

		$sql_data .= implode(",\n", $rows);
		$sql_data .= "\n);\n\n";

		$this->flush($sql_data);
	}
}

// get how much space we allow for a chunk of data, very similar to phpMyAdmin's way of doing things ;-) (hey, we only do this for MySQL anyway :P)
function get_usable_memory()
{
	$val = trim(@ini_get('memory_limit'));

	if (preg_match('/(\\d+)([mkg]?)/i', $val, $regs))
	{
		$memory_limit = (int) $regs[1];
		switch ($regs[2])
		{

			case 'k':
			case 'K':
				$memory_limit *= 1024;
			break;

			case 'm':
			case 'M':
				$memory_limit *= 1048576;
			break;

			case 'g':
			case 'G':
				$memory_limit *= 1073741824;
			break;
		}

		// how much memory PHP requires at the start of export (it is really a little less)
		if ($memory_limit > 6100000)
		{
			$memory_limit -= 6100000;
		}

		// allow us to consume half of the total memory available
		$memory_limit /= 2;
	}
	else
	{
		// set the buffer to 1M if we have no clue how much memory PHP will give us :P
		$memory_limit = 1048576;
	}

	return $memory_limit;
}

function sanitize_data_generic($text)
{
	$data = preg_split('/[\n\t\r\b\f]/', $text);
	preg_match_all('/[\n\t\r\b\f]/', $text, $matches);

	$val = array();

	foreach ($data as $value)
	{
		if (strlen($value))
		{
			$val[] = "'" . $value . "'";
		}
		if (sizeof($matches[0]))
		{
			$val[] = "'" . array_shift($matches[0]) . "'";
		}
	}

	return implode('||', $val);
}

// modified from PHP.net
function fgetd(&$fp, $delim, $read, $seek, $eof, $buffer = 8192)
{
	$record = '';
	$delim_len = strlen($delim);

	while (!$eof($fp))
	{
		$pos = strpos($record, $delim);
		if ($pos === false)
		{
			$record .= $read($fp, $buffer);
			if ($eof($fp) && ($pos = strpos($record, $delim)) !== false)
			{
				$seek($fp, $pos + $delim_len - strlen($record), SEEK_CUR);
				return substr($record, 0, $pos);
			}
		}
		else
		{
			$seek($fp, $pos + $delim_len - strlen($record), SEEK_CUR);
			return substr($record, 0, $pos);
		}
	}

	return false;
}

function fgetd_seekless(&$fp, $delim, $read, $seek, $eof, $buffer = 8192)
{
	static $array = array();
	static $record = '';

	if (!sizeof($array))
	{
		while (!$eof($fp))
		{
			if (strpos($record, $delim) !== false)
			{
				$array = explode($delim, $record);
				$record = array_pop($array);
				break;
			}
			else
			{
				$record .= $read($fp, $buffer);
			}
		}
		if ($eof($fp) && strpos($record, $delim) !== false)
		{
			$array = explode($delim, $record);
			$record = array_pop($array);
		}
	}

	if (sizeof($array))
	{
		return array_shift($array);
	}

	return false;
}

?>