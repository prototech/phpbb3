<?php
/**
*
* @package phpBB3
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
* Custom Profile Fields
* @package phpBB3
*/
class custom_profile
{
	public static $profile_types = array(FIELD_INT => 'int', FIELD_STRING => 'string', FIELD_TEXT => 'text', FIELD_BOOL => 'bool', FIELD_DROPDOWN => 'dropdown', FIELD_DATE => 'date');
	private $profile_cache = array();
	private $options_lang = array();

	/**
	* Assign editable fields to template, mode can be profile (for profile change) or register (for registration)
	* Called by ucp_profile and ucp_register
	* @access public
	*/
	public function generate_profile_fields($mode, $lang_id)
	{
		$sql_where = '';
		switch ($mode)
		{
			case 'register':
				// If the field is required we show it on the registration page
				$sql_where .= ' AND f.field_show_on_reg = 1';
			break;

			case 'profile':
				// Show hidden fields to moderators/admins
				if (!phpbb::$acl->acl_gets('a_', 'm_') && !phpbb::$acl->acl_getf_global('m_'))
				{
					$sql_where .= ' AND f.field_hide = 0';
				}
			break;

			default:
				trigger_error('Wrong profile mode specified', E_USER_ERROR);
			break;
		}

		$sql = 'SELECT l.*, f.*
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . " f
			WHERE f.field_active = 1
				$sql_where
				AND l.lang_id = $lang_id
				AND l.field_id = f.field_id
			ORDER BY f.field_order";
		$result = phpbb::$db->sql_query($sql);

		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			// Return templated field
			$tpl_snippet = $this->process_field_row('change', $row);

			// Some types are multivalue, we can't give them a field_id as we would not know which to pick
			$type = (int) $row['field_type'];

			phpbb::$template->assign_block_vars('profile_fields', array(
				'LANG_NAME'		=> $row['lang_name'],
				'LANG_EXPLAIN'	=> $row['lang_explain'],
				'FIELD'			=> $tpl_snippet,
				'FIELD_ID'		=> ($type == FIELD_DATE || ($type == FIELD_BOOL && $row['field_length'] == '1')) ? '' : 'pf_' . $row['field_ident'],
				'S_REQUIRED'	=> ($row['field_required']) ? true : false,
			));
		}
		phpbb::$db->sql_freeresult($result);
	}

	/**
	* Validate entered profile field data
	* @access public
	*/
	public function validate_profile_field($field_type, &$field_value, $field_data)
	{
		switch ($field_type)
		{
			case FIELD_INT:
			case FIELD_DROPDOWN:
				$field_value = (int) $field_value;
			break;

			case FIELD_BOOL:
				$field_value = (bool) $field_value;
			break;
		}

		switch ($field_type)
		{
			case FIELD_DATE:
				$field_validate = explode('-', $field_value);

				$day = (isset($field_validate[0])) ? (int) $field_validate[0] : 0;
				$month = (isset($field_validate[1])) ? (int) $field_validate[1] : 0;
				$year = (isset($field_validate[2])) ? (int) $field_validate[2] : 0;

				if ((!$day || !$month || !$year) && !$field_data['field_required'])
				{
					return false;
				}

				if ((!$day || !$month || !$year) && $field_data['field_required'])
				{
					return 'FIELD_REQUIRED';
				}

				if ($day < 0 || $day > 31 || $month < 0 || $month > 12 || ($year < 1901 && $year > 0) || $year > gmdate('Y', time()) + 50)
				{
					return 'FIELD_INVALID_DATE';
				}

				if (checkdate($month, $day, $year) === false)
				{
					return 'FIELD_INVALID_DATE';
				}
			break;

			case FIELD_BOOL:
				if (!$field_value && $field_data['field_required'])
				{
					return 'FIELD_REQUIRED';
				}
			break;

			case FIELD_INT:
				if (empty($field_value) && !$field_data['field_required'])
				{
					return false;
				}

				if ($field_value < $field_data['field_minlen'])
				{
					return 'FIELD_TOO_SMALL';
				}
				else if ($field_value > $field_data['field_maxlen'])
				{
					return 'FIELD_TOO_LARGE';
				}
			break;

			case FIELD_DROPDOWN:
				if ($field_value == $field_data['field_novalue'] && $field_data['field_required'])
				{
					return 'FIELD_REQUIRED';
				}
			break;

			case FIELD_STRING:
			case FIELD_TEXT:
				if (empty($field_value) && !$field_data['field_required'])
				{
					return false;
				}
				else if (empty($field_value) && $field_data['field_required'])
				{
					return 'FIELD_REQUIRED';
				}

				if ($field_data['field_minlen'] && utf8_strlen($field_value) < $field_data['field_minlen'])
				{
					return 'FIELD_TOO_SHORT';
				}
				else if ($field_data['field_maxlen'] && utf8_strlen($field_value) > $field_data['field_maxlen'])
				{
					return 'FIELD_TOO_LONG';
				}

				if (!empty($field_data['field_validation']) && $field_data['field_validation'] != '.*')
				{
					$field_validate = ($field_type == FIELD_STRING) ? $field_value : bbcode_nl2br($field_value);
					if (!preg_match('#^' . str_replace('\\\\', '\\', $field_data['field_validation']) . '$#i', $field_validate))
					{
						return 'FIELD_INVALID_CHARS';
					}
				}
			break;
		}

		return false;
	}

	/**
	* Build profile cache, used for display
	* @access private
	*/
	private function build_cache()
	{
		$this->profile_cache = array();

		// Display hidden/no_view fields for admin/moderator
		$sql = 'SELECT l.*, f.*
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . ' f
			WHERE l.lang_id = ' . phpbb::$user->get_iso_lang_id() . '
				AND f.field_active = 1 ' .
				((!phpbb::$acl->acl_gets('a_', 'm_') && !phpbb::$acl->acl_getf_global('m_')) ? '	AND f.field_hide = 0 ' : '') . '
				AND f.field_no_view = 0
				AND l.field_id = f.field_id
			ORDER BY f.field_order';
		$result = phpbb::$db->sql_query($sql);

		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$this->profile_cache[$row['field_ident']] = $row;
		}
		phpbb::$db->sql_freeresult($result);
	}

	/**
	* Get language entries for options and store them here for later use
	*/
	private function get_option_lang($field_id, $lang_id, $field_type, $preview)
	{
		if ($preview)
		{
			$lang_options = (!is_array($this->vars['lang_options'])) ? explode("\n", $this->vars['lang_options']) : $this->vars['lang_options'];

			// @todo: ref optimize
			foreach ($lang_options as $num => $var)
			{
				$this->options_lang[$field_id][$lang_id][($num + 1)] = $var;
			}
		}
		else
		{
			$sql = 'SELECT option_id, lang_value
				FROM ' . PROFILE_FIELDS_LANG_TABLE . "
					WHERE field_id = $field_id
					AND lang_id = $lang_id
					AND field_type = $field_type
				ORDER BY option_id";
			$result = phpbb::$db->sql_query($sql);

			// @todo: ref optimize
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$this->options_lang[$field_id][$lang_id][($row['option_id'] + 1)] = $row['lang_value'];
			}
			phpbb::$db->sql_freeresult($result);
		}
	}

	/**
	* Submit profile field
	* @access public
	*/
	public function submit_cp_field($mode, $lang_id, &$cp_data, &$cp_error)
	{
		$sql_where = '';
		switch ($mode)
		{
			case 'register':
				// If the field is required we show it on the registration page and do not show hidden fields
				$sql_where .= ' AND (f.field_show_on_reg = 1 OR f.field_required = 1) AND f.field_hide = 0';
			break;

			case 'profile':
				// Show hidden fields to moderators/admins
				if (!phpbb::$acl->acl_gets('a_', 'm_') && !phpbb::$acl->acl_getf_global('m_'))
				{
					$sql_where .= ' AND f.field_hide = 0';
				}
			break;

			default:
				trigger_error('Wrong profile mode specified', E_USER_ERROR);
			break;
		}

		$sql = 'SELECT l.*, f.*
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . " f
			WHERE l.lang_id = $lang_id
				AND f.field_active = 1
				$sql_where
				AND l.field_id = f.field_id
			ORDER BY f.field_order";
		$result = phpbb::$db->sql_query($sql);

		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			$cp_data['pf_' . $row['field_ident']] = $this->get_profile_field($row);
			$check_value = $cp_data['pf_' . $row['field_ident']];

			if (($cp_result = $this->validate_profile_field($row['field_type'], $check_value, $row)) !== false)
			{
				// If not and only showing common error messages, use this one
				$error = '';
				switch ($cp_result)
				{
					case 'FIELD_INVALID_DATE':
					case 'FIELD_REQUIRED':
						$error = sprintf(phpbb::$user->lang[$cp_result], $row['lang_name']);
					break;

					case 'FIELD_TOO_SHORT':
					case 'FIELD_TOO_SMALL':
						$error = sprintf(phpbb::$user->lang[$cp_result], $row['lang_name'], $row['field_minlen']);
					break;

					case 'FIELD_TOO_LONG':
					case 'FIELD_TOO_LARGE':
						$error = sprintf(phpbb::$user->lang[$cp_result], $row['lang_name'], $row['field_maxlen']);
					break;

					case 'FIELD_INVALID_CHARS':
						switch ($row['field_validation'])
						{
							case '[0-9]+':
								$error = sprintf(phpbb::$user->lang[$cp_result . '_NUMBERS_ONLY'], $row['lang_name']);
							break;

							case '[\w]+':
								$error = sprintf(phpbb::$user->lang[$cp_result . '_ALPHA_ONLY'], $row['lang_name']);
							break;

							case '[\w_\+\. \-\[\]]+':
								$error = sprintf(phpbb::$user->lang[$cp_result . '_SPACERS_ONLY'], $row['lang_name']);
							break;
						}
					break;
				}

				if ($error != '')
				{
					$cp_error[] = $error;
				}
			}
		}
		phpbb::$db->sql_freeresult($result);
	}

	/**
	* Assign fields to template, used for viewprofile, viewtopic and memberlist (if load setting is enabled)
	* This is directly connected to the user -> mode == grab is to grab the user specific fields, mode == show is for assigning the row to the template
	* @access public
	*/
	public function generate_profile_fields_template($mode, $user_id = 0, $profile_row = false)
	{
		if ($mode == 'grab')
		{
			if (!is_array($user_id))
			{
				$user_id = array($user_id);
			}

			if (!sizeof($this->profile_cache))
			{
				$this->build_cache();
			}

			if (!sizeof($user_id))
			{
				return array();
			}

			$sql = 'SELECT *
				FROM ' . PROFILE_FIELDS_DATA_TABLE . '
				WHERE ' . phpbb::$db->sql_in_set('user_id', array_map('intval', $user_id));
			$result = phpbb::$db->sql_query($sql);

			$field_data = array();
			while ($row = phpbb::$db->sql_fetchrow($result))
			{
				$field_data[$row['user_id']] = $row;
			}
			phpbb::$db->sql_freeresult($result);

			$user_fields = array();

			// Go through the fields in correct order
			foreach (array_keys($this->profile_cache) as $used_ident)
			{
				foreach ($field_data as $user_id => $row)
				{
					$user_fields[$user_id][$used_ident]['value'] = $row['pf_' . $used_ident];
					$user_fields[$user_id][$used_ident]['data'] = $this->profile_cache[$used_ident];
				}
			}

			return $user_fields;
		}
		else if ($mode == 'show')
		{
			// $profile_row == $user_fields[$row['user_id']];
			$tpl_fields = array();
			$tpl_fields['row'] = $tpl_fields['blockrow'] = array();

			foreach ($profile_row as $ident => $ident_ary)
			{
				$value = $this->get_profile_value($ident_ary);

				if ($value === NULL)
				{
					continue;
				}

				$tpl_fields['row'] += array(
					'PROFILE_' . strtoupper($ident) . '_VALUE'	=> $value,
					'PROFILE_' . strtoupper($ident) . '_TYPE'	=> $ident_ary['data']['field_type'],
					'PROFILE_' . strtoupper($ident) . '_NAME'	=> $ident_ary['data']['lang_name'],
					'PROFILE_' . strtoupper($ident) . '_EXPLAIN'=> $ident_ary['data']['lang_explain'],

					'S_PROFILE_' . strtoupper($ident)			=> true
				);

				$tpl_fields['blockrow'][] = array(
					'PROFILE_FIELD_VALUE'	=> $value,
					'PROFILE_FIELD_TYPE'	=> $ident_ary['data']['field_type'],
					'PROFILE_FIELD_NAME'	=> $ident_ary['data']['lang_name'],
					'PROFILE_FIELD_EXPLAIN'	=> $ident_ary['data']['lang_explain'],

					'S_PROFILE_' . strtoupper($ident)		=> true
				);
			}

			return $tpl_fields;
		}
		else
		{
			trigger_error('Wrong mode for custom profile', E_USER_ERROR);
		}
	}

	/**
	* Get Profile Value for display
	*/
	private function get_profile_value($ident_ary)
	{
		$value = $ident_ary['value'];
		$field_type = $ident_ary['data']['field_type'];

		switch (self::$profile_types[$field_type])
		{
			case 'int':
				if ($value == '')
				{
					return NULL;
				}
				return (int) $value;
			break;

			case 'string':
			case 'text':
				if (!$value)
				{
					return NULL;
				}

				$value = make_clickable($value);
				$value = censor_text($value);
				$value = bbcode_nl2br($value);
				return $value;
			break;

			// case 'datetime':
			case 'date':
				$date = explode('-', $value);
				$day = (isset($date[0])) ? (int) $date[0] : 0;
				$month = (isset($date[1])) ? (int) $date[1] : 0;
				$year = (isset($date[2])) ? (int) $date[2] : 0;

				if (!$day && !$month && !$year)
				{
					return NULL;
				}
				else if ($day && $month && $year)
				{
					// d/m/y 00:00 GMT isn't necessarily on the same d/m/y in the user's timezone, so add the timezone seconds
					return phpbb::$user->format_date(gmmktime(0, 0, 0, $month, $day, $year) + phpbb::$user->timezone + phpbb::$user->dst, phpbb::$user->lang['DATE_FORMAT'], true);
				}

				return $value;
			break;

			case 'dropdown':
				$field_id = $ident_ary['data']['field_id'];
				$lang_id = $ident_ary['data']['lang_id'];
				if (!isset($this->options_lang[$field_id][$lang_id]))
				{
					$this->get_option_lang($field_id, $lang_id, FIELD_DROPDOWN, false);
				}

				if ($value == $ident_ary['data']['field_novalue'])
				{
					return NULL;
				}

				$value = (int) $value;

				// User not having a value assigned
				if (!isset($this->options_lang[$field_id][$lang_id][$value]))
				{
					return NULL;
				}

				return $this->options_lang[$field_id][$lang_id][$value];
			break;

			case 'bool':
				$field_id = $ident_ary['data']['field_id'];
				$lang_id = $ident_ary['data']['lang_id'];
				if (!isset($this->options_lang[$field_id][$lang_id]))
				{
					$this->get_option_lang($field_id, $lang_id, FIELD_BOOL, false);
				}

				if ($ident_ary['data']['field_length'] == 1)
				{
					return (isset($this->options_lang[$field_id][$lang_id][(int) $value])) ? $this->options_lang[$field_id][$lang_id][(int) $value] : NULL;
				}
				else if (!$value)
				{
					return NULL;
				}
				else
				{
					return $this->options_lang[$field_id][$lang_id][(int) ($value) + 1];
				}
			break;

			default:
				trigger_error('Unknown profile type', E_USER_ERROR);
			break;
		}
	}

	/**
	* Get field value for registration/profile
	* @access private
	*/
	private function get_var($field_validation, &$profile_row, $default_value, $preview)
	{
		$profile_row['field_ident'] = (isset($profile_row['var_name'])) ? $profile_row['var_name'] : 'pf_' . $profile_row['field_ident'];
		$user_ident = $profile_row['field_ident'];
		// checkbox - only testing for isset
		if ($profile_row['field_type'] == FIELD_BOOL && $profile_row['field_length'] == 2)
		{
			$value = (phpbb_request::is_set($profile_row['field_ident'])) ? true : ((!isset(phpbb::$user->profile_fields[$user_ident]) || $preview) ? $default_value : phpbb::$user->profile_fields[$user_ident]);
		}
		else if ($profile_row['field_type'] == FIELD_INT)
		{
			if (phpbb_request::is_set($profile_row['field_ident']))
			{
				$value = (request_var($profile_row['field_ident'], '') === '') ? null : request_var($profile_row['field_ident'], $default_value);
			}
			else
			{
				if (!$preview && isset(phpbb::$user->profile_fields[$user_ident]) && is_null(phpbb::$user->profile_fields[$user_ident]))
				{
					$value = null;
				}
				else if (!isset(phpbb::$user->profile_fields[$user_ident]) || $preview)
				{
					$value = $default_value;
				}
				else
				{
					$value = phpbb::$user->profile_fields[$user_ident];
				}
			}

			return (is_null($value)) ? '' : (int) $value;
		}
		else
		{
			$value = (phpbb_request::is_set($profile_row['field_ident'])) ? request_var($profile_row['field_ident'], $default_value, true) : ((!isset(phpbb::$user->profile_fields[$user_ident]) || $preview) ? $default_value : phpbb::$user->profile_fields[$user_ident]);

			if (gettype($value) == 'string')
			{
				$value = utf8_normalize_nfc($value);
			}
		}

		switch ($field_validation)
		{
			case 'int':
				return (int) $value;
			break;
		}

		return $value;
	}

	/**
	* Process int-type
	* @access private
	*/
	private function generate_int($profile_row, $preview = false)
	{
		$profile_row['field_value'] = $this->get_var('int', $profile_row, $profile_row['field_default_value'], $preview);
		phpbb::$template->assign_block_vars(self::$profile_types[$profile_row['field_type']], array_change_key_case($profile_row, CASE_UPPER));
	}

	/**
	* Process date-type
	* @access private
	*/
	private function generate_date($profile_row, $preview = false)
	{
		$profile_row['field_ident'] = (isset($profile_row['var_name'])) ? $profile_row['var_name'] : 'pf_' . $profile_row['field_ident'];
		$user_ident = $profile_row['field_ident'];

		$now = getdate();

		if (!phpbb_request::is_set($profile_row['field_ident'] . '_day'))
		{
			if ($profile_row['field_default_value'] == 'now')
			{
				$profile_row['field_default_value'] = sprintf('%2d-%2d-%4d', $now['mday'], $now['mon'], $now['year']);
			}
			list($day, $month, $year) = explode('-', ((!isset(phpbb::$user->profile_fields[$user_ident]) || $preview) ? $profile_row['field_default_value'] : phpbb::$user->profile_fields[$user_ident]));
		}
		else
		{
			if ($preview && $profile_row['field_default_value'] == 'now')
			{
				$profile_row['field_default_value'] = sprintf('%2d-%2d-%4d', $now['mday'], $now['mon'], $now['year']);
				list($day, $month, $year) = explode('-', ((!isset(phpbb::$user->profile_fields[$user_ident]) || $preview) ? $profile_row['field_default_value'] : phpbb::$user->profile_fields[$user_ident]));
			}
			else
			{
				$day = request_var($profile_row['field_ident'] . '_day', 0);
				$month = request_var($profile_row['field_ident'] . '_month', 0);
				$year = request_var($profile_row['field_ident'] . '_year', 0);
			}
		}

		$profile_row['s_day_options'] = '<option value="0"' . ((!$day) ? ' selected="selected"' : '') . '>--</option>';
		for ($i = 1; $i < 32; $i++)
		{
			$profile_row['s_day_options'] .= '<option value="' . $i . '"' . (($i == $day) ? ' selected="selected"' : '') . ">$i</option>";
		}

		$profile_row['s_month_options'] = '<option value="0"' . ((!$month) ? ' selected="selected"' : '') . '>--</option>';
		for ($i = 1; $i < 13; $i++)
		{
			$profile_row['s_month_options'] .= '<option value="' . $i . '"' . (($i == $month) ? ' selected="selected"' : '') . ">$i</option>";
		}

		$profile_row['s_year_options'] = '<option value="0"' . ((!$year) ? ' selected="selected"' : '') . '>--</option>';
		for ($i = $now['year'] - 100; $i <= $now['year'] + 100; $i++)
		{
			$profile_row['s_year_options'] .= '<option value="' . $i . '"' . (($i == $year) ? ' selected="selected"' : '') . ">$i</option>";
		}
		unset($now);

		$profile_row['field_value'] = 0;
		phpbb::$template->assign_block_vars(self::$profile_types[$profile_row['field_type']], array_change_key_case($profile_row, CASE_UPPER));
	}

	/**
	* Process bool-type
	* @access private
	*/
	private function generate_bool($profile_row, $preview = false)
	{
		$value = $this->get_var('int', $profile_row, $profile_row['field_default_value'], $preview);

		$profile_row['field_value'] = $value;
		phpbb::$template->assign_block_vars(self::$profile_types[$profile_row['field_type']], array_change_key_case($profile_row, CASE_UPPER));

		if ($profile_row['field_length'] == 1)
		{
			if (!isset($this->options_lang[$profile_row['field_id']][$profile_row['lang_id']]) || !sizeof($this->options_lang[$profile_row['field_id']][$profile_row['lang_id']]))
			{
				$this->get_option_lang($profile_row['field_id'], $profile_row['lang_id'], FIELD_BOOL, $preview);
			}

			foreach ($this->options_lang[$profile_row['field_id']][$profile_row['lang_id']] as $option_id => $option_value)
			{
				phpbb::$template->assign_block_vars('bool.options', array(
					'OPTION_ID'	=> $option_id,
					'CHECKED'	=> ($value == $option_id) ? ' checked="checked"' : '',
					'VALUE'		=> $option_value,
				));
			}
		}
	}

	/**
	* Process string-type
	* @access private
	*/
	private function generate_string($profile_row, $preview = false)
	{
		$profile_row['field_value'] = $this->get_var('string', $profile_row, $profile_row['lang_default_value'], $preview);
		phpbb::$template->assign_block_vars(self::$profile_types[$profile_row['field_type']], array_change_key_case($profile_row, CASE_UPPER));
	}

	/**
	* Process text-type
	* @access private
	*/
	private function generate_text($profile_row, $preview = false)
	{
		$field_length = explode('|', $profile_row['field_length']);
		$profile_row['field_rows'] = $field_length[0];
		$profile_row['field_cols'] = $field_length[1];

		$profile_row['field_value'] = $this->get_var('string', $profile_row, $profile_row['lang_default_value'], $preview);
		phpbb::$template->assign_block_vars(self::$profile_types[$profile_row['field_type']], array_change_key_case($profile_row, CASE_UPPER));
	}

	/**
	* Process dropdown-type
	* @access private
	*/
	private function generate_dropdown($profile_row, $preview = false)
	{
		$value = $this->get_var('int', $profile_row, $profile_row['field_default_value'], $preview);

		if (!isset($this->options_lang[$profile_row['field_id']]) || !isset($this->options_lang[$profile_row['field_id']][$profile_row['lang_id']]) || !sizeof($this->options_lang[$profile_row['field_id']][$profile_row['lang_id']]))
		{
			$this->get_option_lang($profile_row['field_id'], $profile_row['lang_id'], FIELD_DROPDOWN, $preview);
		}

		$profile_row['field_value'] = $value;
		phpbb::$template->assign_block_vars(self::$profile_types[$profile_row['field_type']], array_change_key_case($profile_row, CASE_UPPER));

		foreach ($this->options_lang[$profile_row['field_id']][$profile_row['lang_id']] as $option_id => $option_value)
		{
			phpbb::$template->assign_block_vars('dropdown.options', array(
				'OPTION_ID'	=> $option_id,
				'SELECTED'	=> ($value == $option_id) ? ' selected="selected"' : '',
				'VALUE'		=> $option_value,
			));
		}
	}

	/**
	* Return Templated value/field. Possible values for $mode are:
	* change == user is able to set/enter profile values; preview == just show the value
	* @access private
	*/
	private function process_field_row($mode, $profile_row)
	{
		$preview = ($mode == 'preview') ? true : false;

		// set template filename
		phpbb::$template->set_filenames(array(
			'cp_body'		=> 'custom_profile_fields.html',
		));

		// empty previously filled blockvars
		foreach (self::$profile_types as $field_case => $field_type)
		{
			phpbb::$template->destroy_block_vars($field_type);
		}

		// Assign template variables
		$type_func = 'generate_' . self::$profile_types[$profile_row['field_type']];
		$this->$type_func($profile_row, $preview);

		// Return templated data
		return phpbb::$template->assign_display('cp_body');
	}

	/**
	* Build Array for user insertion into custom profile fields table
	*/
	public static function build_insert_sql_array($cp_data)
	{
		$sql_not_in = array();
		foreach ($cp_data as $key => $null)
		{
			$sql_not_in[] = (strncmp($key, 'pf_', 3) === 0) ? substr($key, 3) : $key;
		}

		$sql = 'SELECT f.field_type, f.field_ident, f.field_default_value, l.lang_default_value
			FROM ' . PROFILE_LANG_TABLE . ' l, ' . PROFILE_FIELDS_TABLE . ' f
			WHERE l.lang_id = ' . phpbb::$user->get_iso_lang_id() . '
				' . ((sizeof($sql_not_in)) ? ' AND ' . phpbb::$db->sql_in_set('f.field_ident', $sql_not_in, true) : '') . '
				AND l.field_id = f.field_id';
		$result = phpbb::$db->sql_query($sql);

		while ($row = phpbb::$db->sql_fetchrow($result))
		{
			if ($row['field_default_value'] == 'now' && $row['field_type'] == FIELD_DATE)
			{
				$now = getdate();
				$row['field_default_value'] = sprintf('%2d-%2d-%4d', $now['mday'], $now['mon'], $now['year']);
			}

			$cp_data['pf_' . $row['field_ident']] = (in_array($row['field_type'], array(FIELD_TEXT, FIELD_STRING))) ? $row['lang_default_value'] : $row['field_default_value'];
		}
		phpbb::$db->sql_freeresult($result);

		return $cp_data;
	}

	/**
	* Get profile field value on submit
	* @access private
	*/
	private function get_profile_field($profile_row)
	{
		$var_name = 'pf_' . $profile_row['field_ident'];

		switch ($profile_row['field_type'])
		{
			case FIELD_DATE:

				if (!phpbb_request::is_set($var_name . '_day'))
				{
					if ($profile_row['field_default_value'] == 'now')
					{
						$now = getdate();
						$profile_row['field_default_value'] = sprintf('%2d-%2d-%4d', $now['mday'], $now['mon'], $now['year']);
					}
					list($day, $month, $year) = explode('-', $profile_row['field_default_value']);
				}
				else
				{
					$day = request_var($var_name . '_day', 0);
					$month = request_var($var_name . '_month', 0);
					$year = request_var($var_name . '_year', 0);
				}

				$var = sprintf('%2d-%2d-%4d', $day, $month, $year);
			break;

			case FIELD_BOOL:
				// Checkbox
				if ($profile_row['field_length'] == 2)
				{
					$var = phpbb_request::is_set($var_name) ? 1 : 0;
				}
				else
				{
					$var = request_var($var_name, (int) $profile_row['field_default_value']);
				}
			break;

			case FIELD_STRING:
			case FIELD_TEXT:
				$var = utf8_normalize_nfc(request_var($var_name, (string) $profile_row['field_default_value'], true));
			break;

			case FIELD_INT:
				if (phpbb_request::is_set($var_name) && request_var($var_name, '') === '')
				{
					$var = NULL;
				}
				else
				{
					$var = request_var($var_name, (int) $profile_row['field_default_value']);
				}
			break;

			case FIELD_DROPDOWN:
				$var = request_var($var_name, (int) $profile_row['field_default_value']);
			break;

			default:
				$var = request_var($var_name, $profile_row['field_default_value']);
			break;
		}

		return $var;
	}
}

