<?php
/**
 * User and Authentication functions
 *
 * This file contains functions for working with users and authenticating them.
 * It also handles the internal mail messages, favorites, news/journal, and storage of MyGedView
 * customizations.  Assumes that a database connection has already been established.
 *
 * You can extend PhpGedView to work with other systems by implementing the functions in this file.
 * Other possible options are to use LDAP for authentication.
 *
 * phpGedView: Genealogy Viewer
 * Copyright (C) 2002 to 2010  PGV Development Team.  All rights reserved.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @package PhpGedView
 * @subpackage DB
 * @version $Id: authentication.php 6859 2010-01-26 21:15:03Z fisharebest $
 */

if (!defined('PGV_PHPGEDVIEW')) {
	header('HTTP/1.0 403 Forbidden');
	exit;
}

define('PGV_AUTHENTICATION_PHP', '');

/**
 * authenticate a username and password
 *
 * This function takes the given <var>$username</var> and <var>$password</var> and authenticates
 * them against the database.  The passwords are encrypted using the crypt() function.
 * The username is stored in the <var>$_SESSION["pgv_user"]</var> session variable.
 * @param string $user_name the username for the user attempting to login
 * @param string $password the plain text password to test
 * @param boolean $basic true if the userName and password were retrived via Basic HTTP authentication. Defaults to false. At this point, this is only used for logging
 * @return the user_id if sucessful, false otherwise
 */
function authenticateUser($user_name, $password, $basic=false) {
	// If we were already logged in, log out first
	if (getUserId()) {
		userLogout(getUserId());
	}

	if ($user_id=get_user_id($user_name)) {
		$dbpassword=get_user_password($user_id);
		if (crypt($password, $dbpassword)==$dbpassword) {
			if (get_user_setting($user_id, 'verified')=='yes' && get_user_setting($user_id, 'verified_by_admin')=='yes' || get_user_setting($user_id, 'canadmin')=='Y') {
				set_user_setting($user_id, 'loggedin', 'Y');
				//-- reset the user's session
				$_SESSION = array();
				$_SESSION['pgv_user'] = $user_id;
				AddToLog(($basic ? 'Basic HTTP Authentication' :'Login'). ' Successful');
				return $user_id;
			}
		}
	}
	AddToLog(($basic ? 'Basic HTTP Authentication' : 'Login').' Failed ->'.$user_name.'<-');
	return false;
}

/**
 * authenticate a username and password using Basic HTTP Authentication
 *
 * This function uses authenticateUser(), for authentication, but retrives the userName and password provided via basic auth.
 * @return bool return true if the user is already logged in or the basic HTTP auth username and password credentials match a user in the database return false if they don't
 * @TODO Security audit for this functionality
 * @TODO Do we really need a return value here?
 * @TODO should we reauthenticate the user even if already logged in?
 * @TODO do we need to set the user language and other jobs done in login.php? Should that loading be moved to a function called from the authenticateUser function?
 */
