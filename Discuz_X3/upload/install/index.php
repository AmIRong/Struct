<?php

/**
 *      [Discuz!] (C)2001-2099 Comsenz Inc.
 *      This is NOT a freeware, use is subject to license terms
 *
 *      $Id: index.php 22348 2011-05-04 01:16:02Z monkey $
 */

error_reporting(E_ERROR | E_WARNING | E_PARSE);
@set_time_limit(1000);
@set_magic_quotes_runtime(0);

define('IN_DISCUZ', TRUE);
define('IN_COMSENZ', TRUE);
define('ROOT_PATH', dirname(__FILE__).'/../');

require ROOT_PATH.'./source/discuz_version.php';
require ROOT_PATH.'./install/include/install_var.php';
if(function_exists('mysql_connect')) {
	require ROOT_PATH.'./install/include/install_mysql.php';
} else {
	require ROOT_PATH.'./install/include/install_mysqli.php';
}
require ROOT_PATH.'./install/include/install_function.php';
require ROOT_PATH.'./install/include/install_lang.php';

$view_off = getgpc('view_off');

define('VIEW_OFF', $view_off ? TRUE : FALSE);

$allow_method = array('show_license', 'env_check', 'app_reg', 'db_init', 'ext_info', 'install_check', 'tablepre_check');

$step = intval(getgpc('step', 'R')) ? intval(getgpc('step', 'R')) : 0;
$method = getgpc('method');

if(empty($method) || !in_array($method, $allow_method)) {
	$method = isset($allow_method[$step]) ? $allow_method[$step] : '';
}

if(empty($method)) {
	show_msg('method_undefined', $method, 0);
}

if(file_exists($lockfile) && $method != 'ext_info') {
	show_msg('install_locked', '', 0);
} elseif(!class_exists('dbstuff')) {
	show_msg('database_nonexistence', '', 0);
}

timezone_set();

$uchidden = getgpc('uchidden');

if(in_array($method, array('app_reg', 'ext_info'))) {
	$isHTTPS = ($_SERVER['HTTPS'] && strtolower($_SERVER['HTTPS']) != 'off') ? true : false;
	$PHP_SELF = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
	$bbserver = 'http'.($isHTTPS ? 's' : '').'://'.preg_replace("/\:\d+/", '', $_SERVER['HTTP_HOST']).($_SERVER['SERVER_PORT'] && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443 ? ':'.$_SERVER['SERVER_PORT'] : '');
	$default_ucapi = $bbserver.'/ucenter';
	$default_appurl = $bbserver.substr($PHP_SELF, 0, strrpos($PHP_SELF, '/') - 8);
}