/**
* Custom Profile Fields ACP
* @package phpBB3
*/
class custom_profile_admin extends custom_profile
{
	public $vars = array();

	/**
	* Return possible validation options
	*/
	public function validate_options()
	{
		$validate_ary = array('CHARS_ANY' => '.*', 'NUMBERS_ONLY' => '[0-9]+', 'ALPHA_ONLY' => '[\w]+', 'ALPHA_SPACERS' => '[\w_\+\. \-\[\]]+');

		$validate_options = '';
		foreach ($validate_ary as $lang => $value)
		{
			$selected = ($this->vars['field_validation'] == $value) ? ' selected="selected"' : '';
			$validate_options .= '<option value="' . $value . '"' . $selected . '>' . phpbb::$user->lang[$lang] . '</option>';
		}

		return $validate_options;
	}

	/**
	* Get string options for second step in ACP
	*/
	public function get_string_options()
	{
		$options = array(
			0 => array('TITLE' => phpbb::$user->lang['FIELD_LENGTH'],		'FIELD' => '<input type="text" name="field_length" size="5" value="' . $this->vars['field_length'] . '" />'),
			1 => array('TITLE' => phpbb::$user->lang['MIN_FIELD_CHARS'],	'FIELD' => '<input type="text" name="field_minlen" size="5" value="' . $this->vars['field_minlen'] . '" />'),
			2 => array('TITLE' => phpbb::$user->lang['MAX_FIELD_CHARS'],	'FIELD' => '<input type="text" name="field_maxlen" size="5" value="' . $this->vars['field_maxlen'] . '" />'),
			3 => array('TITLE' => phpbb::$user->lang['FIELD_VALIDATION'],	'FIELD' => '<select name="field_validation">' . $this->validate_options() . '</select>')
		);

		return $options;
	}

