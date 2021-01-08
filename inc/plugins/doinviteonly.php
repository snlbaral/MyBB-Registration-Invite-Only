<?php
//disallow unauthorize access
if(!defined("IN_MYBB")) {
	die("You are not authorize to view this");
}

$plugins->add_hook('member_register_agreement', 'doinviteonly_start');
$plugins->add_hook('member_register_start', 'doinviteonly_start');
$plugins->add_hook('member_do_register_end','doinviteonly_end');
$plugins->add_hook('usercp_start', 'doinviteonly_usercp');

//Plugin Information
function doinviteonly_info()
{
	return array(
		'name' => 'MyBB Registration Invite Only',
		'author' => 'Sunil Baral',
		'website' => 'https://github.com/snlbaral',
		'description' => 'This plugin allows register only by invite',
		'version' => '1.0',
		'compatibility' => '18*',
		'guid' => '',
	);
}


//Plugin Installation
function doinviteonly_install()
{
	global $db;
	$collation = $db->build_create_table_collation();
	if (!$db->table_exists('doinviteonly')) {
        switch ($db->type) {
            case 'pgsql':
                $db->write_query(
                    "CREATE TABLE " . TABLE_PREFIX . "doinviteonly(
                        id serial,
                        uid int NOT NULL,
                        username varchar(100) NOT NULL,
                        invitecode varchar(255) NOT NULL DEFAULT '',
                        used_by varchar(100) NOT NULL DEFAULT 'NONE',
                        codeused int(10) NOT NULL DEFAULT '0',
                        dateline timestamp NOT NULL,
                        PRIMARY KEY (id)
                    );"
                );
                break;
            default:
                $db->write_query(
                    "CREATE TABLE " . TABLE_PREFIX . "doinviteonly(
                        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                        `uid` int(10) unsigned NOT NULL,
                        `username` varchar(100) NOT NULL,
                        `invitecode` varchar(255) NOT NULL DEFAULT '',
                        `used_by` varchar(100) NOT NULL DEFAULT 'NONE',                        
                        `codeused` int(10) NOT NULL DEFAULT '0',                    
                        `dateline` datetime NOT NULL,
                        PRIMARY KEY (`id`)
                    ) ENGINE=MyISAM{$collation};"
                );
                break;
        }
	}
}


function doinviteonly_is_installed()
{
	global $db;
	return $db->table_exists('doinviteonly');
}

//Plugin Uninstall
function doinviteonly_uninstall()
{
	global $db;
	if ($db->table_exists('doinviteonly')) {
		$db->drop_table('doinviteonly');
	}
}