function basicHTTPAuthenticateUser() {
	global $pgv_lang;
	$user_id = getUserId();
	if (empty($user_id)){ //not logged in.
		if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW'])
				|| (! authenticateUser($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'], true))) {
			header('WWW-Authenticate: Basic realm="' . $pgv_lang["basic_realm"] . '"');
			header('HTTP/1.0 401 Unauthorized');
			echo $pgv_lang["basic_auth_failure"] ;
			exit;
		}
	} else { //already logged in or successful basic authentication
		return true; //probably not needed
	}
}

/**
 * logs a user out of the system
 * @param string $user_id	logout a specific user
 */
function userLogout($user_id) {
	set_user_setting($user_id, 'loggedin', 'N');

	AddToLog('Logout '.getUserName($user_id));

	// If we are logging ourself out, then end our session too.
	if (getUserId()==$user_id) {
		session_destroy();
	}
}

/**
 * Updates the login time in the database of the given user
 * The login time is used to automatically logout users who have been
 * inactive for the defined session time
 * @param string $username	the username to update the login info for
 */
function userUpdateLogin($user_id) {
	set_user_setting($user_id, 'sessiontime', time());
}

/**
 * get the current user's ID and Name
 *
 * Returns 0 and NULL if we are not logged in.
 *
 * If you want to embed PGV within a content management system, you would probably
 * rewrite these functions to extract the data from the parent system, and then
 * populate PGV's user/user_setting/user_gedcom_setting tables as appropriate.
 *
 */

function getUserId() {
	if (empty($_SESSION['pgv_user'])) {
		return 0;
	} else {
		return $_SESSION['pgv_user'];
	}
}

function getUserName() {
	if (getUserID()) {
		return get_user_name(getUserID());
	} else {
		return null;
	}
}

/**
 * check if given username is an admin
 */
function userIsAdmin($user_id=PGV_USER_ID) {
	if ($user_id) {
		return get_user_setting($user_id, 'canadmin')=='Y';
	} else {
		return false;
	}
}

/**
 * check if given username is an admin for the given gedcom
 */
function userGedcomAdmin($user_id=PGV_USER_ID, $ged_id=PGV_GED_ID) {
	if ($user_id) {
		return get_user_gedcom_setting($user_id, $ged_id, 'canedit')=='admin' || userIsAdmin($user_id, $ged_id);
	} else {
		return false;
	}
}

/**
 * check if the given user has access privileges on this gedcom
 */
function userCanAccess($user_id=PGV_USER_ID, $ged_id=PGV_GED_ID) {
	if ($user_id) {
		if (userIsAdmin($user_id)) {
			return true;
		} else {
			$tmp=get_user_gedcom_setting($user_id, $ged_id, 'canedit');
			return $tmp=='admin' || $tmp=='accept' || $tmp=='edit' || $tmp=='access';
		}
	} else {
		return false;
	}
}

/**
 * check if the given user has write privileges for the given gedcom
 */
function userCanEdit($user_id=PGV_USER_ID, $ged_id=PGV_GED_ID) {
	global $ALLOW_EDIT_GEDCOM;

	if ($ALLOW_EDIT_GEDCOM && $user_id) {
		if (userIsAdmin($user_id)) {
			return true;
		} else {
			$tmp=get_user_gedcom_setting($user_id, $ged_id, 'canedit');
			return $tmp=='admin' || $tmp=='accept' || $tmp=='edit';
		}
	} else {
		return false;
	}
}

/**
 * check if the given user can accept changes for the given gedcom
 *
 * takes a username and checks if the user has write privileges to
 * change the gedcom data and accept changes
 * @param string $username	the username of the user check privileges
 * @return boolean true if user can accept false if user cannot accept
 */
function userCanAccept($user_id=PGV_USER_ID, $ged_id=PGV_GED_ID) {
	global $ALLOW_EDIT_GEDCOM;

	if ($user_id) {
		if ($ALLOW_EDIT_GEDCOM) {
			$tmp=get_user_gedcom_setting($user_id, $ged_id, 'canedit');
			return $tmp=='admin' || $tmp=='accept';
		} else {
			// If we've disabled editing, an admin can still accept pending edits.
			return userGedcomAdmin($user_id, $ged_id);
		}
	} else {
		return false;
	}
}

/**
 * Should user's changed automatically be accepted
 */
function userAutoAccept($user_id=PGV_USER_ID) {
	if ($user_id) {
		return get_user_setting($user_id, 'auto_accept')=='Y';
	} else {
		return false;
	}
}

/**
 * Does an admin user exist?  Used to redirect to install/config page
 * during initial setup.
 */
function adminUserExists() {
	return admin_user_exists();
}

// Get the full name for a user
function getUserFullName($user_id) {
	global $TBLPREFIX, $NAME_REVERSE;

	$sql=
		"SELECT setting_value FROM {$TBLPREFIX}user_setting".
		"	WHERE user_id=? AND setting_name IN (?,?)".
		" ORDER BY setting_name ".($NAME_REVERSE ? 'DESC' : 'ASC');

	return implode(' ', PGV_DB::prepare($sql)->execute(array($user_id, 'firstname', 'lastname'))->fetchOneColumn());
}

// Get the root person for this gedcom
function getUserRootId($user_id, $ged_id) {
	if ($user_id) {
		return get_user_gedcom_setting(PGV_USER_ID, PGV_GED_ID, 'rootid');
	} else {
		return getUserGedcomId($user_id, $ged_id);
	}
}

// Get the user's ID in the given gedcom
function getUserGedcomId($user_id, $ged_id) {
	if ($user_id) {
		return get_user_gedcom_setting(PGV_USER_ID, PGV_GED_ID, 'gedcomid');
	} else {
		return null;
	}
}

/**
 * add a message into the log-file
 * @param string $LogString		the message to add
 * @param boolean $savelangerror
 * @return string returns the log line if successfully inserted into the log (used for CVS/SVN commit messages)
 */
function AddToLog($LogString, $savelangerror=false) {
	global $INDEX_DIRECTORY, $LOGFILE_CREATE, $argc;

	$wroteLogString = false;

	if ($LOGFILE_CREATE=='none') {
		return;
	}

	if (isset($_SERVER['REMOTE_ADDR'])) {
		$REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
	} elseif ($argc>1) {
		$REMOTE_ADDR = 'cli';
	}
	if ($LOGFILE_CREATE !== 'none' && $savelangerror === false) {
		if (empty($LOGFILE_CREATE))
			$LOGFILE_CREATE='daily';
		if ($LOGFILE_CREATE=='daily')
			$logfile = $INDEX_DIRECTORY.'pgv-' . date('Ymd') . '.log';
		if ($LOGFILE_CREATE=='weekly')
			$logfile = $INDEX_DIRECTORY.'pgv-' . date('Ym') . '-week' . date('W') . '.log';
		if ($LOGFILE_CREATE=='monthly')
			$logfile = $INDEX_DIRECTORY.'pgv-' . date('Ym') . '.log';
		if ($LOGFILE_CREATE=='yearly')
			$logfile = $INDEX_DIRECTORY.'pgv-' . date('Y') . '.log';
		if (is_writable($INDEX_DIRECTORY)) {
			$logline=date('d.m.Y H:i:s').' - '.$REMOTE_ADDR.' - '.(getUserId() ? getUserName() : 'Anonymous').' - '.$LogString.PGV_EOL;
			$fp = fopen($logfile, 'a');
			flock($fp, 2);
			fputs($fp, $logline);
			flock($fp, 3);
			fclose($fp);
			$wroteLogString = true;
		}
	}
	if ($wroteLogString)
		return $logline;
	else
		return '';
}

//----------------------------------- AddToSearchLog
//-- requires a string to add into the searchlog-file
function AddToSearchLog($LogString, $allgeds) {
	global $INDEX_DIRECTORY;

	if (empty($allgeds))
		return;

	$all_geds=get_all_gedcoms();

	//-- do not allow code to be written to the log file
	$LogString = preg_replace('/<\?.*\?>/', '*** CODE DETECTED ***', $LogString);

	foreach ($allgeds as $ged_id=>$ged_name) {
		if (count($all_geds)) {
			// If we have more than one gedcom, then need to load the settings
			require get_config_file($ged_id); // Note: load locally, not globally
		}
		switch ($SEARCHLOG_CREATE) {
		case 'none':
			continue 2;
		case 'yearly':
			$logfile=$INDEX_DIRECTORY.'srch-'.$ged_name.date('Y').'.log';
			break;
		case 'monthly':
			$logfile=$INDEX_DIRECTORY.'srch-'.$ged_name.date('Ym').'.log';
			break;
		case 'weekly':
			$logfile=$INDEX_DIRECTORY.'srch-'.$ged_name.date('Ym').'-week'.date('W').'.log';
			break;
		case 'daily':
		default:
			$logfile=$INDEX_DIRECTORY.'srch-'.$ged_name.date('Ymd').'.log';
			break;
		}
		if (is_writable($INDEX_DIRECTORY)) {
			$logline='Date / Time: '.date('d.m.Y H:i:s').' - IP: '.$_SERVER['REMOTE_ADDR'].' - User: '.PGV_USER_NAME.'<br />';
			if (count($allgeds)==count($all_geds)) {
				$logline.='Searchtype: Global<br />';
			} else {
				$logline.='Searchtype: Gedcom<br />';
			}
			$logline.=$LogString.'<br /><br />'.PGV_EOL;
			$fp=fopen($logfile, 'a');
			flock($fp, 2);
			fputs($fp, $logline);
			flock($fp, 3);
			fclose($fp);
		}
	}
}

//----------------------------------- AddToChangeLog
//-- requires a string to add into the changelog-file
function AddToChangeLog($LogString, $ged="") {
	global $INDEX_DIRECTORY, $CHANGELOG_CREATE, $GEDCOM, $username, $SEARCHLOG_CREATE;

	//-- do not allow code to be written to the log file
	$LogString = preg_replace('/<\?.*\?>/', "*** CODE DETECTED ***", $LogString);

	if (empty($ged))
		$ged = $GEDCOM;
	$oldged = $GEDCOM;
	$GEDCOM = $ged;
	if ($ged!=$oldged)
		include(get_config_file());
	if ($CHANGELOG_CREATE != "none") {
		$REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
		if (empty($CHANGELOG_CREATE))
			$CHANGELOG_CREATE="daily";
		if ($CHANGELOG_CREATE=="daily")
			$logfile = $INDEX_DIRECTORY."/ged-" . $GEDCOM . date("Ymd") . ".log";
		if ($CHANGELOG_CREATE=="weekly")
			$logfile = $INDEX_DIRECTORY."/ged-" . $GEDCOM . date("Ym") . "-week" . date("W") . ".log";
		if ($CHANGELOG_CREATE=="monthly")
			$logfile = $INDEX_DIRECTORY."/ged-" . $GEDCOM . date("Ym") . ".log";
		if ($CHANGELOG_CREATE=="yearly")
			$logfile = $INDEX_DIRECTORY."/ged-" . $GEDCOM . date("Y") . ".log";
		if (is_writable($INDEX_DIRECTORY)) {
			$logline = date("d.m.Y H:i:s") . " - " . $REMOTE_ADDR . " - " . $LogString . "\r\n";
			$fp = fopen($logfile, "a");
			flock($fp, 2);
			fputs($fp, $logline);
			flock($fp, 3);
			fclose($fp);
		}

	}
	$GEDCOM = $oldged;
	if ($ged!=$oldged)
		include(get_config_file());
}

//----------------------------------- addMessage
//-- stores a new message in the database
function addMessage($message) {
	global $TBLPREFIX, $CONTACT_METHOD, $pgv_lang,$CHARACTER_SET, $LANGUAGE, $PGV_STORE_MESSAGES, $SERVER_URL, $PGV_SIMPLE_MAIL, $WEBMASTER_EMAIL;
	global $TEXT_DIRECTION, $TEXT_DIRECTION_array, $DATE_FORMAT, $DATE_FORMAT_array, $TIME_FORMAT, $TIME_FORMAT_array, $WEEK_START, $WEEK_START_array;
	global $PHPGEDVIEW_EMAIL;

	//-- do not allow users to send a message to themselves
	if ($message["from"]==$message["to"]) {
		return false;
	}

	$user_id_from=get_user_id($message['from']);
	$user_id_to  =get_user_id($message['to']);

	require_once PGV_ROOT.'includes/functions/functions_mail.php';

	if (!$user_id_to) {
		//-- the to user must be a valid user in the system before it will send any mails
		return false;
	}

	// Switch to the "from" user's language
	$oldLanguage = $LANGUAGE;
	$from_lang=get_user_setting($user_id_from, 'language');
	if ($from_lang && $LANGUAGE!=$from_lang) {
		loadLanguage($from_lang, true);
	}

	//-- setup the message body for the "from" user
	$email2 = $message["body"];
	if (isset($message["from_name"]))
		$email2 = $pgv_lang["message_from_name"]." ".$message["from_name"]."\r\n".$pgv_lang["message_from"]." ".$message["from_email"]."\r\n\r\n".$email2;
	if (!empty($message["url"]))
		$email2 .= "\r\n\r\n--------------------------------------\r\n\r\n".$pgv_lang["viewing_url"]."\r\n".$SERVER_URL.$message["url"]."\r\n";
	$email2 .= "\r\n=--------------------------------------=\r\nIP ADDRESS: ".$_SERVER['REMOTE_ADDR']."\r\n";
	$email2 .= "DNS LOOKUP: ".gethostbyaddr($_SERVER['REMOTE_ADDR'])."\r\n";
	$email2 .= "LANGUAGE: $LANGUAGE\r\n";
	$subject2 = "[".$pgv_lang["phpgedview_message"].($TEXT_DIRECTION=="ltr"?"] ":" [").$message["subject"];
	$from ="";
	if (!$user_id_from) {
		$from = $message["from"];
		$email2 = $pgv_lang["message_email3"]."\r\n\r\n".$email2;
		$fromFullName = $message["from"];
	} else {
		$fromFullName = getUserFullName($user_id_from);
		if (!$PGV_SIMPLE_MAIL)
			$from = hex4email($fromFullName,$CHARACTER_SET). " <".get_user_setting($user_id_from, 'email').">";
		else
			$from = get_user_setting($user_id_from, 'email');
		$email2 = $pgv_lang["message_email2"]."\r\n\r\n".$email2;

	}
	if ($message["method"]!="messaging") {
		$subject1 = "[".$pgv_lang["phpgedview_message"].($TEXT_DIRECTION=="ltr"?"] ":" [").$message["subject"];
		if (!$user_id_from) {
			$email1 = $pgv_lang["message_email1"];
			if (!empty($message["from_name"])) {
				$email1 .= $message["from_name"]."\r\n\r\n".$message["body"];
			} else {
				$email1 .= $from."\r\n\r\n".$message["body"];
			}
		} else {
			$email1 = $pgv_lang["message_email1"];
			$email1 .= $fromFullName."\r\n\r\n".$message["body"];
		}
		if (!isset($message["no_from"])) {
			if (stristr($from, $PHPGEDVIEW_EMAIL)){
				$from = get_user_setting(get_user_id($WEBMASTER_EMAIL), 'email');
			}
			if (!$user_id_from) {
				$header2 = $PHPGEDVIEW_EMAIL;
			} elseif (isset($to)) {
				$header2 = $to;
			}
			if (!empty($header2)) {
				pgvMail($from, $header2, $subject2, $email2);
			}
		}
	}

	//-- Load the "to" users language
	$to_lang=get_user_setting($user_id_to, 'language');
	if ($to_lang && $LANGUAGE!=$to_lang) {
		loadLanguage($to_lang, true);
	}
	if (isset($message["from_name"]))
		$message["body"] = $pgv_lang["message_from_name"]." ".$message["from_name"]."\r\n".$pgv_lang["message_from"]." ".$message["from_email"]."\r\n\r\n".$message["body"];
	//-- [ phpgedview-Feature Requests-1588353 ] Supress admin IP address in Outgoing PGV Email
	if (!userIsAdmin($user_id_from)) {
		if (!empty($message["url"]))
			$message["body"] .= "\r\n\r\n--------------------------------------\r\n\r\n".$pgv_lang["viewing_url"]."\r\n".$SERVER_URL.$message["url"]."\r\n";
		$message["body"] .= "\r\n=--------------------------------------=\r\nIP ADDRESS: ".$_SERVER['REMOTE_ADDR']."\r\n";
		$message["body"] .= "DNS LOOKUP: ".gethostbyaddr($_SERVER['REMOTE_ADDR'])."\r\n";
		$message["body"] .= "LANGUAGE: $LANGUAGE\r\n";
	}
	if (empty($message["created"]))
		$message["created"] = gmdate ("D, d M Y H:i:s T");
	if ($PGV_STORE_MESSAGES && ($message["method"]!="messaging3" && $message["method"]!="mailto" && $message["method"]!="none")) {
		PGV_DB::prepare("INSERT INTO {$TBLPREFIX}messages (m_id, m_from, m_to, m_subject, m_body, m_created) VALUES (?, ? ,? ,? ,? ,?)")
			->execute(array(get_next_id("messages", "m_id"), $message["from"], $message["to"], $message["subject"], $message["body"], $message["created"]));
	}
	if ($message["method"]!="messaging") {
		$subject1 = "[".$pgv_lang["phpgedview_message"].($TEXT_DIRECTION=="ltr"?"] ":" [").$message["subject"];
		if (!$user_id_from) {
			$email1 = $pgv_lang["message_email1"];
			if (!empty($message["from_name"]))
				$email1 .= $message["from_name"]."\r\n\r\n".$message["body"];
			else
				$email1 .= $from."\r\n\r\n".$message["body"];
		} else {
			$email1 = $pgv_lang["message_email1"];
			$email1 .= $fromFullName."\r\n\r\n".$message["body"];
		}
		if (!$user_id_to) {
			//-- the to user must be a valid user in the system before it will send any mails
			return false;
		} else {
			$toFullName=getUserFullName($user_id_to);
			if (!$PGV_SIMPLE_MAIL)
				$to = hex4email($toFullName, $CHARACTER_SET). " <".get_user_setting($user_id_to, 'email').">";
			else
				$to = get_user_setting($user_id_to, 'email');
		}
		if (get_user_setting($user_id_to, 'email'))
			pgvMail($to, $from, $subject1, $email1);
	}

	if ($LANGUAGE!=$oldLanguage)
		loadLanguage($oldLanguage, true);			// restore language settings if needed

	return true;
}

//----------------------------------- deleteMessage
//-- deletes a message in the database
function deleteMessage($message_id) {
	global $TBLPREFIX;

	return (bool)PGV_DB::prepare("DELETE FROM {$TBLPREFIX}messages WHERE m_id=?")->execute(array($message_id));
}

//----------------------------------- getUserMessages
//-- Return an array of a users messages
function getUserMessages($username) {
	global $TBLPREFIX;

	$rows=
		PGV_DB::prepare("SELECT * FROM {$TBLPREFIX}messages WHERE m_to=? ORDER BY m_id DESC")
		->execute(array($username))
		->fetchAll();

	$messages=array();
	foreach ($rows as $row) {
		$messages[]=array(
			"id"=>$row->m_id,
			"to"=>$row->m_to,
			"from"=>$row->m_from,
			"subject"=>$row->m_subject,
			"body"=>$row->m_body,
			"created"=>$row->m_created
		);
	}
	return $messages;
}

/**
 * stores a new favorite in the database
 * @param array $favorite	the favorite array of the favorite to add
 */
function addFavorite($favorite) {
	global $TBLPREFIX;

	// -- make sure a favorite is added
	if (empty($favorite["gid"]) && empty($favorite["url"]))
		return false;

	//-- make sure this is not a duplicate entry
	$sql = "SELECT 1 FROM {$TBLPREFIX}favorites WHERE";
	if (!empty($favorite["gid"])) {
		$sql.=" fv_gid=?";
		$vars=array($favorite["gid"]);
	} else {
		$sql.=" fv_url=?";
		$vars=array($favorite["url"]);
	}
	$sql.=" AND fv_file=? AND fv_username=?";
	$vars[]=$favorite["file"];
	$vars[]=$favorite["username"];

	if (PGV_DB::prepare($sql)->execute($vars)->fetchOne()) {
		return false;
	}

	//-- add the favorite to the database
	return (bool)
		PGV_DB::prepare("INSERT INTO {$TBLPREFIX}favorites (fv_id, fv_username, fv_gid, fv_type, fv_file, fv_url, fv_title, fv_note) VALUES (?, ? ,? ,? ,? ,? ,? ,?)")
			->execute(array(get_next_id("favorites", "fv_id"), $favorite["username"], $favorite["gid"], $favorite["type"], $favorite["file"], $favorite["url"], $favorite["title"], $favorite["note"]));
}

/**
 * deleteFavorite
 * deletes a favorite in the database
 * @param int $fv_id	the id of the favorite to delete
 */
function deleteFavorite($fv_id) {
	global $TBLPREFIX;

	return (bool)
		PGV_DB::prepare("DELETE FROM {$TBLPREFIX}favorites WHERE fv_id=?")
		->execute(array($fv_id));
}

/**
 * Get a user's favorites
 * Return an array of a users messages
 * @param string $username		the username to get the favorites for
 */
function getUserFavorites($username) {
	global $TBLPREFIX;

	$rows=
		PGV_DB::prepare("SELECT * FROM {$TBLPREFIX}favorites WHERE fv_username=?")
		->execute(array($username))
		->fetchAll();

	$favorites = array();
	foreach ($rows as $row) {
		if (get_id_from_gedcom($row->fv_file)) { // If gedcom exists
			$favorites[]=array(
				"id"=>$row->fv_id,
				"username"=>$row->fv_username,
				"gid"=>$row->fv_gid,
				"type"=>$row->fv_type,
				"file"=>$row->fv_file,
				"title"=>$row->fv_title,
				"note"=>$row->fv_note,
				"url"=>$row->fv_url
			);
		}
	}
	return $favorites;
}

/**
 * get blocks for the given username
 *
 * retrieve the block configuration for the given user
 * if no blocks have been set yet, and the username is a valid user (not a gedcom) then try and load
 * the defaultuser blocks.
 * @param string $username	the username or gedcom name for the blocks
 * @return array	an array of the blocks.  The two main indexes in the array are "main" and "right"
 */
function getBlocks($username) {
	global $TBLPREFIX;

	$blocks = array();
	$blocks["main"] = array();
	$blocks["right"] = array();

	$rows=
		PGV_DB::prepare("SELECT * FROM {$TBLPREFIX}blocks WHERE b_username=? ORDER BY b_location, b_order")
		->execute(array($username))
		->fetchAll();

	if ($rows) {
		foreach ($rows as $row) {
			if (!isset($row->b_config))
				$row->b_config="";
			if ($row->b_location=="main")
				$blocks["main"][$row->b_order] = array($row->b_name, @unserialize($row->b_config));
			if ($row->b_location=="right")
				$blocks["right"][$row->b_order] = array($row->b_name, @unserialize($row->b_config));
		}
	} else {
		if (get_user_id($username)) {
			//-- if no blocks found, check for a default block setting
			//$rows=
				PGV_DB::prepare("SELECT * FROM {$TBLPREFIX}blocks WHERE b_username=? ORDER BY b_location, b_order")
				->execute(array('defaultuser'))
				->fetchAll();

			foreach ($rows as $row) {
				if (!isset($row->b_config))
					$row->b_config="";
				if ($row->b_location=="main")
					$blocks["main"][$row->b_order] = array($row->b_name, @unserialize($row->b_config));
				if ($row->b_location=="right")
					$blocks["right"][$row->b_order] = array($row->b_name, @unserialize($row->b_config));
			}
		}
	}
	return $blocks;
}

/**
 * Set Blocks
 *
 * Sets the blocks for a gedcom or user portal
 * the $setdefault parameter tells the program to also store these blocks as the blocks used by default
 * @param String $username the username or gedcom name to update the blocks for
 * @param array $ublocks the new blocks to set for the user or gedcom
 * @param boolean $setdefault	if true tells the program to also set these blocks as the blocks for the defaultuser
 */
function setBlocks($username, $ublocks, $setdefault=false) {
	global $TBLPREFIX;

	PGV_DB::prepare("DELETE FROM {$TBLPREFIX}blocks WHERE b_username=? AND b_name!=?")
		->execute(array($username, 'faq'));

	if ($setdefault) {
		PGV_DB::prepare("DELETE FROM {$TBLPREFIX}blocks WHERE b_username=?")
			->execute(array('defaultuser'));
	}

	$statement=PGV_DB::prepare("INSERT INTO {$TBLPREFIX}blocks (b_id, b_username, b_location, b_order, b_name, b_config) VALUES (?, ?, ?, ?, ?, ?)");

	foreach($ublocks["main"] as $order=>$block) {
		$statement->execute(array(get_next_id("blocks", "b_id"), $username, 'main', $order, $block[0], serialize($block[1])));

		if ($setdefault) {
			$statement->execute(array(get_next_id("blocks", "b_id"), 'defaultuser', 'main', $order, $block[0], serialize($block[1])));
		}
	}
	foreach($ublocks["right"] as $order=>$block) {
		$statement->execute(array(get_next_id("blocks", "b_id"), $username, 'right', $order, $block[0], serialize($block[1])));

		if ($setdefault) {
			$statement->execute(array(get_next_id("blocks", "b_id"), 'defaultuser', 'right', $order, $block[0], serialize($block[1])));
		}
	}
}

/**
 * Adds a news item to the database
 *
 * This function adds a news item represented by the $news array to the database.
 * If the $news array has an ["id"] field then the function assumes that it is
 * as update of an older news item.
 *
 * @author John Finlay
 * @param array $news a news item array
 */
function addNews($news) {
	global $TBLPREFIX;

	if (!isset($news["date"]))
		$news["date"] = client_time();
	if (!empty($news["id"])) {
		// In case news items are added from usermigrate, it will also contain an ID.
		// So we check first if the ID exists in the database. If not, insert instead of update.
		$exists=
			PGV_DB::prepare("SELECT 1 FROM {$TBLPREFIX}news where n_id=?")
			->execute(array($news["id"]))
			->fetchOne();

		if (!$exists) {
			return (bool)
				PGV_DB::prepare("INSERT INTO {$TBLPREFIX}news (n_id, n_username, n_date, n_title, n_text) VALUES (?, ? ,? ,? ,?)")
				->execute(array($news["id"], $news["username"], $news["date"], $news["title"], $news["text"]));
		} else {
			return (bool)
				PGV_DB::prepare("UPDATE {$TBLPREFIX}news SET n_date=?, n_title=? , n_text=? WHERE n_id=?")
				->execute(array($news["date"], $news["title"], $news["text"], $news["id"]));
		}
	} else {
		return (bool)
			PGV_DB::prepare("INSERT INTO {$TBLPREFIX}news (n_id, n_username, n_date, n_title, n_text) VALUES (?, ? ,? ,? ,?)")
			->execute(array(get_next_id("news", "n_id"), $news["username"], $news["date"], $news["title"], $news["text"]));
	}
}

/**
 * Deletes a news item from the database
 *
 * @author John Finlay
 * @param int $news_id the id number of the news item to delete
 */
function deleteNews($news_id) {
	global $TBLPREFIX;

	return (bool)PGV_DB::prepare("DELETE FROM {$TBLPREFIX}news WHERE n_id=?")->execute(array($news_id));
}

/**
 * Gets the news items for the given user or gedcom
 *
 * @param String $username the username or gedcom file name to get news items for
 */
function getUserNews($username) {
	global $TBLPREFIX;

	$rows=
		PGV_DB::prepare("SELECT * FROM {$TBLPREFIX}news WHERE n_username=? ORDER BY n_date DESC")
		->execute(array($username))
		->fetchAll();

	$news=array();
	foreach ($rows as $row) {
		$news[$row->n_id]=array(
			"id"=>$row->n_id,
			"username"=>$row->n_username,
			"date"=>$row->n_date,
			"title"=>$row->n_title,
			"text"=>$row->n_text,
			"anchor"=>"article".$row->n_id
		);
	}
	return $news;
}

/**
 * Gets the news item for the given news id
 *
 * @param int $news_id the id of the news entry to get
 */
function getNewsItem($news_id) {
	global $TBLPREFIX;

	$row=
		PGV_DB::prepare("SELECT * FROM {$TBLPREFIX}news WHERE n_id=?")
		->execute(array($news_id))
		->fetchOneRow();

	if ($row) {
		return array(
			"id"=>$row->n_id,
			"username"=>$row->n_username,
			"date"=>$row->n_date,
			"title"=>$row->n_title,
			"text"=>$row->n_text,
			"anchor"=>"article".$row->n_id
		);
	} else {
		return null;
	}
}

?>