	/**
	* Get text options for second step in ACP
	*/
	public function get_text_options()
	{
		$options = array(
			0 => array('TITLE' => phpbb::$user->lang['FIELD_LENGTH'],		'FIELD' => '<input name="rows" size="5" value="' . $this->vars['rows'] . '" /> ' . phpbb::$user->lang['ROWS'] . '</dd><dd><input name="columns" size="5" value="' . $this->vars['columns'] . '" /> ' . phpbb::$user->lang['COLUMNS'] . ' <input type="hidden" name="field_length" value="' . $this->vars['field_length'] . '" />'),
			1 => array('TITLE' => phpbb::$user->lang['MIN_FIELD_CHARS'],	'FIELD' => '<input type="text" name="field_minlen" size="10" value="' . $this->vars['field_minlen'] . '" />'),
			2 => array('TITLE' => phpbb::$user->lang['MAX_FIELD_CHARS'],	'FIELD' => '<input type="text" name="field_maxlen" size="10" value="' . $this->vars['field_maxlen'] . '" />'),
			3 => array('TITLE' => phpbb::$user->lang['FIELD_VALIDATION'],	'FIELD' => '<select name="field_validation">' . $this->validate_options() . '</select>')
		);

		return $options;
	}

	/**
	* Get int options for second step in ACP
	*/
	public function get_int_options()
	{
		$options = array(
			0 => array('TITLE' => phpbb::$user->lang['FIELD_LENGTH'],		'FIELD' => '<input type="text" name="field_length" size="5" value="' . $this->vars['field_length'] . '" />'),
			1 => array('TITLE' => phpbb::$user->lang['MIN_FIELD_NUMBER'],	'FIELD' => '<input type="text" name="field_minlen" size="5" value="' . $this->vars['field_minlen'] . '" />'),
			2 => array('TITLE' => phpbb::$user->lang['MAX_FIELD_NUMBER'],	'FIELD' => '<input type="text" name="field_maxlen" size="5" value="' . $this->vars['field_maxlen'] . '" />'),
			3 => array('TITLE' => phpbb::$user->lang['DEFAULT_VALUE'],		'FIELD' => '<input type="post" name="field_default_value" value="' . $this->vars['field_default_value'] . '" />')
		);

		return $options;
	}