//Activate Plugin
function doinviteonly_activate()
{
	global $db, $mybb, $settings;

	//Admin CP Settings
	$doinviteonly_group = array(
		'gid' => '',
		'name' => 'doinviteonly',
		'title' => 'MyBB Registration Invite Only',
		'description' => 'Settings for MyBB Registration Invite Only',
		'disporder' => '1',
		'isdefault' =>  '0',
	);
	$db->insert_query('settinggroups',$doinviteonly_group);
	$gid = $db->insert_id();

	//Enable or Disable
	$doinviteonly_enable = array(
		'sid' => 'NULL',
		'name' => 'doinviteonly_enable',
		'title' => 'Do you want to enable this plugin?',
		'description' => 'If you set this option to yes, this plugin will start working.',
		'optionscode' => 'yesno',
		'value' => '1',
		'disporder' => 1,
		'gid' => intval($gid),
	);

	//Allowed User Group
	$doinviteonly_allowed_group = array(
		'sid' => 'NULL',
		'name' => 'doinviteonly_allowed_group',
		'title' => 'Which groups this plugin is enable for?',
		'description' => 'Add gid of group that will be able to use this plugin.',
		'optionscode' => 'groupselect',
		'value' => '3,4,6',
		'disporder' => 1,
		'gid' => intval($gid),
	);

	$db->insert_query('settings',$doinviteonly_enable);
	$db->insert_query('settings',$doinviteonly_allowed_group);
	rebuild_settings();


	$insert_temp = array(
		'tid' => NULL,
		'title' => 'doinviteonly_input',
		'template' => $db->escape_string('
<label><b>Invite Code:</b></label><br/><input type="text" style="background: #fff;padding:6px;border:1px solid #ccc;outline:0;width:30%;margin:10px 0px" name="invitecode" autocomplete="off" required><br/>
			'),
		'sid' => '-1',
		'version' => $mybb->version_code,
		'dateline' => time(),
	);
	$db->insert_query('templates',$insert_temp);

	$insert_temp = array(
		'tid' => NULL,
		'title' => 'doinviteonly_error',
		'template' => $db->escape_string('
<div class="red_alert">Invite Code Mismatch</div>
			'),
		'sid' => '-1',
		'version' => $mybb->version_code,
		'dateline' => time(),
	);
	$db->insert_query('templates',$insert_temp);

	$insert_temp = array(
		'tid' => NULL,
		'title' => 'doinviteonly_do_input',
		'template' => $db->escape_string('
<input type="hidden" value="{$invitecode}" name="invitecode"> 
			'),
		'sid' => '-1',
		'version' => $mybb->version_code,
		'dateline' => time(),
	);
	$db->insert_query('templates',$insert_temp);

	$insert_temp = array(
		'tid' => NULL,
		'title' => 'doinviteonly_usercp_link',
		'template' => $db->escape_string('
<br />
<form action="usercp.php" method="post">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>Generate Invite Code</strong></td>
</tr>
<tr>
<td style="padding:20px">
<div align="center">
<input type="hidden" name="action" value="generate_invitecode" />
<input type="submit" class="button" name="submit" value="Generate" />
</div>
</td>
</tr>
</table>
</form>
			'),
		'sid' => '-1',
		'version' => $mybb->version_code,
		'dateline' => time(),
	);
	$db->insert_query('templates',$insert_temp);


	$insert_temp = array(
		'tid' => NULL,
		'title' => 'doinviteonly_usercp_list',
		'template' => $db->escape_string('
<br />
<table border="1" cellspacing="0" cellpadding="10" class="tborder" style="border-collapse:collapse">
<tr>
<td class="thead" colspan="3"><strong>Available Invite Code</strong></td>
</tr>
<tr>
	<th>Invite Code</th>
	<th>Generated On</th>
	<th>Action</th>
</tr>
{$invitecode_delete}
</table>
			'),
		'sid' => '-1',
		'version' => $mybb->version_code,
		'dateline' => time(),
	);
	$db->insert_query('templates',$insert_temp);

	$insert_temp = array(
		'tid' => NULL,
		'title' => 'doinviteonly_usercp_list_row',
		'template' => $db->escape_string('
<br />
<form action="usercp.php" method="post">
<input type="hidden" name="invitecode" value="{$invitecodelist}">
<tr>
	<td>{$invitecodelist}</td>
	<td>{$invitecode_generated}</td>
	<input type="hidden" name="action" value="delete_invitecode" />
	<td><input type="submit" class="button" name="submit" value="Delete"></td>
</tr>
</form>
			'),
		'sid' => '-1',
		'version' => $mybb->version_code,
		'dateline' => time(),
	);
	$db->insert_query('templates',$insert_temp);


	//Activate in template
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("member_register_agreement", "#" . preg_quote("<div align=\"center\">") . "#i", "<div align=\"center\">\r\n
        {\$doinviteonly_input}");
    find_replace_templatesets("member_register_agreement", "#" . preg_quote("{\$coppa_agreement}") . "#i", "{\$coppa_agreement}\r\n
        {\$doinviteonly_error}");
    find_replace_templatesets("member_register", "#" . preg_quote("{\$regerrors}") . "#i", "{\$regerrors}\r\n
        {\$doinviteonly_do_input}");
    find_replace_templatesets("usercp", "#" . preg_quote("{\$user_notepad}") . "#i", "{\$user_notepad}\r\n
        {\$doinviteonly_usercp_link}");
    find_replace_templatesets("usercp", "#" . preg_quote("{\$doinviteonly_usercp_link}") . "#i", "{\$doinviteonly_usercp_link}\r\n
        {\$doinviteonly_usercp_list}");
}

//Deactivate Plugin
function doinviteonly_deactivate()
{
	global $db, $mybb, $settings;
	$db->query("DELETE from ".TABLE_PREFIX."settinggroups WHERE name IN ('doinviteonly')");
	$db->query("DELETE from ".TABLE_PREFIX."settings WHERE name IN ('doinviteonly_enable')");
	$db->query("DELETE from ".TABLE_PREFIX."templates WHERE title LIKE 'doinviteonly%' AND sid='-1'");
	rebuild_settings();

	//Deactive from template
    include MYBB_ROOT . "/inc/adminfunctions_templates.php";
    find_replace_templatesets("member_register_agreement", "#" . preg_quote("\r\n
        {\$doinviteonly_input}") . "#i", "", 0);
    find_replace_templatesets("member_register_agreement", "#" . preg_quote("\r\n
        {\$doinviteonly_error}") . "#i", "", 0);
    find_replace_templatesets("member_register", "#" . preg_quote("\r\n
        {\$doinviteonly_do_input}") . "#i", "", 0);
    find_replace_templatesets("usercp", "#" . preg_quote("\r\n
        {\$doinviteonly_usercp_link}") . "#i", "", 0);
    find_replace_templatesets("usercp", "#" . preg_quote("\r\n
        {\$doinviteonly_usercp_list}") . "#i", "", 0);

}

function doinviteonly_start()
{
	global $db, $mybb, $templates, $settings, $doinviteonly_input, $doinviteonly_error,
	$doinviteonly_do_input;

    if($settings['doinviteonly_enable'] != 1)
    {
        return;
    }

    if($mybb->input['action']=="register") {

	    //Add Input Field
	    $stuff = $templates->get('doinviteonly_input');
		eval("\$doinviteonly_input = \"".$stuff."\";");
		//Error MSG
		if(!isset($mybb->input['invitecode']) && $mybb->input['error']==1) {
			$err_msg = $templates->get('doinviteonly_error');
			eval("\$doinviteonly_error = \"".$err_msg."\";");		
		}

	    if(isset($mybb->input['invitecode'])) {
	    	//check on db if invite code exist
	    	$invitecode = $db->escape_string($mybb->input['invitecode']);
	    	$check_query = $db->simple_select("doinviteonly","*","invitecode='".$invitecode."' AND codeused='0'");
	    	if($db->num_rows($check_query)<1) {
	    		header("Location: member.php?action=register&error=1");
	    	} else {
				eval("\$doinviteonly_do_input = \"".$templates->get('doinviteonly_do_input')."\";");
	    		return;
	    	}

	    }

	}
    
}

function doinviteonly_end()
{
	global $db, $mybb, $templates, $settings;

	$invitecode = $db->escape_string($mybb->input['invitecode']);
	$update_array = array(
		'codeused' => 1,
		'used_by' => $db->escape_string($mybb->get_input('username')),
	);
	$db->update_query('doinviteonly',$update_array,"invitecode='$invitecode'");
}


function doinviteonly_usercp()
{
	global $db, $mybb, $templates, $settings, $doinviteonly_usercp_link,
	$doinviteonly_usercp_list, $doinviteonly_usercp_list_row, $invitecodelist, $invitecode_delete;
	
	$allowed_group = explode(',', $mybb->settings['doinviteonly_allowed_group']);
	$usergroup = $mybb->user['usergroup'];

	if($usergroup!=1 && (in_array($usergroup, $allowed_group) || in_array('-1', $allowed_group))) {

		eval("\$doinviteonly_usercp_link = \"".$templates->get('doinviteonly_usercp_link')."\";");
		$uid = $mybb->user['uid'];
		$uid = (int)$uid;

		$check_invitecode = $db->query("SELECT * from mybb_doinviteonly WHERE uid='$uid' AND codeused='0'");
		if($db->num_rows($check_invitecode)>0) {
			while($row=$db->fetch_array($check_invitecode)) {
				$invitecodelist = $row['invitecode'];
				$invitecode_generated = $row['dateline'];
			    eval("\$invitecode_delete .= \"".$templates->get("doinviteonly_usercp_list_row")."\";");
			}
			eval("\$doinviteonly_usercp_list = \"".$templates->get('doinviteonly_usercp_list')."\";");
		}

		if($mybb->input['action']=="generate_invitecode") {
			$username = $mybb->user['username'];
			$uid = $mybb->user['uid'];
			$dateline = date("Y:m:d H:i:s");
			function generateInviteCode($length = 10 ) {
			return substr(str_shuffle(str_repeat($x= '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' , ceil($length/strlen($x)) )), 1 ,$length);
			}
			$newinvitecode = generateInviteCode();

			$insert_array = array(
				'uid' => (int)$uid,
				'username' => $db->escape_string($username),
				'invitecode' => $db->escape_string($newinvitecode),
				'dateline' => $dateline,
			);
			$stm = $db->insert_query("doinviteonly",$insert_array);
			header("Location: usercp.php");
		}

		if($mybb->input['action']=="delete_invitecode") {
			$uid = (int)$mybb->user['uid'];
			$invitecode = $db->escape_string($mybb->input['invitecode']);
			$stm = $db->delete_query("doinviteonly","invitecode='$invitecode' AND uid='$uid'");
			if($stm) {
				header("Location: usercp.php");
			}
		}

	}
}
