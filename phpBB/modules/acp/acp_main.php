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
class acp_main
{
	var $u_action;

	function main($id, $mode)
	{
		// Show restore permissions notice
		if (phpbb::$user->data['user_perm_from'] && phpbb::$acl->acl_get('a_switchperm'))
		{
			$this->tpl_name = 'acp_main';
			$this->page_title = 'ACP_MAIN';

			$sql = 'SELECT user_id, username, user_colour
				FROM ' . USERS_TABLE . '
				WHERE user_id = ' . phpbb::$user->data['user_perm_from'];
			$result = phpbb::$db->sql_query($sql);
			$user_row = phpbb::$db->sql_fetchrow($result);
			phpbb::$db->sql_freeresult($result);

			$perm_from = '<strong' . (($user_row['user_colour']) ? ' style="color: #' . $user_row['user_colour'] . '">' : '>');
			$perm_from .= ($user_row['user_id'] != ANONYMOUS) ? '<a href="' . phpbb::$url->append_sid('memberlist', 'mode=viewprofile&amp;u=' . $user_row['user_id']) . '">' : '';
			$perm_from .= $user_row['username'];
			$perm_from .= ($user_row['user_id'] != ANONYMOUS) ? '</a>' : '';
			$perm_from .= '</strong>';

			phpbb::$template->assign_vars(array(
				'S_RESTORE_PERMISSIONS'		=> true,
				'U_RESTORE_PERMISSIONS'		=> phpbb::$url->append_sid('ucp', 'mode=restore_perm'),
				'PERM_FROM'					=> $perm_from,
				'L_PERMISSIONS_TRANSFERRED_EXPLAIN'	=> sprintf(phpbb::$user->lang['PERMISSIONS_TRANSFERRED_EXPLAIN'], $perm_from, phpbb::$url->append_sid('ucp', 'mode=restore_perm')),
			));

			return;
		}

		$action = request_var('action', '');

		if ($action)
		{
			if ($action === 'admlogout')
			{
				phpbb::$user->unset_admin();
				$redirect_url = phpbb::$url->append_sid(PHPBB_ROOT_PATH . 'index.' . PHP_EXT);
				phpbb::$url->meta_refresh(3, $redirect_url);
				trigger_error(phpbb::$user->lang['ADM_LOGGED_OUT'] . '<br /><br />' . sprintf(phpbb::$user->lang['RETURN_INDEX'], '<a href="' . $redirect_url . '">', '</a>'));
			}

			if (!confirm_box(true))
			{
				switch ($action)
				{
					case 'online':
						$confirm = true;
						$confirm_lang = 'RESET_ONLINE_CONFIRM';
					break;
					case 'stats':
						$confirm = true;
						$confirm_lang = 'RESYNC_STATS_CONFIRM';
					break;
					case 'user':
						$confirm = true;
						$confirm_lang = 'RESYNC_POSTCOUNTS_CONFIRM';
					break;
					case 'date':
						$confirm = true;
						$confirm_lang = 'RESET_DATE_CONFIRM';
					break;
					case 'db_track':
						$confirm = true;
						$confirm_lang = 'RESYNC_POST_MARKING_CONFIRM';
					break;
					case 'purge_cache':
						$confirm = true;
						$confirm_lang = 'PURGE_CACHE_CONFIRM';
					break;

					default:
						$confirm = true;
						$confirm_lang = 'CONFIRM_OPERATION';
				}

				if ($confirm)
				{
					confirm_box(false, phpbb::$user->lang[$confirm_lang], build_hidden_fields(array(
						'i'			=> $id,
						'mode'		=> $mode,
						'action'	=> $action,
					)));
				}
			}
			else
			{
				switch ($action)
				{

					case 'online':
						if (!phpbb::$acl->acl_get('a_board'))
						{
							trigger_error(phpbb::$user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						set_config('record_online_users', 1, true);
						set_config('record_online_date', time(), true);
						add_log('admin', 'LOG_RESET_ONLINE');
					break;

					case 'stats':
						if (!phpbb::$acl->acl_get('a_board'))
						{
							trigger_error(phpbb::$user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						$sql = 'SELECT COUNT(post_id) AS stat
							FROM ' . POSTS_TABLE . '
							WHERE post_approved = 1';
						$result = phpbb::$db->sql_query($sql);
						set_config('num_posts', (int) phpbb::$db->sql_fetchfield('stat'), true);
						phpbb::$db->sql_freeresult($result);

						$sql = 'SELECT COUNT(topic_id) AS stat
							FROM ' . TOPICS_TABLE . '
							WHERE topic_approved = 1';
						$result = phpbb::$db->sql_query($sql);
						set_config('num_topics', (int) phpbb::$db->sql_fetchfield('stat'), true);
						phpbb::$db->sql_freeresult($result);

						$sql = 'SELECT COUNT(user_id) AS stat
							FROM ' . USERS_TABLE . '
							WHERE user_type IN (' . phpbb::USER_NORMAL . ',' . phpbb::USER_FOUNDER . ')';
						$result = phpbb::$db->sql_query($sql);
						set_config('num_users', (int) phpbb::$db->sql_fetchfield('stat'), true);
						phpbb::$db->sql_freeresult($result);

						$sql = 'SELECT COUNT(attach_id) as stat
							FROM ' . ATTACHMENTS_TABLE . '
							WHERE is_orphan = 0';
						$result = phpbb::$db->sql_query($sql);
						set_config('num_files', (int) phpbb::$db->sql_fetchfield('stat'), true);
						phpbb::$db->sql_freeresult($result);

						$sql = 'SELECT SUM(filesize) as stat
							FROM ' . ATTACHMENTS_TABLE . '
							WHERE is_orphan = 0';
						$result = phpbb::$db->sql_query($sql);
						set_config('upload_dir_size', (float) phpbb::$db->sql_fetchfield('stat'), true);
						phpbb::$db->sql_freeresult($result);

						if (!function_exists('update_last_username'))
						{
							include(PHPBB_ROOT_PATH . 'includes/functions_user.' . PHP_EXT);
						}
						update_last_username();

						add_log('admin', 'LOG_RESYNC_STATS');
					break;

					case 'user':
						if (!phpbb::$acl->acl_get('a_board'))
						{
							trigger_error(phpbb::$user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						// Resync post counts
						$start = $max_post_id = 0;

						// Find the maximum post ID, we can only stop the cycle when we've reached it
						$sql = 'SELECT MAX(forum_last_post_id) as max_post_id
							FROM ' . FORUMS_TABLE;
						$result = phpbb::$db->sql_query($sql);
						$max_post_id = (int) phpbb::$db->sql_fetchfield('max_post_id');
						phpbb::$db->sql_freeresult($result);

						// No maximum post id? :o
						if (!$max_post_id)
						{
							$sql = 'SELECT MAX(post_id)
								FROM ' . POSTS_TABLE;
							$result = phpbb::$db->sql_query($sql);
							$max_post_id = (int) phpbb::$db->sql_fetchfield('max_post_id');
							phpbb::$db->sql_freeresult($result);
						}

						// Still no maximum post id? Then we are finished
						if (!$max_post_id)
						{
							add_log('admin', 'LOG_RESYNC_POSTCOUNTS');
							break;
						}

						$step = (phpbb::$config['num_posts']) ? (max((int) (phpbb::$config['num_posts'] / 5), 20000)) : 20000;
						phpbb::$db->sql_query('UPDATE ' . USERS_TABLE . ' SET user_posts = 0');

						while ($start < $max_post_id)
						{
							$sql = 'SELECT COUNT(post_id) AS num_posts, poster_id
								FROM ' . POSTS_TABLE . '
								WHERE post_id BETWEEN ' . ($start + 1) . ' AND ' . ($start + $step) . '
									AND post_postcount = 1 AND post_approved = 1
								GROUP BY poster_id';
							$result = phpbb::$db->sql_query($sql);

							if ($row = phpbb::$db->sql_fetchrow($result))
							{
								do
								{
									$sql = 'UPDATE ' . USERS_TABLE . " SET user_posts = user_posts + {$row['num_posts']} WHERE user_id = {$row['poster_id']}";
									phpbb::$db->sql_query($sql);
								}
								while ($row = phpbb::$db->sql_fetchrow($result));
							}
							phpbb::$db->sql_freeresult($result);

							$start += $step;
						}

						add_log('admin', 'LOG_RESYNC_POSTCOUNTS');

					break;

					case 'date':
						if (!phpbb::$acl->acl_get('a_board'))
						{
							trigger_error(phpbb::$user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						set_config('board_startdate', time() - 1);
						add_log('admin', 'LOG_RESET_DATE');
					break;

					case 'db_track':
						if (phpbb::$db->features['truncate'])
						{
							phpbb::$db->sql_query('TRUNCATE TABLE ' . TOPICS_POSTED_TABLE);
						}
						else
						{
							phpbb::$db->sql_query('DELETE FROM ' . TOPICS_POSTED_TABLE);
						}

						// This can get really nasty... therefore we only do the last six months
						$get_from_time = time() - (6 * 4 * 7 * 24 * 60 * 60);

						// Select forum ids, do not include categories
						$sql = 'SELECT forum_id
							FROM ' . FORUMS_TABLE . '
							WHERE forum_type <> ' . FORUM_CAT;
						$result = phpbb::$db->sql_query($sql);

						$forum_ids = array();
						while ($row = phpbb::$db->sql_fetchrow($result))
						{
							$forum_ids[] = $row['forum_id'];
						}
						phpbb::$db->sql_freeresult($result);

						// Any global announcements? ;)
						$forum_ids[] = 0;

						// Now go through the forums and get us some topics...
						foreach ($forum_ids as $forum_id)
						{
							$sql = 'SELECT p.poster_id, p.topic_id
								FROM ' . POSTS_TABLE . ' p, ' . TOPICS_TABLE . ' t
								WHERE t.forum_id = ' . $forum_id . '
									AND t.topic_moved_id = 0
									AND t.topic_last_post_time > ' . $get_from_time . '
									AND t.topic_id = p.topic_id
									AND p.poster_id <> ' . ANONYMOUS . '
								GROUP BY p.poster_id, p.topic_id';
							$result = phpbb::$db->sql_query($sql);

							$posted = array();
							while ($row = phpbb::$db->sql_fetchrow($result))
							{
								$posted[$row['poster_id']][] = $row['topic_id'];
							}
							phpbb::$db->sql_freeresult($result);

							$sql_ary = array();
							foreach ($posted as $user_id => $topic_row)
							{
								foreach ($topic_row as $topic_id)
								{
									$sql_ary[] = array(
										'user_id'		=> (int) $user_id,
										'topic_id'		=> (int) $topic_id,
										'topic_posted'	=> 1,
									);
								}
							}
							unset($posted);

							if (sizeof($sql_ary))
							{
								phpbb::$db->sql_multi_insert(TOPICS_POSTED_TABLE, $sql_ary);
							}
						}

						add_log('admin', 'LOG_RESYNC_POST_MARKING');
					break;

					case 'purge_cache':
						if (!phpbb::$user->is_founder)
						{
							trigger_error(phpbb::$user->lang['NO_AUTH_OPERATION'] . adm_back_link($this->u_action), E_USER_WARNING);
						}

						phpbb::$acm->purge();

						// Clear permissions
						phpbb::$acl->acl_clear_prefetch();
						cache_moderators();

						add_log('admin', 'LOG_PURGE_CACHE');
					break;
				}
			}
		}

		// Get forum statistics
		$total_posts = phpbb::$config['num_posts'];
		$total_topics = phpbb::$config['num_topics'];
		$total_users = phpbb::$config['num_users'];
		$total_files = phpbb::$config['num_files'];

		$start_date = phpbb::$user->format_date(phpbb::$config['board_startdate']);

		$boarddays = (time() - phpbb::$config['board_startdate']) / 86400;

		$posts_per_day = sprintf('%.2f', $total_posts / $boarddays);
		$topics_per_day = sprintf('%.2f', $total_topics / $boarddays);
		$users_per_day = sprintf('%.2f', $total_users / $boarddays);
		$files_per_day = sprintf('%.2f', $total_files / $boarddays);

		$upload_dir_size = get_formatted_filesize(phpbb::$config['upload_dir_size']);

		$avatar_dir_size = 0;

		if ($avatar_dir = @opendir(PHPBB_ROOT_PATH . phpbb::$config['avatar_path']))
		{
			while (($file = readdir($avatar_dir)) !== false)
			{
				if ($file[0] != '.' && $file != 'CVS' && strpos($file, 'index.') === false)
				{
					$avatar_dir_size += filesize(PHPBB_ROOT_PATH . phpbb::$config['avatar_path'] . '/' . $file);
				}
			}
			closedir($avatar_dir);

			$avatar_dir_size = get_formatted_filesize($avatar_dir_size);
		}
		else
		{
			// Couldn't open Avatar dir.
			$avatar_dir_size = phpbb::$user->lang['NOT_AVAILABLE'];
		}

		if ($posts_per_day > $total_posts)
		{
			$posts_per_day = $total_posts;
		}

		if ($topics_per_day > $total_topics)
		{
			$topics_per_day = $total_topics;
		}

		if ($users_per_day > $total_users)
		{
			$users_per_day = $total_users;
		}

		if ($files_per_day > $total_files)
		{
			$files_per_day = $total_files;
		}

		if (phpbb::$config['allow_attachments'] || phpbb::$config['allow_pm_attach'])
		{
			$sql = 'SELECT COUNT(attach_id) AS total_orphan
				FROM ' . ATTACHMENTS_TABLE . '
				WHERE is_orphan = 1
					AND filetime < ' . (time() - 3*60*60);
			$result = phpbb::$db->sql_query($sql);
			$total_orphan = (int) phpbb::$db->sql_fetchfield('total_orphan');
			phpbb::$db->sql_freeresult($result);
		}
		else
		{
			$total_orphan = false;
		}

		$dbsize = get_database_size();

		phpbb::$template->assign_vars(array(
			'TOTAL_POSTS'		=> $total_posts,
			'POSTS_PER_DAY'		=> $posts_per_day,
			'TOTAL_TOPICS'		=> $total_topics,
			'TOPICS_PER_DAY'	=> $topics_per_day,
			'TOTAL_USERS'		=> $total_users,
			'USERS_PER_DAY'		=> $users_per_day,
			'TOTAL_FILES'		=> $total_files,
			'FILES_PER_DAY'		=> $files_per_day,
			'START_DATE'		=> $start_date,
			'AVATAR_DIR_SIZE'	=> $avatar_dir_size,
			'DBSIZE'			=> $dbsize,
			'UPLOAD_DIR_SIZE'	=> $upload_dir_size,
			'TOTAL_ORPHAN'		=> $total_orphan,
			'S_TOTAL_ORPHAN'	=> ($total_orphan === false) ? false : true,
			'GZIP_COMPRESSION'	=> (phpbb::$config['gzip_compress']) ? phpbb::$user->lang['ON'] : phpbb::$user->lang['OFF'],
			'DATABASE_INFO'		=> phpbb::$db->sql_server_info(),
			'BOARD_VERSION'		=> phpbb::$config['version'],

			'U_ACTION'			=> $this->u_action,
			'U_ADMIN_LOG'		=> phpbb::$url->append_sid(PHPBB_ADMIN_PATH . 'index.' . PHP_EXT, 'i=logs&amp;mode=admin'),
			'U_INACTIVE_USERS'	=> phpbb::$url->append_sid(PHPBB_ADMIN_PATH . 'index.' . PHP_EXT, 'i=inactive&amp;mode=list'),

			'S_ACTION_OPTIONS'	=> (phpbb::$acl->acl_get('a_board')) ? true : false,
			'S_FOUNDER'			=> phpbb::$user->is_founder,
			)
		);

		$log_data = array();
		$log_count = 0;

		if (phpbb::$acl->acl_get('a_viewlogs'))
		{
			view_log('admin', $log_data, $log_count, 5);

			foreach ($log_data as $row)
			{
				phpbb::$template->assign_block_vars('log', array(
					'USERNAME'	=> $row['username_full'],
					'IP'		=> $row['ip'],
					'DATE'		=> phpbb::$user->format_date($row['time']),
					'ACTION'	=> $row['action'])
				);
			}
		}

		if (phpbb::$acl->acl_get('a_user'))
		{
			$inactive = array();
			$inactive_count = 0;

			view_inactive_users($inactive, $inactive_count, 10);

			foreach ($inactive as $row)
			{
				phpbb::$template->assign_block_vars('inactive', array(
					'INACTIVE_DATE'	=> phpbb::$user->format_date($row['user_inactive_time']),
					'JOINED'		=> phpbb::$user->format_date($row['user_regdate']),
					'LAST_VISIT'	=> (!$row['user_lastvisit']) ? ' - ' : phpbb::$user->format_date($row['user_lastvisit']),
					'REASON'		=> $row['inactive_reason'],
					'USER_ID'		=> $row['user_id'],
					'USERNAME'		=> $row['username'],
					'U_USER_ADMIN'	=> phpbb::$url->append_sid(PHPBB_ADMIN_PATH . 'index.' . PHP_EXT, "i=users&amp;mode=overview&amp;u={$row['user_id']}"))
				);
			}

			$option_ary = array('activate' => 'ACTIVATE', 'delete' => 'DELETE');
			if (phpbb::$config['email_enable'])
			{
				$option_ary += array('remind' => 'REMIND');
			}

			phpbb::$template->assign_vars(array(
				'S_INACTIVE_USERS'		=> true,
				'S_INACTIVE_OPTIONS'	=> build_select($option_ary))
			);
		}

		// Warn if install is still present
		if (file_exists(PHPBB_ROOT_PATH . 'install'))
		{
			phpbb::$template->assign_var('S_REMOVE_INSTALL', true);
		}

		if (!defined('PHPBB_DISABLE_CONFIG_CHECK') && file_exists(PHPBB_ROOT_PATH . 'config.' . PHP_EXT) && is_writable(PHPBB_ROOT_PATH . 'config.' . PHP_EXT))
		{
			// World-Writable? (000x)
			phpbb::$template->assign_var('S_WRITABLE_CONFIG', (bool) (@fileperms(PHPBB_ROOT_PATH . 'config.' . PHP_EXT) & 0x0002));
		}

		// Fill dbms version if not yet filled
		if (empty(phpbb::$config['dbms_version']))
		{
			set_config('dbms_version', phpbb::$db->sql_server_info(true));
		}

		$this->tpl_name = 'acp_main';
		$this->page_title = 'ACP_MAIN';
	}
}

?>