	/**
	* Get bool options for second step in ACP
	*/
	public function get_bool_options()
	{
		global $lang_defs;

		$default_lang_id = $lang_defs['iso'][phpbb::$config['default_lang']];

		$profile_row = array(
			'var_name'				=> 'field_default_value',
			'field_id'				=> 1,
			'lang_name'				=> $this->vars['lang_name'],
			'lang_explain'			=> $this->vars['lang_explain'],
			'lang_id'				=> $default_lang_id,
			'field_default_value'	=> $this->vars['field_default_value'],
			'field_ident'			=> 'field_default_value',
			'field_type'			=> FIELD_BOOL,
			'field_length'			=> $this->vars['field_length'],
			'lang_options'			=> $this->vars['lang_options']
		);

		$options = array(
			0 => array('TITLE' => phpbb::$user->lang['FIELD_TYPE'], 'EXPLAIN' => phpbb::$user->lang['BOOL_TYPE_EXPLAIN'], 'FIELD' => '<label><input type="radio" class="radio" name="field_length" value="1"' . (($this->vars['field_length'] == 1) ? ' checked="checked"' : '') . ' onchange="document.getElementById(\'add_profile_field\').submit();" /> ' . phpbb::$user->lang['RADIO_BUTTONS'] . '</label><label><input type="radio" class="radio" name="field_length" value="2"' . (($this->vars['field_length'] == 2) ? ' checked="checked"' : '') . ' onchange="document.getElementById(\'add_profile_field\').submit();" /> ' . phpbb::$user->lang['CHECKBOX'] . '</label>'),
			1 => array('TITLE' => phpbb::$user->lang['DEFAULT_VALUE'], 'FIELD' => $this->process_field_row('preview', $profile_row))
		);

		return $options;
	}

