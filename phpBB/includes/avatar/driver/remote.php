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
* Handles avatars hosted remotely
* @package avatars
*/
class phpbb_avatar_driver_remote extends phpbb_avatar_driver
{
	/**
	* @inheritdoc
	*/
	public function get_data($row, $ignore_config = false)
	{
		if ($ignore_config || $this->config['allow_avatar_remote'])
		{
			return array(
				'src' => $row['avatar'],
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
		$template->assign_vars(array(
			'AV_REMOTE_WIDTH' => (($row['avatar_type'] == AVATAR_REMOTE || $row['avatar_type'] == 'remote') && $row['avatar_width']) ? $row['avatar_width'] : request_var('av_local_width', 0),
			'AV_REMOTE_HEIGHT' => (($row['avatar_type'] == AVATAR_REMOTE || $row['avatar_type'] == 'remote') && $row['avatar_height']) ? $row['avatar_height'] : request_var('av_local_width', 0),
			'AV_REMOTE_URL' => (($row['avatar_type'] == AVATAR_REMOTE || $row['avatar_type'] == 'remote') && $row['avatar']) ? $row['avatar'] : '',
		));

		return true;
	}

	/**
	* @inheritdoc
	*/
	public function process_form($template, $row, &$error)
	{
		$url = request_var('av_remote_url', '');
		$width = request_var('av_remote_width', 0);
		$height = request_var('av_remote_height', 0);
			
		if (!preg_match('#^(http|https|ftp)://#i', $url))
		{
			$url = 'http://' . $url;
		}

		require_once($this->phpbb_root_path . 'includes/functions_user.' . $this->phpEx);

		$error = array_merge($error, validate_data(array(
			'url' => $url,
		), array(
			'url' => array('string', true, 5, 255),
		)));

		if (!empty($error))
		{
			return false;
		}

		// Check if this url looks alright
		// This isn't perfect, but it's what phpBB 3.0 did, and might as well make sure everything is compatible
		if (!preg_match('#^(http|https|ftp)://(?:(.*?\.)*?[a-z0-9\-]+?\.[a-z]{2,4}|(?:\d{1,3}\.){3,5}\d{1,3}):?([0-9]*?).*?\.(gif|jpg|jpeg|png)$#i', $url))
		{
			$error[] = 'AVATAR_URL_INVALID';
			return false;
		}

		// Make sure getimagesize works...
		if (function_exists('getimagesize'))
		{
			if (($width <= 0 || $height <= 0) && (($image_data = @getimagesize($url)) === false))
			{
				$error[] = 'UNABLE_GET_IMAGE_SIZE';
				return false;
			}

			if (!empty($image_data) && ($image_data[0] <= 0 || $image_data[1] <= 0))
			{
				$error[] = 'AVATAR_NO_SIZE';
				return false;
			}

			$width = ($width && $height) ? $width : $image_data[0];
			$height = ($width && $height) ? $height : $image_data[1];
		}

		if ($width <= 0 || $height <= 0)
		{
			$error[] = 'AVATAR_NO_SIZE';
			return false;
		}

		include_once($this->phpbb_root_path . 'includes/functions_upload.' . $this->phpEx);
		$types = fileupload::image_types();
		$extension = strtolower(filespec::get_extension($url));

		if (!empty($image_data) && (!isset($types[$image_data[2]]) || !in_array($extension, $types[$image_data[2]])))
		{
			if (!isset($types[$image_data[2]]))
			{
				$error[] = 'UNABLE_GET_IMAGE_SIZE';
			}
			else
			{
				$error[] = array('IMAGE_FILETYPE_MISMATCH', $types[$image_data[2]][0], $extension);
			}

			return false;
		}

		if ($this->config['avatar_max_width'] || $this->config['avatar_max_height'])
		{
			if ($width > $this->config['avatar_max_width'] || $height > $this->config['avatar_max_height'])
			{
				$error[] = array('AVATAR_WRONG_SIZE', $this->config['avatar_min_width'], $this->config['avatar_min_height'], $this->config['avatar_max_width'], $this->config['avatar_max_height'], $width, $height);
				return false;
			}
		}

		if ($this->config['avatar_min_width'] || $this->config['avatar_min_height'])
		{
			if ($width < $this->config['avatar_min_width'] || $height < $this->config['avatar_min_height'])
			{
				$error[] = array('AVATAR_WRONG_SIZE', $this->config['avatar_min_width'], $this->config['avatar_min_height'], $this->config['avatar_max_width'], $this->config['avatar_max_height'], $width, $height);
				return false;
			}
		}

		return array(
			'avatar' => $url,
			'avatar_width' => $width,
			'avatar_height' => $height,
		);
	}
}