<?php
/**
*
* @package ucp
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
* ucp_groups
* @package ucp
*/
class ucp_groups
{
	var $u_action;

	function main($id, $mode)
	{
		phpbb::$user->add_lang('groups');

		$return_page = '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '">', '</a>');

		$mark_ary	= request_var('mark', array(0));
		$submit		= phpbb_request::variable('submit', false, false, phpbb_request::POST);
		$delete		= phpbb_request::variable('delete', false, false, phpbb_request::POST);
		$error = $data = array();

		switch ($mode)
		{
			case 'membership':

				$this->page_title = 'UCP_USERGROUPS_MEMBER';

				if ($submit || phpbb_request::is_set_post('change_default'))
				{
					$action = (phpbb_request::is_set_post('change_default')) ? 'change_default' : request_var('action', '');
					$group_id = ($action == 'change_default') ? request_var('default', 0) : request_var('selected', 0);

					if (!$group_id)
					{
						trigger_error('NO_GROUP_SELECTED');
					}

					$sql = 'SELECT group_id, group_name, group_type
						FROM ' . GROUPS_TABLE . '
						WHERE group_id IN (' . $group_id . ', ' . phpbb::$user->data['group_id'] . ')';
					$result = phpbb::$db->sql_query($sql);

					$group_row = array();
					while ($row = phpbb::$db->sql_fetchrow($result))
					{
						$row['group_name'] = ($row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $row['group_name']] : $row['group_name'];
						$group_row[$row['group_id']] = $row;
					}
					phpbb::$db->sql_freeresult($result);

					if (!sizeof($group_row))
					{
						trigger_error('GROUP_NOT_EXIST');
					}

					switch ($action)
					{
						case 'change_default':
							// User already having this group set as default?
							if ($group_id == phpbb::$user->data['group_id'])
							{
								trigger_error(phpbb::$user->lang['ALREADY_DEFAULT_GROUP'] . $return_page);
							}

							if (!phpbb::$acl->acl_get('u_chggrp'))
							{
								trigger_error(phpbb::$user->lang['NOT_AUTHORISED'] . $return_page);
							}

							// User needs to be member of the group in order to make it default
							if (!group_memberships($group_id, phpbb::$user->data['user_id'], true))
							{
								trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
							}

							if (confirm_box(true))
							{
								group_user_attributes('default', $group_id, phpbb::$user->data['user_id']);

								add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_GROUP_CHANGE', sprintf(phpbb::$user->lang['USER_GROUP_CHANGE'], $group_row[phpbb::$user->data['group_id']]['group_name'], $group_row[$group_id]['group_name']));

								meta_refresh(3, $this->u_action);
								trigger_error(phpbb::$user->lang['CHANGED_DEFAULT_GROUP'] . $return_page);
							}
							else
							{
								$s_hidden_fields = array(
									'default'		=> $group_id,
									'change_default'=> true
								);

								confirm_box(false, sprintf(phpbb::$user->lang['GROUP_CHANGE_DEFAULT'], $group_row[$group_id]['group_name']), build_hidden_fields($s_hidden_fields));
							}

						break;

						case 'resign':

							// User tries to resign from default group but is not allowed to change it?
							if ($group_id == phpbb::$user->data['group_id'] && !phpbb::$acl->acl_get('u_chggrp'))
							{
								trigger_error(phpbb::$user->lang['NOT_RESIGN_FROM_DEFAULT_GROUP'] . $return_page);
							}

							if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
							{
								trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
							}
							list(, $row) = each($row);

							$sql = 'SELECT group_type
								FROM ' . GROUPS_TABLE . '
								WHERE group_id = ' . $group_id;
							$result = phpbb::$db->sql_query($sql);
							$group_type = (int) phpbb::$db->sql_fetchfield('group_type');
							phpbb::$db->sql_freeresult($result);

							if ($group_type != GROUP_OPEN && $group_type != GROUP_FREE)
							{
								trigger_error(phpbb::$user->lang['CANNOT_RESIGN_GROUP'] . $return_page);
							}

							if (confirm_box(true))
							{
								group_user_del($group_id, phpbb::$user->data['user_id']);

								add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_GROUP_RESIGN', $group_row[$group_id]['group_name']);

								meta_refresh(3, $this->u_action);
								trigger_error(phpbb::$user->lang[($row['user_pending']) ? 'GROUP_RESIGNED_PENDING' : 'GROUP_RESIGNED_MEMBERSHIP'] . $return_page);
							}
							else
							{
								$s_hidden_fields = array(
									'selected'		=> $group_id,
									'action'		=> 'resign',
									'submit'		=> true
								);

								confirm_box(false, ($row['user_pending']) ? 'GROUP_RESIGN_PENDING' : 'GROUP_RESIGN_MEMBERSHIP', build_hidden_fields($s_hidden_fields));
							}

						break;

						case 'join':

							$sql = 'SELECT ug.*, u.username, u.username_clean, u.user_email
								FROM ' . USER_GROUP_TABLE . ' ug, ' . USERS_TABLE . ' u
								WHERE ug.user_id = u.user_id
									AND ug.group_id = ' . $group_id . '
									AND ug.user_id = ' . phpbb::$user->data['user_id'];
							$result = phpbb::$db->sql_query($sql);
							$row = phpbb::$db->sql_fetchrow($result);
							phpbb::$db->sql_freeresult($result);

							if ($row)
							{
								if ($row['user_pending'])
								{
									trigger_error(phpbb::$user->lang['ALREADY_IN_GROUP_PENDING'] . $return_page);
								}

								trigger_error(phpbb::$user->lang['ALREADY_IN_GROUP'] . $return_page);
							}

							// Check permission to join (open group or request)
							if ($group_row[$group_id]['group_type'] != GROUP_OPEN && $group_row[$group_id]['group_type'] != GROUP_FREE)
							{
								trigger_error(phpbb::$user->lang['CANNOT_JOIN_GROUP'] . $return_page);
							}

							if (confirm_box(true))
							{
								if ($group_row[$group_id]['group_type'] == GROUP_FREE)
								{
									group_user_add($group_id, phpbb::$user->data['user_id']);

									$email_template = 'group_added';
								}
								else
								{
									group_user_add($group_id, phpbb::$user->data['user_id'], false, false, false, 0, 1);

									$email_template = 'group_request';
								}

								include_once(PHPBB_ROOT_PATH . 'includes/functions_messenger.' . PHP_EXT);
								$messenger = new messenger();

								$sql = 'SELECT u.username, u.username_clean, u.user_email, u.user_notify_type, u.user_jabber, u.user_lang
									FROM ' . USER_GROUP_TABLE . ' ug, ' . USERS_TABLE . ' u
									WHERE ug.user_id = u.user_id
										AND ' . (($group_row[$group_id]['group_type'] == GROUP_FREE) ? 'ug.user_id = ' . phpbb::$user->data['user_id'] : 'ug.group_leader = 1') . "
										AND ug.group_id = $group_id";
								$result = phpbb::$db->sql_query($sql);

								while ($row = phpbb::$db->sql_fetchrow($result))
								{
									$messenger->template($email_template, $row['user_lang']);

									$messenger->to($row['user_email'], $row['username']);
									$messenger->im($row['user_jabber'], $row['username']);

									$messenger->assign_vars(array(
										'USERNAME'			=> htmlspecialchars_decode($row['username']),
										'GROUP_NAME'		=> htmlspecialchars_decode($group_row[$group_id]['group_name']),
										'REQUEST_USERNAME'	=> phpbb::$user->data['username'],

										'U_PENDING'		=> generate_board_url() . '/ucp.' . PHP_EXT . "?i=groups&mode=manage&action=list&g=$group_id",
										'U_GROUP'		=> generate_board_url() . '/memberlist.' . PHP_EXT . "?mode=group&g=$group_id")
									);

									$messenger->send($row['user_notify_type']);
								}
								phpbb::$db->sql_freeresult($result);

								$messenger->save_queue();

								add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_GROUP_JOIN' . (($group_row[$group_id]['group_type'] == GROUP_FREE) ? '' : '_PENDING'), $group_row[$group_id]['group_name']);

								meta_refresh(3, $this->u_action);
								trigger_error(phpbb::$user->lang[($group_row[$group_id]['group_type'] == GROUP_FREE) ? 'GROUP_JOINED' : 'GROUP_JOINED_PENDING'] . $return_page);
							}
							else
							{
								$s_hidden_fields = array(
									'selected'		=> $group_id,
									'action'		=> 'join',
									'submit'		=> true
								);

								confirm_box(false, ($group_row[$group_id]['group_type'] == GROUP_FREE) ? 'GROUP_JOIN' : 'GROUP_JOIN_PENDING', build_hidden_fields($s_hidden_fields));
							}

						break;

						case 'demote':

							if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
							{
								trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
							}
							list(, $row) = each($row);

							if (!$row['group_leader'])
							{
								trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
							}

							if (confirm_box(true))
							{
								group_user_attributes('demote', $group_id, phpbb::$user->data['user_id']);

								add_log('user', phpbb::$user->data['user_id'], 'LOG_USER_GROUP_DEMOTE', $group_row[$group_id]['group_name']);

								meta_refresh(3, $this->u_action);
								trigger_error(phpbb::$user->lang['USER_GROUP_DEMOTED'] . $return_page);
							}
							else
							{
								$s_hidden_fields = array(
									'selected'		=> $group_id,
									'action'		=> 'demote',
									'submit'		=> true
								);

								confirm_box(false, 'USER_GROUP_DEMOTE', build_hidden_fields($s_hidden_fields));
							}

						break;
					}
				}

				$sql = 'SELECT g.*, ug.group_leader, ug.user_pending
					FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
					WHERE ug.user_id = ' . phpbb::$user->data['user_id'] . '
						AND g.group_id = ug.group_id
					ORDER BY g.group_type DESC, g.group_name';
				$result = phpbb::$db->sql_query($sql);

				$group_id_ary = array();

				while ($row = phpbb::$db->sql_fetchrow($result))
				{
					$block = ($row['group_leader']) ? 'leader' : (($row['user_pending']) ? 'pending' : 'member');

					switch ($row['group_type'])
					{
						case GROUP_OPEN:
							$group_status = 'OPEN';
						break;

						case GROUP_CLOSED:
							$group_status = 'CLOSED';
						break;

						case GROUP_HIDDEN:
							$group_status = 'HIDDEN';
						break;

						case GROUP_SPECIAL:
							$group_status = 'SPECIAL';
						break;

						case GROUP_FREE:
							$group_status = 'FREE';
						break;
					}

					phpbb::$template->assign_block_vars($block, array(
						'GROUP_ID'		=> $row['group_id'],
						'GROUP_NAME'	=> ($row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $row['group_name']] : $row['group_name'],
						'GROUP_DESC'	=> ($row['group_type'] <> GROUP_SPECIAL) ? generate_text_for_display($row['group_desc'], $row['group_desc_uid'], $row['group_desc_bitfield'], $row['group_desc_options']) : phpbb::$user->lang['GROUP_IS_SPECIAL'],
						'GROUP_SPECIAL'	=> ($row['group_type'] <> GROUP_SPECIAL) ? false : true,
						'GROUP_STATUS'	=> phpbb::$user->lang['GROUP_IS_' . $group_status],
						'GROUP_COLOUR'	=> $row['group_colour'],

						'U_VIEW_GROUP'	=> append_sid('memberlist', 'mode=group&amp;g=' . $row['group_id']),

						'S_GROUP_DEFAULT'	=> ($row['group_id'] == phpbb::$user->data['group_id']) ? true : false,
					));

					$group_id_ary[] = (int) $row['group_id'];
				}
				phpbb::$db->sql_freeresult($result);

				// Hide hidden groups unless user is an admin with group privileges
				$sql_and = (phpbb::$acl->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? '<> ' . GROUP_SPECIAL : 'NOT IN (' . GROUP_SPECIAL . ', ' . GROUP_HIDDEN . ')';

				$sql = 'SELECT group_id, group_name, group_colour, group_desc, group_desc_uid, group_desc_bitfield, group_desc_options, group_type, group_founder_manage
					FROM ' . GROUPS_TABLE . '
					WHERE ' . ((sizeof($group_id_ary)) ? phpbb::$db->sql_in_set('group_id', $group_id_ary, true) . ' AND ' : '') . "
						group_type $sql_and
					ORDER BY group_type DESC, group_name";
				$result = phpbb::$db->sql_query($sql);

				while ($row = phpbb::$db->sql_fetchrow($result))
				{
					switch ($row['group_type'])
					{
						case GROUP_OPEN:
							$group_status = 'OPEN';
						break;

						case GROUP_CLOSED:
							$group_status = 'CLOSED';
						break;

						case GROUP_HIDDEN:
							$group_status = 'HIDDEN';
						break;

						case GROUP_SPECIAL:
							$group_status = 'SPECIAL';
						break;

						case GROUP_FREE:
							$group_status = 'FREE';
						break;
					}

					phpbb::$template->assign_block_vars('nonmember', array(
						'GROUP_ID'		=> $row['group_id'],
						'GROUP_NAME'	=> ($row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $row['group_name']] : $row['group_name'],
						'GROUP_DESC'	=> ($row['group_type'] <> GROUP_SPECIAL) ? generate_text_for_display($row['group_desc'], $row['group_desc_uid'], $row['group_desc_bitfield'], $row['group_desc_options']) : phpbb::$user->lang['GROUP_IS_SPECIAL'],
						'GROUP_SPECIAL'	=> ($row['group_type'] <> GROUP_SPECIAL) ? false : true,
						'GROUP_CLOSED'	=> ($row['group_type'] <> GROUP_CLOSED || phpbb::$acl->acl_gets('a_group', 'a_groupadd', 'a_groupdel')) ? false : true,
						'GROUP_STATUS'	=> phpbb::$user->lang['GROUP_IS_' . $group_status],
						'S_CAN_JOIN'	=> ($row['group_type'] == GROUP_OPEN || $row['group_type'] == GROUP_FREE) ? true : false,
						'GROUP_COLOUR'	=> $row['group_colour'],

						'U_VIEW_GROUP'	=> append_sid('memberlist', 'mode=group&amp;g=' . $row['group_id']),
					));
				}
				phpbb::$db->sql_freeresult($result);

				phpbb::$template->assign_vars(array(
					'S_CHANGE_DEFAULT'	=> (phpbb::$acl->acl_get('u_chggrp')) ? true : false,
					'S_LEADER_COUNT'	=> $leader_count,
					'S_MEMBER_COUNT'	=> $member_count,
					'S_PENDING_COUNT'	=> $pending_count,
					'S_NONMEMBER_COUNT'	=> $nonmember_count,

					'S_UCP_ACTION'			=> $this->u_action,
				));

			break;

			case 'manage':

				$this->page_title = 'UCP_USERGROUPS_MANAGE';
				$action		= (phpbb_request::is_set_post('addusers')) ? 'addusers' : request_var('action', '');
				$group_id	= request_var('g', 0);

				include(PHPBB_ROOT_PATH . 'includes/functions_display.' . PHP_EXT);

				add_form_key('ucp_groups');

				if ($group_id)
				{
					$sql = 'SELECT *
						FROM ' . GROUPS_TABLE . "
						WHERE group_id = $group_id";
					$result = phpbb::$db->sql_query($sql);
					$group_row = phpbb::$db->sql_fetchrow($result);
					phpbb::$db->sql_freeresult($result);

					if (!$group_row)
					{
						trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
					}

					// Check if the user is allowed to manage this group if set to founder only.
					if (!phpbb::$user->is_founder && $group_row['group_founder_manage'])
					{
						trigger_error(phpbb::$user->lang['NOT_ALLOWED_MANAGE_GROUP'] . $return_page, E_USER_WARNING);
					}

					$group_name = $group_row['group_name'];
					$group_type = $group_row['group_type'];

					$avatar_img = (!empty($group_row['group_avatar'])) ? get_user_avatar($group_row['group_avatar'], $group_row['group_avatar_type'], $group_row['group_avatar_width'], $group_row['group_avatar_height'], 'GROUP_AVATAR') : '<img src="' . PHPBB_ROOT_PATH . phpbb::$base_config['admin_folder'] . '/images/no_avatar.gif" alt="" />';

					phpbb::$template->assign_vars(array(
						'GROUP_NAME'			=> ($group_type == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $group_name] : $group_name,
						'GROUP_INTERNAL_NAME'	=> $group_name,
						'GROUP_COLOUR'			=> (isset($group_row['group_colour'])) ? $group_row['group_colour'] : '',
						'GROUP_DESC_DISP'		=> generate_text_for_display($group_row['group_desc'], $group_row['group_desc_uid'], $group_row['group_desc_bitfield'], $group_row['group_desc_options']),
						'GROUP_TYPE'			=> $group_row['group_type'],

						'AVATAR'				=> $avatar_img,
						'AVATAR_IMAGE'			=> $avatar_img,
						'AVATAR_WIDTH'			=> (isset($group_row['group_avatar_width'])) ? $group_row['group_avatar_width'] : '',
						'AVATAR_HEIGHT'			=> (isset($group_row['group_avatar_height'])) ? $group_row['group_avatar_height'] : '',
					));
				}

				switch ($action)
				{
					case 'edit':

						if (!$group_id)
						{
							trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
						}

						if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
						{
							trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
						}
						list(, $row) = each($row);

						if (!$row['group_leader'])
						{
							trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
						}

						$file_uploads = (@ini_get('file_uploads') || strtolower(@ini_get('file_uploads')) == 'on') ? true : false;
						phpbb::$user->add_lang(array('acp/groups', 'acp/common'));

						$data = $submit_ary = array();

						$update	= phpbb_request::is_set_post('update');

						$error = array();

						$avatar_select = basename(request_var('avatar_select', ''));
						$category = basename(request_var('category', ''));

						$can_upload = (file_exists(PHPBB_ROOT_PATH . phpbb::$config['avatar_path']) && @is_writable(PHPBB_ROOT_PATH . phpbb::$config['avatar_path']) && $file_uploads) ? true : false;

						// Did we submit?
						if ($update)
						{
							$group_name	= utf8_normalize_nfc(request_var('group_name', '', true));
							$group_desc = utf8_normalize_nfc(request_var('group_desc', '', true));
							$group_type	= request_var('group_type', GROUP_FREE);

							$allow_desc_bbcode	= request_var('desc_parse_bbcode', false);
							$allow_desc_urls	= request_var('desc_parse_urls', false);
							$allow_desc_smilies	= request_var('desc_parse_smilies', false);

							$submit_ary = array(
								'colour'		=> request_var('group_colour', ''),
								'rank'			=> request_var('group_rank', 0),
								'receive_pm'	=> phpbb_request::is_set('group_receive_pm') ? 1 : 0,
								'message_limit'	=> request_var('group_message_limit', 0),
								'max_recipients'=> request_var('group_max_recipients', 0),
							);

							$data['uploadurl']	= request_var('uploadurl', '');
							$data['remotelink'] = request_var('remotelink', '');
							$data['width']		= request_var('width', '');
							$data['height']		= request_var('height', '');
							$delete				= request_var('delete', '');

							if (!empty($_FILES['uploadfile']['tmp_name']) || $data['uploadurl'] || $data['remotelink'])
							{
								// Avatar stuff
								$var_ary = array(
									'uploadurl'		=> array('string', true, 5, 255),
									'remotelink'	=> array('string', true, 5, 255),
									'width'			=> array('string', true, 1, 3),
									'height'		=> array('string', true, 1, 3),
								);

								if (!($error = validate_data($data, $var_ary)))
								{
									$data['user_id'] = "g$group_id";

									if ((!empty($_FILES['uploadfile']['tmp_name']) || $data['uploadurl']) && $can_upload)
									{
										list($submit_ary['avatar_type'], $submit_ary['avatar'], $submit_ary['avatar_width'], $submit_ary['avatar_height']) = avatar_upload($data, $error);
									}
									else if ($data['remotelink'])
									{
										list($submit_ary['avatar_type'], $submit_ary['avatar'], $submit_ary['avatar_width'], $submit_ary['avatar_height']) = avatar_remote($data, $error);
									}
								}
							}
							else if ($avatar_select && phpbb::$config['allow_avatar_local'])
							{
								// check avatar gallery
								if (is_dir(PHPBB_ROOT_PATH . phpbb::$config['avatar_gallery_path'] . '/' . $category))
								{
									$submit_ary['avatar_type'] = AVATAR_GALLERY;

									list($submit_ary['avatar_width'], $submit_ary['avatar_height']) = getimagesize(PHPBB_ROOT_PATH . phpbb::$config['avatar_gallery_path'] . '/' . $category . '/' . $avatar_select);
									$submit_ary['avatar'] = $category . '/' . $avatar_select;
								}
							}
							else if ($delete)
							{
								$submit_ary['avatar'] = '';
								$submit_ary['avatar_type'] = $submit_ary['avatar_width'] = $submit_ary['avatar_height'] = 0;
							}
							else if ($data['width'] && $data['height'])
							{
								// Only update the dimensions?
								if (phpbb::$config['avatar_max_width'] || phpbb::$config['avatar_max_height'])
								{
									if ($data['width'] > phpbb::$config['avatar_max_width'] || $data['height'] > phpbb::$config['avatar_max_height'])
									{
										$error[] = sprintf(phpbb::$user->lang['AVATAR_WRONG_SIZE'], phpbb::$config['avatar_min_width'], phpbb::$config['avatar_min_height'], phpbb::$config['avatar_max_width'], phpbb::$config['avatar_max_height'], $data['width'], $data['height']);
									}
								}

								if (!sizeof($error))
								{
									if (phpbb::$config['avatar_min_width'] || phpbb::$config['avatar_min_height'])
									{
										if ($data['width'] < phpbb::$config['avatar_min_width'] || $data['height'] < phpbb::$config['avatar_min_height'])
										{
											$error[] = sprintf(phpbb::$user->lang['AVATAR_WRONG_SIZE'], phpbb::$config['avatar_min_width'], phpbb::$config['avatar_min_height'], phpbb::$config['avatar_max_width'], phpbb::$config['avatar_max_height'], $data['width'], $data['height']);
										}
									}
								}

								if (!sizeof($error))
								{
									$submit_ary['avatar_width'] = $data['width'];
									$submit_ary['avatar_height'] = $data['height'];
								}
							}

							if ((isset($submit_ary['avatar']) && $submit_ary['avatar'] && (!isset($group_row['group_avatar']))) || $delete)
							{
								if (isset($group_row['group_avatar']) && $group_row['group_avatar'])
								{
									avatar_delete('group', $group_row, true);
								}
							}

							if (!check_form_key('ucp_groups'))
							{
								$error[] = phpbb::$user->lang['FORM_INVALID'];
							}

							if (!sizeof($error))
							{
								// Only set the rank, colour, etc. if it's changed or if we're adding a new
								// group. This prevents existing group members being updated if no changes
								// were made.

								$group_attributes = array();
								$test_variables = array('rank', 'colour', 'avatar', 'avatar_type', 'avatar_width', 'avatar_height', 'receive_pm', 'legend', 'message_limit', 'max_recipients');
								foreach ($test_variables as $test)
								{
									if ($action == 'add' || (isset($submit_ary[$test]) && $group_row['group_' . $test] != $submit_ary[$test]))
									{
										$group_attributes['group_' . $test] = $group_row['group_' . $test] = $submit_ary[$test];
									}
								}

								if (!($error = group_create($group_id, $group_type, $group_name, $group_desc, $group_attributes, $allow_desc_bbcode, $allow_desc_urls, $allow_desc_smilies)))
								{
									phpbb::$acm->destroy_sql(GROUPS_TABLE);

									$message = ($action == 'edit') ? 'GROUP_UPDATED' : 'GROUP_CREATED';
									trigger_error(phpbb::$user->lang[$message] . $return_page);
								}
							}

							if (sizeof($error))
							{
								$group_rank = $submit_ary['rank'];

								$group_desc_data = array(
									'text'			=> $group_desc,
									'allow_bbcode'	=> $allow_desc_bbcode,
									'allow_smilies'	=> $allow_desc_smilies,
									'allow_urls'	=> $allow_desc_urls
								);
							}
						}
						else if (!$group_id)
						{
							$group_name = utf8_normalize_nfc(request_var('group_name', '', true));
							$group_desc_data = array(
								'text'			=> '',
								'allow_bbcode'	=> true,
								'allow_smilies'	=> true,
								'allow_urls'	=> true
							);
							$group_rank = 0;
							$group_type = GROUP_OPEN;
						}
						else
						{
							$group_desc_data = generate_text_for_edit($group_row['group_desc'], $group_row['group_desc_uid'], $group_row['group_desc_options']);
							$group_rank = $group_row['group_rank'];
						}

						$sql = 'SELECT *
							FROM ' . RANKS_TABLE . '
							WHERE rank_special = 1
							ORDER BY rank_title';
						$result = phpbb::$db->sql_query($sql);

						$rank_options = '<option value="0"' . ((!$group_rank) ? ' selected="selected"' : '') . '>' . phpbb::$user->lang['USER_DEFAULT'] . '</option>';
						while ($row = phpbb::$db->sql_fetchrow($result))
						{
							$selected = ($group_rank && $row['rank_id'] == $group_rank) ? ' selected="selected"' : '';
							$rank_options .= '<option value="' . $row['rank_id'] . '"' . $selected . '>' . $row['rank_title'] . '</option>';
						}
						phpbb::$db->sql_freeresult($result);

						$type_free		= ($group_type == GROUP_FREE) ? ' checked="checked"' : '';
						$type_open		= ($group_type == GROUP_OPEN) ? ' checked="checked"' : '';
						$type_closed	= ($group_type == GROUP_CLOSED) ? ' checked="checked"' : '';
						$type_hidden	= ($group_type == GROUP_HIDDEN) ? ' checked="checked"' : '';

						$display_gallery = phpbb_request::is_set_post('display_gallery');

						if (phpbb::$config['allow_avatar_local'] && $display_gallery)
						{
							avatar_gallery($category, $avatar_select, 4);
						}

						$avatars_enabled = ($can_upload || (phpbb::$config['allow_avatar_local'] || phpbb::$config['allow_avatar_remote'])) ? true : false;

						phpbb::$template->assign_vars(array(
							'S_EDIT'			=> true,
							'S_INCLUDE_SWATCH'	=> true,
							'S_CAN_UPLOAD'		=> $can_upload,
							'S_FORM_ENCTYPE'	=> ($can_upload) ? ' enctype="multipart/form-data"' : '',
							'S_ERROR'			=> (sizeof($error)) ? true : false,
							'S_SPECIAL_GROUP'	=> ($group_type == GROUP_SPECIAL) ? true : false,
							'S_AVATARS_ENABLED'	=> $avatars_enabled,
							'S_DISPLAY_GALLERY'	=> (phpbb::$config['allow_avatar_local'] && !$display_gallery) ? true : false,
							'S_IN_GALLERY'		=> (phpbb::$config['allow_avatar_local'] && $display_gallery) ? true : false,

							'ERROR_MSG'				=> (sizeof($error)) ? implode('<br />', $error) : '',
							'GROUP_RECEIVE_PM'		=> (isset($group_row['group_receive_pm']) && $group_row['group_receive_pm']) ? ' checked="checked"' : '',
							'GROUP_MESSAGE_LIMIT'	=> (isset($group_row['group_message_limit'])) ? $group_row['group_message_limit'] : 0,
							'GROUP_MAX_RECIPIENTS'	=> (isset($group_row['group_max_recipients'])) ? $group_row['group_max_recipients'] : 0,

							'GROUP_DESC'			=> $group_desc_data['text'],
							'S_DESC_BBCODE_CHECKED'	=> $group_desc_data['allow_bbcode'],
							'S_DESC_URLS_CHECKED'	=> $group_desc_data['allow_urls'],
							'S_DESC_SMILIES_CHECKED'=> $group_desc_data['allow_smilies'],

							'S_RANK_OPTIONS'		=> $rank_options,
							'AVATAR_MAX_FILESIZE'	=> phpbb::$config['avatar_filesize'],

							'GROUP_TYPE_FREE'		=> GROUP_FREE,
							'GROUP_TYPE_OPEN'		=> GROUP_OPEN,
							'GROUP_TYPE_CLOSED'		=> GROUP_CLOSED,
							'GROUP_TYPE_HIDDEN'		=> GROUP_HIDDEN,
							'GROUP_TYPE_SPECIAL'	=> GROUP_SPECIAL,

							'GROUP_FREE'		=> $type_free,
							'GROUP_OPEN'		=> $type_open,
							'GROUP_CLOSED'		=> $type_closed,
							'GROUP_HIDDEN'		=> $type_hidden,

							'U_SWATCH'			=> append_sid(phpbb::$base_config['admin_folder'] . '/swatch', 'form=ucp&amp;name=group_colour'),
							'S_UCP_ACTION'		=> $this->u_action . "&amp;action=$action&amp;g=$group_id",
							'L_AVATAR_EXPLAIN'	=> sprintf(phpbb::$user->lang['AVATAR_EXPLAIN'], phpbb::$config['avatar_max_width'], phpbb::$config['avatar_max_height'], phpbb::$config['avatar_filesize'] / 1024),
						));

					break;

					case 'list':

						if (!$group_id)
						{
							trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
						}

						if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
						{
							trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
						}
						list(, $row) = each($row);

						if (!$row['group_leader'])
						{
							trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
						}

						phpbb::$user->add_lang(array('acp/groups', 'acp/common'));
						$start = request_var('start', 0);

						// Grab the leaders - always, on every page...
						$sql = 'SELECT u.user_id, u.username, u.username_clean, u.user_colour, u.user_regdate, u.user_posts, u.group_id, ug.group_leader, ug.user_pending
							FROM ' . USERS_TABLE . ' u, ' . USER_GROUP_TABLE . " ug
							WHERE ug.group_id = $group_id
								AND u.user_id = ug.user_id
								AND ug.group_leader = 1
							ORDER BY ug.user_pending DESC, u.username_clean";
						$result = phpbb::$db->sql_query($sql);

						while ($row = phpbb::$db->sql_fetchrow($result))
						{
							phpbb::$template->assign_block_vars('leader', array(
								'USERNAME'			=> $row['username'],
								'USERNAME_COLOUR'	=> $row['user_colour'],
								'USERNAME_FULL'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
								'U_USER_VIEW'		=> get_username_string('profile', $row['user_id'], $row['username']),
								'S_GROUP_DEFAULT'	=> ($row['group_id'] == $group_id) ? true : false,
								'JOINED'			=> ($row['user_regdate']) ? phpbb::$user->format_date($row['user_regdate']) : ' - ',
								'USER_POSTS'		=> $row['user_posts'],
								'USER_ID'			=> $row['user_id'],
							));
						}
						phpbb::$db->sql_freeresult($result);

						// Total number of group members (non-leaders)
						$sql = 'SELECT COUNT(user_id) AS total_members
							FROM ' . USER_GROUP_TABLE . "
							WHERE group_id = $group_id
								AND group_leader = 0";
						$result = phpbb::$db->sql_query($sql);
						$total_members = (int) phpbb::$db->sql_fetchfield('total_members');
						phpbb::$db->sql_freeresult($result);

						// Grab the members
						$sql = 'SELECT u.user_id, u.username, u.username_clean, u.user_colour, u.user_regdate, u.user_posts, u.group_id, ug.group_leader, ug.user_pending
							FROM ' . USERS_TABLE . ' u, ' . USER_GROUP_TABLE . " ug
							WHERE ug.group_id = $group_id
								AND u.user_id = ug.user_id
								AND ug.group_leader = 0
							ORDER BY ug.user_pending DESC, u.username_clean";
						$result = phpbb::$db->sql_query_limit($sql, phpbb::$config['topics_per_page'], $start);

						$pending = false;
						$approved = false;

						while ($row = phpbb::$db->sql_fetchrow($result))
						{
							if ($row['user_pending'] && !$pending)
							{
								phpbb::$template->assign_block_vars('member', array(
									'S_PENDING'		=> true,
								));
								phpbb::$template->assign_var('S_PENDING_SET', true);

								$pending = true;
							}
							else if (!$row['user_pending'] && !$approved)
							{
								phpbb::$template->assign_block_vars('member', array(
									'S_APPROVED'		=> true,
								));
								phpbb::$template->assign_var('S_APPROVED_SET', true);

								$approved = true;
							}

							phpbb::$template->assign_block_vars('member', array(
								'USERNAME'			=> $row['username'],
								'USERNAME_COLOUR'	=> $row['user_colour'],
								'USERNAME_FULL'		=> get_username_string('full', $row['user_id'], $row['username'], $row['user_colour']),
								'U_USER_VIEW'		=> get_username_string('profile', $row['user_id'], $row['username']),
								'S_GROUP_DEFAULT'	=> ($row['group_id'] == $group_id) ? true : false,
								'JOINED'			=> ($row['user_regdate']) ? phpbb::$user->format_date($row['user_regdate']) : ' - ',
								'USER_POSTS'		=> $row['user_posts'],
								'USER_ID'			=> $row['user_id'],
							));
						}
						phpbb::$db->sql_freeresult($result);

						$s_action_options = '';
						$options = array('default' => 'DEFAULT', 'approve' => 'APPROVE', 'deleteusers' => 'DELETE');

						foreach ($options as $option => $lang)
						{
							$s_action_options .= '<option value="' . $option . '">' . phpbb::$user->lang['GROUP_' . $lang] . '</option>';
						}

						phpbb::$template->assign_vars(array(
							'S_LIST'			=> true,
							'S_ACTION_OPTIONS'	=> $s_action_options,
							'S_ON_PAGE'			=> on_page($total_members, phpbb::$config['topics_per_page'], $start),
							'PAGINATION'		=> generate_pagination($this->u_action . "&amp;action=$action&amp;g=$group_id", $total_members, phpbb::$config['topics_per_page'], $start),

							'U_ACTION'			=> $this->u_action . "&amp;g=$group_id",
							'U_FIND_USERNAME'	=> append_sid('memberlist', 'mode=searchuser&amp;form=ucp&amp;field=usernames'),
						));

					break;

					case 'approve':

						if (!$group_id)
						{
							trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
						}

						if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
						{
							trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
						}
						list(, $row) = each($row);

						if (!$row['group_leader'])
						{
							trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
						}

						phpbb::$user->add_lang('acp/groups');

						// Approve, demote or promote
						group_user_attributes('approve', $group_id, $mark_ary, false, false);

						trigger_error(phpbb::$user->lang['USERS_APPROVED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '&amp;action=list&amp;g=' . $group_id . '">', '</a>'));

					break;

					case 'default':

						if (!$group_id)
						{
							trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
						}

						if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
						{
							trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
						}
						list(, $row) = each($row);

						if (!$row['group_leader'])
						{
							trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
						}

						$group_row['group_name'] = ($group_row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $group_row['group_name']] : $group_row['group_name'];

						if (confirm_box(true))
						{
							if (!sizeof($mark_ary))
							{
								$start = 0;

								do
								{
									$sql = 'SELECT user_id
										FROM ' . USER_GROUP_TABLE . "
										WHERE group_id = $group_id
										ORDER BY user_id";
									$result = phpbb::$db->sql_query_limit($sql, 200, $start);

									$mark_ary = array();
									if ($row = phpbb::$db->sql_fetchrow($result))
									{
										do
										{
											$mark_ary[] = $row['user_id'];
										}
										while ($row = phpbb::$db->sql_fetchrow($result));

										group_user_attributes('default', $group_id, $mark_ary, false, $group_row['group_name'], $group_row);

										$start = (sizeof($mark_ary) < 200) ? 0 : $start + 200;
									}
									else
									{
										$start = 0;
									}
									phpbb::$db->sql_freeresult($result);
								}
								while ($start);
							}
							else
							{
								group_user_attributes('default', $group_id, $mark_ary, false, $group_row['group_name'], $group_row);
							}

							phpbb::$user->add_lang('acp/groups');

							trigger_error(phpbb::$user->lang['GROUP_DEFS_UPDATED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '&amp;action=list&amp;g=' . $group_id . '">', '</a>'));
						}
						else
						{
							phpbb::$user->add_lang('acp/common');

							confirm_box(false, phpbb::$user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
								'mark'		=> $mark_ary,
								'g'			=> $group_id,
								'i'			=> $id,
								'mode'		=> $mode,
								'action'	=> $action))
							);
						}

					break;

					case 'deleteusers':

						phpbb::$user->add_lang(array('acp/groups', 'acp/common'));

						if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
						{
							trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
						}
						list(, $row) = each($row);

						if (!$row['group_leader'])
						{
							trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
						}

						$group_row['group_name'] = ($group_row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $group_row['group_name']] : $group_row['group_name'];

						if (confirm_box(true))
						{
							if (!$group_id)
							{
								trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
							}

							$error = group_user_del($group_id, $mark_ary, false, $group_row['group_name']);

							if ($error)
							{
								trigger_error(phpbb::$user->lang[$error] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '&amp;action=list&amp;g=' . $group_id . '">', '</a>'));
							}

							trigger_error(phpbb::$user->lang['GROUP_USERS_REMOVE'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '&amp;action=list&amp;g=' . $group_id . '">', '</a>'));
						}
						else
						{
							confirm_box(false, phpbb::$user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
								'mark'		=> $mark_ary,
								'g'			=> $group_id,
								'i'			=> $id,
								'mode'		=> $mode,
								'action'	=> $action))
							);
						}

					break;

					case 'addusers':

						phpbb::$user->add_lang(array('acp/groups', 'acp/common'));

						$names = utf8_normalize_nfc(request_var('usernames', '', true));

						if (!$group_id)
						{
							trigger_error(phpbb::$user->lang['NO_GROUP'] . $return_page);
						}

						if (!$names)
						{
							trigger_error(phpbb::$user->lang['NO_USERS'] . $return_page);
						}

						if (!($row = group_memberships($group_id, phpbb::$user->data['user_id'])))
						{
							trigger_error(phpbb::$user->lang['NOT_MEMBER_OF_GROUP'] . $return_page);
						}
						list(, $row) = each($row);

						if (!$row['group_leader'])
						{
							trigger_error(phpbb::$user->lang['NOT_LEADER_OF_GROUP'] . $return_page);
						}

						$name_ary = array_unique(explode("\n", $names));
						$group_name = ($group_row['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $group_row['group_name']] : $group_row['group_name'];

						$default = request_var('default', 0);

						if (confirm_box(true))
						{
							// Add user/s to group
							if ($error = group_user_add($group_id, false, $name_ary, $group_name, $default, 0, 0, $group_row))
							{
								trigger_error(phpbb::$user->lang[$error] . $return_page);
							}

							trigger_error(phpbb::$user->lang['GROUP_USERS_ADDED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '&amp;action=list&amp;g=' . $group_id . '">', '</a>'));
						}
						else
						{
							$s_hidden_fields = array(
								'default'	=> $default,
								'usernames'	=> $names,
								'g'			=> $group_id,
								'i'			=> $id,
								'mode'		=> $mode,
								'action'	=> $action
							);
							confirm_box(false, sprintf(phpbb::$user->lang['GROUP_CONFIRM_ADD_USER' . ((sizeof($name_ary) == 1) ? '' : 'S')], implode(', ', $name_ary)), build_hidden_fields($s_hidden_fields));
						}

						trigger_error(phpbb::$user->lang['NO_USERS_ADDED'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_PAGE'], '<a href="' . $this->u_action . '&amp;action=list&amp;g=' . $group_id . '">', '</a>'));

					break;

					default:
						phpbb::$user->add_lang('acp/common');

						$sql = 'SELECT g.group_id, g.group_name, g.group_colour, g.group_desc, g.group_desc_uid, g.group_desc_bitfield, g.group_desc_options, g.group_type, ug.group_leader
							FROM ' . GROUPS_TABLE . ' g, ' . USER_GROUP_TABLE . ' ug
							WHERE ug.user_id = ' . phpbb::$user->data['user_id'] . '
								AND g.group_id = ug.group_id
								AND ug.group_leader = 1
							ORDER BY g.group_type DESC, g.group_name';
						$result = phpbb::$db->sql_query($sql);

						while ($value = phpbb::$db->sql_fetchrow($result))
						{
							phpbb::$template->assign_block_vars('leader', array(
								'GROUP_NAME'	=> ($value['group_type'] == GROUP_SPECIAL) ? phpbb::$user->lang['G_' . $value['group_name']] : $value['group_name'],
								'GROUP_DESC'	=> generate_text_for_display($value['group_desc'], $value['group_desc_uid'], $value['group_desc_bitfield'], $value['group_desc_options']),
								'GROUP_TYPE'	=> $value['group_type'],
								'GROUP_ID'		=> $value['group_id'],
								'GROUP_COLOUR'	=> $value['group_colour'],

								'U_LIST'	=> $this->u_action . "&amp;action=list&amp;g={$value['group_id']}",
								'U_EDIT'	=> $this->u_action . "&amp;action=edit&amp;g={$value['group_id']}",
							));
						}
						phpbb::$db->sql_freeresult($result);

					break;
				}

			break;
		}

		$this->tpl_name = 'ucp_groups_' . $mode;
	}
}

?>