	/**
	* Get dropdown options for second step in ACP
	*/
	public function get_dropdown_options()
	{
		global $lang_defs;

		$default_lang_id = $lang_defs['iso'][phpbb::$config['default_lang']];

		$profile_row[0] = array(
			'var_name'				=> 'field_default_value',
			'field_id'				=> 1,
			'lang_name'				=> $this->vars['lang_name'],
			'lang_explain'			=> $this->vars['lang_explain'],
			'lang_id'				=> $default_lang_id,
			'field_default_value'	=> $this->vars['field_default_value'],
			'field_ident'			=> 'field_default_value',
			'field_type'			=> FIELD_DROPDOWN,
			'lang_options'			=> $this->vars['lang_options']
		);

		$profile_row[1] = $profile_row[0];
		$profile_row[1]['var_name'] = 'field_novalue';
		$profile_row[1]['field_ident'] = 'field_novalue';
		$profile_row[1]['field_default_value']	= $this->vars['field_novalue'];

		$options = array(
			0 => array('TITLE' => phpbb::$user->lang['DEFAULT_VALUE'], 'FIELD' => $this->process_field_row('preview', $profile_row[0])),
			1 => array('TITLE' => phpbb::$user->lang['NO_VALUE_OPTION'], 'EXPLAIN' => phpbb::$user->lang['NO_VALUE_OPTION_EXPLAIN'], 'FIELD' => $this->process_field_row('preview', $profile_row[1]))
		);

		return $options;
	}

