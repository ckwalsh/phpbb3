<?php
/**
*
* @package avatar
* @copyright (c) 2011 phpBB Group
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
* Handles avatars uploaded to the board
* @package avatars
*/
class phpbb_avatar_driver_upload extends phpbb_avatar_driver
{
	/**
	* @inheritdoc
	*/
	public function get_data($row, $ignore_config = false)
	{
		if ($ignore_config || $this->config['allow_avatar_upload'])
		{
			return array(
				'src' => $this->phpbb_root_path . 'download/file.' . $this->phpEx . '?avatar=' . $row['avatar'],
				'width' => $row['avatar_width'],
				'height' => $row['avatar_height'],
			);
		}
		else
		{
			return array(
				'src' => '',
				'width' => 0,
				'height' => 0,
			);
		}
	}

	/**
	* @inheritdoc
	*/
	public function prepare_form($template, $row, &$error)
	{
		if (!$this->can_upload())
		{
			return false;
		}

		$template->assign_vars(array(
			'S_UPLOAD_AVATAR_URL' => ($this->config['allow_avatar_remote_upload']) ? true : false,
			'AV_UPLOAD_SIZE' => $this->config['avatar_filesize'],
		));
		
		return true;
	}

	/**
	* @inheritdoc
	*/
	public function process_form($template, $row, &$error)
	{
		if (!$this->can_upload())
		{
			return false;
		}

		include_once($this->phpbb_root_path . 'includes/functions_upload.' . $this->phpEx);

		$upload = new fileupload('AVATAR_', array('jpg', 'jpeg', 'gif', 'png'), $this->config['avatar_filesize'], $this->config['avatar_min_width'], $this->config['avatar_min_height'], $this->config['avatar_max_width'], $this->config['avatar_max_height'], (isset($this->config['mime_triggers']) ? explode('|', $this->config['mime_triggers']) : false));

		$url = request_var('av_upload_url', '');

		if (!empty($_FILES['av_upload_file']['name']))
		{
			$file = $upload->form_upload('av_upload_file');
		}
		else
		{
			$file = $upload->remote_upload($url);
		}

		$prefix = $this->config['avatar_salt'] . '_';
		$file->clean_filename('avatar', $prefix, $row['id']);

		$destination = $this->config['avatar_path'];

		// Adjust destination path (no trailing slash)
		if (substr($destination, -1, 1) == '/' || substr($destination, -1, 1) == '\\')
		{
			$destination = substr($destination, 0, -1);
		}

		$destination = str_replace(array('../', '..\\', './', '.\\'), '', $destination);
		if ($destination && ($destination[0] == '/' || $destination[0] == "\\"))
		{
			$destination = '';
		}

		// Move file and overwrite any existing image
		$file->move_file($destination, true);

		if (sizeof($file->error))
		{
			$file->remove();
			$error = array_merge($error, $file->error);
			return false;
		}

		return array(
			'avatar' => $row['id'] . '_' . time() . '.' . $file->get('extension'),
			'avatar_width' => $file->get('width'),
			'avatar_height' => $file->get('height'),
		);
	}

	/**
	* @inheritdoc
	*/
	public function delete($row)
	{
		$ext = substr(strrchr($row['avatar'], '.'), 1);
		$filename = $this->phpbb_root_path . $this->config['avatar_path'] . '/' . $this->config['avatar_salt'] . '_' . $row['id'] . '.' . $ext;

		if (file_exists($filename))
		{
			@unlink($filename);
		}

		return true;
	}

	/**
	* @TODO
	*/
	private function can_upload()
	{
		return (file_exists($this->phpbb_root_path . $this->config['avatar_path']) && phpbb_is_writable($this->phpbb_root_path . $this->config['avatar_path']) && (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on'));
	}
}