if($method == 'show_license') {

	transfer_ucinfo($_POST);
	show_license();

} elseif($method == 'env_check') {

	VIEW_OFF && function_check($func_items);

	env_check($env_items);

	dirfile_check($dirfile_items);

	show_env_result($env_items, $dirfile_items, $func_items, $filesock_items);

} elseif($method == 'app_reg') {

	@include ROOT_PATH.CONFIG;
	@include ROOT_PATH.CONFIG_UC;
	if(!defined('UC_API')) {
		define('UC_API', '');
	}
	if(getgpc('install_ucenter') == 'yes') {
		header("Location: index.php?step=3&install_ucenter=yes");
		die;
	}
	$submit = true;
	$error_msg = array();
	if(isset($form_app_reg_items) && is_array($form_app_reg_items)) {
		foreach($form_app_reg_items as $key => $items) {
			$$key = getgpc($key, 'p');
			if(!isset($$key) || !is_array($$key)) {
				$submit = false;
				break;
			}
			foreach($items as $k => $v) {
				$tmp = $$key;
				$$k = $tmp[$k];
				if(empty($$k) || !preg_match($v['reg'], $$k)) {
					if(empty($$k) && !$v['required']) {
						continue;
					}
					$submit = false;
					VIEW_OFF or $error_msg[$key][$k] = 1;
				}
			}
		}
	} else {
		$submit = false;
	}

 	$ucapi = defined('UC_API') && UC_API ? UC_API : $default_ucapi;

	if($submit) {

		$app_type = 'DISCUZX'; // Only For Discuz!

		$app_name = $sitename ? $sitename : SOFT_NAME;
		$app_url = $siteurl ? $siteurl : $default_appurl;

		$ucapi = $ucurl ? $ucurl : (defined('UC_API') && UC_API ? UC_API : $default_ucapi);
		$ucip = isset($ucip) ? $ucip : '';
		$ucfounderpw = $ucpw;
		$app_tagtemplates = 'apptagtemplates[template]='.urlencode('<a href="{url}" target="_blank">{subject}</a>').'&'.
		'apptagtemplates[fields][subject]='.urlencode($lang['tagtemplates_subject']).'&'.
		'apptagtemplates[fields][uid]='.urlencode($lang['tagtemplates_uid']).'&'.
		'apptagtemplates[fields][username]='.urlencode($lang['tagtemplates_username']).'&'.
		'apptagtemplates[fields][dateline]='.urlencode($lang['tagtemplates_dateline']).'&'.
		'apptagtemplates[fields][url]='.urlencode($lang['tagtemplates_url']);

		$ucapi = preg_replace("/\/$/", '', trim($ucapi));
		if(empty($ucapi) || !preg_match("/^(http:\/\/)/i", $ucapi)) {
			show_msg('uc_url_invalid', $ucapi, 0);
		} else {
			if(!$ucip) {
				$temp = @parse_url($ucapi);
				$ucip = gethostbyname($temp['host']);
				if(ip2long($ucip) == -1 || ip2long($ucip) === FALSE) {
					show_msg('uc_dns_error', $ucapi, 0);
				}
			}
		}
		include_once ROOT_PATH.'./uc_client/client.php';

		$ucinfo = dfopen($ucapi.'/index.php?m=app&a=ucinfo&release='.UC_CLIENT_RELEASE, 500, '', '', 1, $ucip);
		list($status, $ucversion, $ucrelease, $uccharset, $ucdbcharset, $apptypes) = explode('|', $ucinfo);
		if($status != 'UC_STATUS_OK') {
			show_msg('uc_url_unreachable', $ucapi, 0);
		} else {
			$dbcharset = strtolower($dbcharset ? str_replace('-', '', $dbcharset) : $dbcharset);
			$ucdbcharset = strtolower($ucdbcharset ? str_replace('-', '', $ucdbcharset) : $ucdbcharset);
			if(UC_CLIENT_VERSION > $ucversion) {
				show_msg('uc_version_incorrect', $ucversion, 0);
			} elseif($dbcharset && $ucdbcharset != $dbcharset) {
				show_msg('uc_dbcharset_incorrect', '', 0);
			}

			$postdata = "m=app&a=add&ucfounder=&ucfounderpw=".urlencode($ucpw)."&apptype=".urlencode($app_type)."&appname=".urlencode($app_name)."&appurl=".urlencode($app_url)."&appip=&appcharset=".CHARSET.'&appdbcharset='.DBCHARSET.'&'.$app_tagtemplates.'&release='.UC_CLIENT_RELEASE;
			$ucconfig = dfopen($ucapi.'/index.php', 500, $postdata, '', 1, $ucip);
			if(empty($ucconfig)) {
				show_msg('uc_api_add_app_error', $ucapi, 0);
			} elseif($ucconfig == '-1') {
				show_msg('uc_admin_invalid', '', 0);
			} else {
				list($appauthkey, $appid) = explode('|', $ucconfig);
				$ucconfig_array = explode('|', $ucconfig);
				$ucconfig_array[] = $ucapi;
				$ucconfig_array[] = $ucip;
				if(empty($appauthkey) || empty($appid)) {
					show_msg('uc_data_invalid', '', 0);
				} elseif($succeed = save_uc_config($ucconfig_array, ROOT_PATH.CONFIG_UC)) {
					if(VIEW_OFF) {
						show_msg('app_reg_success');
					} else {
						$step = $step + 1;
						header("Location: index.php?step=$step");
						exit;
					}
				} else {
					show_msg('config_unwriteable', '', 0);
				}
			}
		}

	}
	if(VIEW_OFF) {

		show_msg('missing_parameter', '', 0);

	} else {

		show_form($form_app_reg_items, $error_msg);

	}

}