	/**
	* Get date options for second step in ACP
	*/
	public function get_date_options()
	{
		global $lang_defs;

		$default_lang_id = $lang_defs['iso'][phpbb::$config['default_lang']];

		$profile_row = array(
			'var_name'				=> 'field_default_value',
			'lang_name'				=> $this->vars['lang_name'],
			'lang_explain'			=> $this->vars['lang_explain'],
			'lang_id'				=> $default_lang_id,
			'field_default_value'	=> $this->vars['field_default_value'],
			'field_ident'			=> 'field_default_value',
			'field_type'			=> FIELD_DATE,
			'field_length'			=> $this->vars['field_length']
		);

		$always_now = request_var('always_now', -1);
		if ($always_now == -1)
		{
			$s_checked = ($this->vars['field_default_value'] == 'now') ? true : false;
		}
		else
		{
			$s_checked = ($always_now) ? true : false;
		}

		$options = array(
			0 => array('TITLE' => phpbb::$user->lang['DEFAULT_VALUE'],	'FIELD' => $this->process_field_row('preview', $profile_row)),
			1 => array('TITLE' => phpbb::$user->lang['ALWAYS_TODAY'],	'FIELD' => '<label><input type="radio" class="radio" name="always_now" value="1"' . (($s_checked) ? ' checked="checked"' : '') . ' onchange="document.getElementById(\'add_profile_field\').submit();" /> ' . phpbb::$user->lang['YES'] . '</label><label><input type="radio" class="radio" name="always_now" value="0"' . ((!$s_checked) ? ' checked="checked"' : '') . ' onchange="document.getElementById(\'add_profile_field\').submit();" /> ' . phpbb::$user->lang['NO'] . '</label>'),
		);

		return $options;
	}
}

?>