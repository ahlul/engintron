<?php
/**
 * @version    1.7.0
 * @package    Engintron for cPanel/WHM
 * @author     Fotis Evangelou
 * @url        https://engintron.com
 * @copyright  Copyright (c) 2010 - 2016 Nuevvo Webware P.C. All rights reserved.
 * @license    GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 */

// Permissions check
$grantAccess = false;
$checkPrivileges = trim(shell_exec("if whmapi1 myprivs | grep -q 'all: 1'; then echo 'all'; fi;"));
$user = getenv('REMOTE_USER'); /* legacy check */
if($checkPrivileges=='all') $grantAccess = true;
if($user=="root") $grantAccess = true;
if($grantAccess === false){
	echo "You do not have sufficient permissions to access this page...";
	exit;
}

// *** Common variables to make updating easier ***
define('PLG_NAME', 'Engintron for cPanel/WHM');
define('PLG_NAME_SHORT', 'Engintron');
define('PLG_VERSION', '1.7.0');
define('NGINX_VERSION', trim(str_replace('nginx version: nginx/','',shell_exec('nginx -v 2>&1'))));
define('ENGINTRON_STATE', trim(file_get_contents("/usr/local/src/engintron/state.conf")));
define('CENTOS_RELEASE', trim(shell_exec('rpm -q --qf "%{VERSION}" $(rpm -q --whatprovides redhat-release)')));

// Get params
$op = $_GET['op'];
$f = $_GET['f'];
$s = $_GET['s'];

$allowed_files = array(
	'/etc/crontab',
	'/etc/nginx/nginx.conf',
	'/etc/nginx/proxy_params_common',
	'/etc/nginx/proxy_params_dynamic',
	'/etc/nginx/proxy_params_static',
	'/etc/nginx/custom_rules',
	'/etc/nginx/custom_rules.dist',
	'/etc/nginx/conf.d/default.conf',
	'/etc/my.cnf',
	'/usr/local/apache/conf/php.conf',
	'/usr/local/lib/php.ini'
);

// Common Commands
function execute($act) {

	switch($act){
		case "nginx_restart":
			$command = shell_exec("service nginx restart");
			if(version_compare(CENTOS_RELEASE, '7', '>=')) {
				$output = "<p>Nginx restarted.</p>";
			} else {
				if(empty($command)) {
					$output = "<p>Something went wrong, check the Nginx log.</p>";
				} else {
					$output = "<p>".nl2br($command)."</p>";
				}
			}
			break;

		case "nginx_reload":
			$command = shell_exec("service nginx reload");
			if(version_compare(CENTOS_RELEASE, '7', '>=')) {
				$output = "<p>Nginx configuration reloaded.</p>";
			} else {
				if(empty($command)) {
					$output = "<p>Something went wrong, check the Nginx log.</p>";
				} else {
					$output = "<p>".nl2br($command)."</p>";
				}
			}
			break;

		case "nginx_config":
			if(version_compare(CENTOS_RELEASE, '7', '>=')) {
				$command = shell_exec("nginx -t 2>&1");
			} else {
				$command = shell_exec("service nginx configtest 2>&1");
			}
			if(empty($command)) {
				$output = "<p>Something went wrong, check the Nginx log.</p>";
			} else {
				$output = "<p>".nl2br($command)."</p>";
			}
			break;

		case "nginx_purgecache":
			$command = shell_exec("find /tmp/engintron_dynamic/ -type f | xargs rm -rvf; find /tmp/engintron_static/ -type f | xargs rm -rvf; find /tmp/engintron_temp/ -type f | xargs rm -rvf");
			if(empty($command)) {
				$output = "<p>No output generated. Nginx cache & temp folders are probably empty.</p>";
			} else {
				$output = "<p>".nl2br($command)."</p>";
			}
			break;

		case "httpd_restart":
			$command = shell_exec("service httpd restart");
			if(empty($command)) {
				$output = "<p>Apache restarted.</p>";
			} else {
				$output = "<p>".nl2br($command)."</p>";
			}
			break;

		case "httpd_reload":
			$command = shell_exec("service httpd reload");
			if(empty($command)) {
				$output = "<p>Apache reloaded.</p>";
			} else {
				$output = "<p>".nl2br($command)."</p>";
			}
			break;

		case "httpd_status":
			if(file_exists("/usr/local/src/engintron/state.conf") && ENGINTRON_STATE=="on"){
				$command = shell_exec("curl http://127.0.0.1:8080/whm-server-status");
				if(empty($command)) {
					$output = "<p>No output generated by Apache - is it stopped?.</p>";
				} else {
					$output = "<pre>".trim($command)."</pre>";
				}
			} else {
				$command = shell_exec("service httpd status");
				if(empty($command)) {
					$output = "<p>No output generated by Apache - is it stopped?.</p>";
				} else {
					$output = "<p>".nl2br($command)."</p>";
				}
			}
			break;

		case "mysql_restart":
			$command = shell_exec("/usr/local/cpanel/scripts/restartsrv_mysql");
			if(empty($command)) {
				$output = "<p>Something went wrong, check the MySQL log.</p>";
			} else {
				$output = "<p>".nl2br($command)."</p>";
			}
			break;

		case "mysql_status":
			$command = shell_exec("service mysql status");
			if(empty($command)) {
				$output = "<p>Something went wrong, check the MySQL log.</p>";
			} else {
				$output = "<p>".nl2br($command)."</p>";
			}
			break;

	}
	return $output;
}

// Operations
switch($op) {
	case "view":
		if(isset($f) && in_array($f, $allowed_files)){
			$ret = file_get_contents($f);
		}
		break;

	case "edit":
		if(isset($_POST['data'])) {
			$data = $_POST['data'];
			if(isset($f) && in_array($f, $allowed_files)){
				file_put_contents($f, $data);
				$message = '<b>'.$f.'</b> has been updated';
				if(isset($_POST['c'])){
					switch($s){
						case "nginx":
							$message .= execute("nginx_reload");
							break;
						case "apache":
							$message .= execute("httpd_reload");
							break;
						case "mysql":
							$message .= execute("mysql_restart");
							break;
						case "phpconf":
							$message .= nl2br(shell_exec("/usr/local/cpanel/bin/apache_conf_distiller --update"));
							$message .= nl2br(shell_exec("/scripts/rebuildhttpdconf --update"));
							$message .= execute("httpd_restart");
							break;
						case "cron":
							$message .= nl2br(shell_exec("service crond restart"));
							break;
					}
				}
			}
		} else {
			$message = '';
		}
		break;

	case "nginx_status":
		$ret = "<b>Nginx Status:</b><br /><br />";
		$ret .= shell_exec("service nginx status 2>&1")."<br />";
		$ret .= shell_exec("curl http://localhost/nginx_status");
		break;

	case "nginx_restart":
		$ret = "<b>Restarting Nginx...</b><br />";
		$ret .= execute("nginx_restart");
		break;

	case "nginx_reload":
		$ret = "<b>Reloading Nginx...</b><br />";
		$ret .= execute("nginx_reload");
		break;

	case "nginx_config":
		$ret = "<b>Checking Nginx configuration...</b><br />";
		$ret .= execute("nginx_config");
		break;

	case "nginx_errorlog":
		if(empty($_POST['access_entries'])) {
			$entries = 100;
		} else {
			$entries = $_POST['access_entries'];
		}
		$ret = "<b>Showing last {$entries} entries from /var/log/nginx/error.log</b><br /><br />";
		$ret .= shell_exec("tail -{$entries} /var/log/nginx/error.log");;
		break;

	case "nginx_accesslog":
		if(empty($_POST['error_entries'])) {
			$entries = 100;
		} else {
			$entries = $_POST['error_entries'];
		}
		$ret = "<b>Showing last {$entries} entries from /var/log/nginx/access.log</b><br /><br />";
		$ret .= shell_exec("tail -{$entries} /var/log/nginx/access.log");;
		break;

	case "nginx_modules":
		$ret = "<b>Show precompiled Nginx modules...</b><br /><br />";
		$ret .= shell_exec("nginx -V 2>&1");
		break;

	case "nginx_purgelogs":
		$ret = "<b>Cleanup Nginx access &amp; error log files...</b><br />";
		$ret .= shell_exec("bash /engintron.sh purgelogs");
		$ret .= execute("nginx_restart");
		break;

	case "nginx_purgecache":
		$ret = "<b>Cleanup Nginx cache &amp; temp files...</b><br />";
		$ret .= execute("nginx_purgecache");
		$ret .= execute("nginx_restart");
		break;

	case "httpd_status":
		$ret = "<b>Apache Status:</b><br />";
		$ret .= execute("httpd_status");
		break;

	case "httpd_restart":
		$ret = "<b>Restarting Apache...</b><br />";
		$ret .= execute("httpd_restart");
		break;

	case "httpd_reload":
		$ret = "<b>Reloading Apache...</b><br />";
		$ret .= execute("httpd_reload");
		break;

	case "mysql_restart":
		$ret = "<b>Restarting MySQL...</b><br />";
		$ret .= execute("mysql_restart");
		break;

	case "mysql_status":
		$ret = "<b>MySQL Status:</b><br />";
		$ret .= execute("mysql_status");
		break;

	case "mysql_processlist":
		$ret = "<b>MySQL Process List:</b><br />";
		$ret .= shell_exec("mysqladmin processlist 2>&1");
		break;

	case "utils_80":
		$ret = "<b>$ netstat -anp | grep :80 | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -n</b><br /><br />";
		$ret .= shell_exec("netstat -anp | grep :80 | awk '{print $5}' | cut -d: -f1 | sort | uniq -c | sort -n")."<br /><br />";
		$ret .= "<b>$ netstat -an | grep :80 | wc -l</b><br /><br />";
		$ret .= trim(shell_exec("netstat -an | grep :80 | wc -l")).' total concurrent connections';
		break;

	case "utils_pstree":
		$ret = "<b>$ pstree</b><br /><br />";
		$ret .= shell_exec("pstree");
		break;

	case "utils_top":
		$ret = "<b>$ top -b -n 1</b><br /><br />";
		$ret .= shell_exec("top -b -n 1");
		break;

	case "utils_top_php":
		$ret = "<b>$ top -b -n 1 | grep php | sort -k8,8</b><br /><br />";
		$ret .= shell_exec("top -b -n 1 | grep php | sort -k8,8");
		break;

	case "utils_fixaccessperms":
		$ret = "<b>$ /engintron.sh fixaccessperms</b><br /><br />";
		$ret .= shell_exec("bash /engintron.sh fixaccessperms");
		break;

	case "utils_fixownerperms":
		$ret = "<b>$ /engintron.sh fixownerperms</b><br /><br />";
		$ret .= shell_exec("bash /engintron.sh fixownerperms");
		break;

	case "utils_cleanup":
		$ret = "<b>$ /engintron.sh cleanup</b><br /><br />";
		$ret .= shell_exec("bash /engintron.sh cleanup");
		break;

	case "engintron_toggle":
		if(file_exists("/usr/local/src/engintron/state.conf")){
			if(ENGINTRON_STATE=="on") {
				$ret = shell_exec("bash /engintron.sh disable");
			} elseif(ENGINTRON_STATE=="off") {
				$ret = shell_exec("bash /engintron.sh enable");
			} else {
				$ret = "Couldn't get state of Engintron - please try again.";
			}
		} else {
			$ret = "Couldn't get state of Engintron - please try again.";
		}
		break;

	case "engintron_update":
	case "engintron_update_stable":
		$ret = strip_tags(shell_exec("cd /; rm -f /engintron.sh; wget --no-check-certificate https://raw.githubusercontent.com/engintron/engintron/master/engintron.sh; bash engintron.sh install"), "<br><span>");
		break;

	case "engintron_update_mainline":
		$ret = strip_tags(shell_exec("cd /; rm -f /engintron.sh; wget --no-check-certificate https://raw.githubusercontent.com/engintron/engintron/master/engintron.sh; bash engintron.sh install mainline"), "<br><span>");
		break;

	case "engintron_res":
		$ret = shell_exec("bash /usr/local/src/engintron/engintron.sh res 2>&1");
		break;

	case "engintron_resall":
		$ret = shell_exec("bash /usr/local/src/engintron/engintron.sh resall 2>&1");
		break;

	case "utils_info":
	default:
		if(file_exists("/usr/local/src/engintron/state.conf")){
			if(ENGINTRON_STATE=="on") {
				$ret = "<b class=\"green ngStatus\">*** Engintron is ENABLED ***</b><i class=\"ngSep\">########################################</i>";
			} elseif(ENGINTRON_STATE=="off") {
				$ret = "<b class=\"ngStatus\">*** Engintron is DISABLED ***</b><i class=\"ngSep\">########################################</i>";
			} else {
				$ret = "*** Couldn't get state of Engintron - please try again. ***<i class=\"ngSep\">########################################</i>";
			}
		} else {
			$ret = "*** Couldn't get state of Engintron - please try again. ***<i class=\"ngSep\">########################################</i>";
		}
		$ret .= "<b class=\"green\">*** System Info ***</b><br /><br />";
		$ret .= "<b>Uptime:</b> ";
		$ret .= trim(shell_exec("uptime"))."<br /><br />";
		$ret .= "<b>OS:</b> ";
		$ret .= trim(shell_exec("cat /etc/redhat-release"))."<br /><br />";
		$ret .= "<b>Kernel:</b> ";
		$ret .= trim(shell_exec("uname -a"))."<br /><br />";
		$ret .= "<b>Processors:</b> ";
		$ret .= trim(shell_exec("grep processor /proc/cpuinfo | wc -l"))." CPU(s)<br /><br />";
		$ret .= "<b>RAM:</b> ";
		$ret .= round(trim(shell_exec("grep MemTotal /proc/meminfo | awk '{print $2}'")/(1024*1024)), 2)." GB<br /><br />";
		$ret .= "<b>Memory Usage:</b><br />";
		$ret .= shell_exec("free -m")."<br />";
		$ret .= "<b>Disk Usage:</b><br />";
		$ret .= trim(shell_exec("df -hT"))."<br /><br />";
		$ret .= "<b>Nginx Cache/Temp Disk Usage:</b><br />";
		$ret .= trim(shell_exec("du -sh /tmp/engintron_*"))."<br /><br />";
		$ret .= "<b>System Time:</b> ";
		$ret .= trim(shell_exec("date"))."<br /><br />";
		$ret .= "<b>System Users Connected:</b><br />";
		$ret .= trim(shell_exec("w"))."<br /><br />";
}

?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="utf-8" />
		<title><?php echo PLG_NAME; ?></title>
		<link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Open+Sans:400,400italic,700,700italic|Montserrat:400,700|Source+Code+Pro:400,700" />
		<link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css" />
		<link rel="stylesheet" type="text/css" href="https://engintron.com/app/webfonts/style.css" />
		<style type="text/css">
			body {margin:0;padding:0;font-family:'Open Sans',sans-serif;font-size:13px;color:#333;}
			a {color:#08c;text-decoration:none;}
			a:hover {text-decoration:underline;}
			input[type=submit] {padding:8px;border:0;font-size:13px;border-radius:4px;cursor:pointer;color:#fff;background-color:#179541;background-image:-webkit-gradient(linear, left top, left bottom, from(#179541), to(#007f2a));background-image:-webkit-linear-gradient(top, #179541, #007f2a);background-image:-moz-linear-gradient(top, #179541, #007f2a);background-image:-o-linear-gradient(top, #179541, #007f2a);background-image:linear-gradient(to bottom, #179541, #007f2a);-webkit-transition:all 500ms cubic-bezier(0.000, 0.685, 0.205, 0.995);-moz-transition:all 500ms cubic-bezier(0.000, 0.685, 0.205, 0.995);-ms-transition:all 500ms cubic-bezier(0.000, 0.685, 0.205, 0.995);-o-transition:all 500ms cubic-bezier(0.000, 0.685, 0.205, 0.995);transition:all 500ms cubic-bezier(0.000, 0.685, 0.205, 0.995);}
			.clr {clear:both;display:block;height:0;line-height:0;padding:0;margin:0;}
			.sep {padding:0 4px;margin:0;}
			.ngViewDefault {font-size:12px;font-style:italic;}
			hr {line-height:0;height:0;border:none;border-bottom:1px solid #eaeaea;padding:0;margin:8px 0;}
			div#ngBreadcrumbs {background:#eee;padding:4px 16px;margin:0;border-bottom:2px solid #eaeaea;}
				div#ngBreadcrumbs a {color:#999;font-weight:bold;text-decoration:none;font-size:12px;margin:0 4px;}
				div#ngBreadcrumbs a:hover {color:#666;}
				div#ngBreadcrumbs a.active {color:#333;}
			div#ngContainer {margin:0;padding:0 16px 4px;}
				h1#ngTitle {margin:0;padding:0;text-align:center;}
				h1#ngTitle a {background:url('https://engintron.com/app/images/Engintron_Logo_316x98_8.png') no-repeat 0 50%;font-size:20px;padding:36px 0 36px 326px;margin:0 0 8px 0;color:#333;display:inline-block;text-decoration:none;text-align:left;}
				h1#ngTitle a span {display:block;font-size:11px;font-weight:normal;color:#999;}
				h2 {border-bottom:2px solid #eaeaea;padding:8px 0;text-transform:uppercase;font-family:'Montserrat',sans-serif;font-weight:700;font-size:24px;color:#008d23;}
				div#ngOperations {float:left;width:30%;}
				div#ngOperations ul {padding:0 0 0 8px;margin:0;list-style:none;}
				div#ngOperations ul li {padding:1px 0;}
				div#ngOperations ul li.active {font-weight:bold;}
				div#ngOperations ul li h3 {padding:0;margin:0 0 4px 0;}
				div#ngOperations ul li ul {padding:0 0 0 16px;margin:0 0 16px 0;list-style:square;}
				div#ngOperations ul li form.displayLogs a:hover {text-decoration:none;}
				div#ngOperations ul li form.displayLogs input {border:none;border-bottom:1px solid #08c;text-align:center;color:#08c;font-size:12px;padding:1px 8px;}
				div#ngOperations ul li.active form.displayLogs input {font-weight:bold;}
				div#ngOperations ul li form.displayLogs:hover a {text-decoration:underline;}
				div#ngOperations ul li.ngUpdate span {font-size:11px;font-weight:normal;font-style:italic;color:#999;display:none;}
					p#ngSocialIcons a {color:#333;font-size:20px;text-decoration:none;margin:0 20px 0 0;}
					a#cpAppsLink {background:#f26b32;color:#fff;padding:4px 8px 2px;margin:0;border-radius:3px;font-size:10px;font-weight:bold;vertical-align:super;}
					a#cpAppsLink:hover {background:#e34806;text-decoration:none;}
					p#commercialSupport b {}
				div#ngOutput {float:right;width:68%;}
					#ngTerminalWindow {text-align:left;width:100%;height:460px;border-radius:10px;margin:auto;}
					#ngTerminalWindow header {background:#eaeaea;height:30px;border-radius:8px 8px 0 0;padding:0 10px;margin:0;text-align:center;}
						#ngTerminalWindow header .button {width:12px;height:12px;margin:10px 6px 0 0;border-radius:8px;float:left;}
						#ngTerminalWindow header .button.green {background:#3BB662;}
						#ngTerminalWindow header .button.yellow {background:#E5C30F;}
						#ngTerminalWindow header .button.red {background:#E75448;}
						#ngTerminalWindow header span {line-height:30px;display:block;width:100px;margin:0 auto;}
					div#ngOutputWindow {padding:0;margin:0 0 20px 0;border:1px solid #eaeaea;}
					div#ngOutputWindow pre {font-family:'Source Code Pro',monospace;font-size:13px;white-space:pre-wrap;color:#fff;background:#000;padding:8px;margin:0;min-height:300px;max-height:900px;overflow:auto;}
						div#ngOutputWindow pre b {color:red;}
						div#ngOutputWindow pre b.green,
						div#ngOutputWindow pre span {color:green;}
						div#ngOutputWindow pre b.ngStatus {font-size:18px;}
						div#ngOutputWindow pre i.ngSep {color:#aaa;font-size:12px;display:block;padding:0;margin:20px 0;}
					body.op_edit div#ngOutputWindow {border:1px solid #eaeaea;border-top:0;padding:0;margin:0;}
					#ngAceEditor {box-sizing:border-box;border:none;width:100%;padding:8px;margin:0;font-family:'Source Code Pro',monospace;font-size:13px;height:460px;overflow:auto;color:#fff;background:#000;outline:0;}
					div#ngOutput form#fileEditor textarea#data {display:none;}
					div#ngOutput form#fileEditor .editbox {background:#eee;border-top:1px solid #eaeaea;padding:8px;margin:-3px 0 0 0;}
			div#ngFooter {text-align:center;border-top:2px solid #eaeaea;background:#eee;padding:16px;margin:0;}
				div#ngFooter p {margin:0;padding:0;font-size:12px;color:#666;}
				div#ngFooter a {color:#333;font-weight:bold;text-decoration:none;}
				div#ngFooter a:hover {text-decoration:underline;}
			div#ngMessage {position:fixed;top:16px;right:16px;background:#fff;font-size:12px;line-height:12px;text-align:center;margin:0;padding:16px;border-radius:4px;box-shadow:0 1px 4px 0 #999;}
				div#ngMessage .ngMsgState {width:16px;height:16px;margin:0 10px 0 0;padding:0;display:inline-block;background:#5fca4a;vertical-align:text-top;}
			.hidden {opacity:0;transition:opacity 2s linear;}
		</style>
		<script>
			(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
			(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
			m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
			})(window,document,'script','//www.google-analytics.com/analytics.js','ga');
			ga('create', 'UA-16375363-18', 'auto');
			ga('send', 'pageview', '/engintron_whm_app');
		</script>
	</head>
	<body class="op_<?php echo $op; ?>">
		<div id="ngBreadcrumbs">
			<a href="../scripts/command?PFILE=main">Home</a> &raquo; <a href="../scripts/command?PFILE=Plugins">Plugins</a> &raquo; <a href="engintron.php" class="active"><?php echo PLG_NAME; ?></a>
		</div>
		<div id="ngContainer">
			<h1 id="ngTitle"><a href="engintron.php" title="<?php echo PLG_NAME; ?>">v<?php echo PLG_VERSION; ?><span>(Nginx version: <?php echo NGINX_VERSION; ?>)</span></a></h1>
			<div id="ngOperations">
				<h2>Operations</h2>
				<ul>
					<li>
						<h3>System</h3>
						<ul>
							<li><a href="engintron.php">System Status &amp; Info</a></li>
							<li><a href="engintron.php?op=engintron_res">Restart Apache &amp; Nginx</a></li>
							<li><a href="engintron.php?op=engintron_resall">Restart all services</a></li>
						</ul>
					</li>
					<li>
						<h3>Nginx</h3>
						<ul>
							<li><a href="engintron.php?op=nginx_status">Status</a></li>
							<li><a href="engintron.php?op=nginx_reload">Reload</a></li>
							<li><a href="engintron.php?op=nginx_restart">Restart</a></li>
							<li><a href="engintron.php?op=edit&f=/etc/nginx/custom_rules&s=nginx">Edit your custom_rules for Nginx</a><?php if(file_exists('/etc/nginx/custom_rules.dist')): ?> (<a class="ngViewDefault" href="engintron.php?op=view&f=/etc/nginx/custom_rules.dist">view default</a>)<?php endif; ?></li>
							<li><a href="engintron.php?op=edit&f=/etc/nginx/conf.d/default.conf&s=nginx">Edit default.conf</a></li>
							<li><a href="engintron.php?op=edit&f=/etc/nginx/proxy_params_common&s=nginx">Edit proxy_params_common</a></li>
							<li><a href="engintron.php?op=edit&f=/etc/nginx/proxy_params_dynamic&s=nginx">Edit proxy_params_dynamic</a></li>
							<li><a href="engintron.php?op=edit&f=/etc/nginx/proxy_params_static&s=nginx">Edit proxy_params_static</a></li>
							<li><a href="engintron.php?op=edit&f=/etc/nginx/nginx.conf&s=nginx">Edit nginx.conf</a></li>
							<li><a href="engintron.php?op=nginx_config">Check configuration for errors</a></li>
							<li><a href="engintron.php?op=nginx_modules">Show compiled modules</a></li>
							<li>
								<form action="engintron.php?op=nginx_accesslog" method="post" id="accesslog" class="displayLogs">
									<a href="engintron.php?op=nginx_accesslog" onClick="ngSaveFile('accesslog')">Show last</a> <input type="text" name="access_entries" size="4" value="100" autocomplete="off" /> <a href="engintron.php?op=nginx_accesslog" onClick="ngSaveFile('accesslog')">access log entries</a>
								</form>
							</li>
							<li>
								<form action="engintron.php?op=nginx_errorlog" method="post" id="errorlog" class="displayLogs">
									<a href="engintron.php?op=nginx_errorlog" onClick="ngSaveFile('errorlog')">Show last</a> <input type="text" name="error_entries" size="4" value="100" autocomplete="off" /> <a href="engintron.php?op=nginx_errorlog" onClick="ngSaveFile('errorlog')">error log entries</a>
								</form>
							</li>
							<li><a href="engintron.php?op=nginx_purgelogs">Purge access &amp; error log files</a></li>
							<li><a href="engintron.php?op=nginx_purgecache">Purge cache &amp; temp files</a></li>
						</ul>
					</li>
					<li>
						<h3>Apache (&amp; PHP)</h3>
						<ul>
							<li><a href="engintron.php?op=httpd_status">Status</a></li>
							<li><a href="engintron.php?op=httpd_reload">Reload</a></li>
							<li><a href="engintron.php?op=httpd_restart">Restart</a></li>
							<li><a href="engintron.php?op=edit&f=/usr/local/lib/php.ini&s=apache">Edit php.ini</a></li>
							<?php if(file_exists('/usr/local/apache/conf/php.conf')): ?>
							<li><a href="engintron.php?op=edit&f=/usr/local/apache/conf/php.conf&s=phpconf">Edit php.conf</a></li>
							<?php endif; ?>
						</ul>
					</li>
					<li>
						<h3>MySQL</h3>
						<ul>
							<li><a href="engintron.php?op=mysql_status">Status</a></li>
							<li><a href="engintron.php?op=mysql_restart">Restart</a></li>
							<!--<li><a href="engintron.php?op=mysql_processlist">Process list</a></li>-->
							<li><a href="engintron.php?op=edit&f=/etc/my.cnf&s=mysql">Edit my.cnf</a></li>
						</ul>
					</li>
					<li>
						<h3>Other System Configuration Files</h3>
						<ul>
							<li><a href="engintron.php?op=edit&f=/etc/crontab&s=cron">Edit /etc/crontab</a></li>
						</ul>
					</li>
					<li>
						<h3>Utilities</h3>
						<ul>
							<li><a href="engintron.php?op=utils_top">Show all processes (top)</a></li>
							<li><a href="engintron.php?op=utils_top_php">Show top PHP processes</a></li>
							<li><a href="engintron.php?op=utils_pstree">Show current process tree</a></li>
							<li><a href="engintron.php?op=utils_80">Current connections on port 80 (per IP &amp; total)</a></li>
							<!--
							<li><a href="engintron.php?op=utils_fixaccessperms">Change file &amp; directory access permissions to 644 &amp; 755 respectively in all user /public_html directories</a></li>
							<li><a href="engintron.php?op=utils_fixownerperms">Fix owner permissions in all user /public_html directories</a></li>
							<li><a href="engintron.php?op=utils_cleanup">Cleanup Mac or Windows specific metadata &amp; Apache error_log files in all user /public_html directories</a></li>
                     		-->
						</ul>
					</li>
					<li>
						<h3>Engintron</h3>
						<ul>
							<li><a href="engintron.php?op=engintron_toggle">Enable/Disable Engintron</a></li>
							<li id="ngUpdateStable" class="ngUpdate">
								<a href="engintron.php?op=engintron_update_stable">Update (or re-install) Engintron [Nginx stable]</a>
								<span>[please wait a few minutes...]</span>
							</li>
							<li id="ngUpdateMainline" class="ngUpdate">
								<a href="engintron.php?op=engintron_update_mainline">Update (or re-install) Engintron [Nginx mainline]</a>
								<span>[please wait a few minutes...]</span>
							</li>
						</ul>
					</li>
				</ul>
				<h2>About</h2>
				<p><a target="_blank" href="https://engintron.com/"><?php echo PLG_NAME; ?></a> integrates the popular <a target="_blank" href="http://nginx.org/">Nginx</a><sup>&reg;</sup> web server as a "reverse caching proxy" in front of Apache in cPanel<sup>&reg;</sup>.</p>
				<p>Nginx will cache &amp; serve static assets like CSS, JavaScript, images etc. as well as dynamic HTML with a 1 second micro-cache. This process will reduce CPU &amp; RAM usage on your server, while increasing your overall serving capacity. The result is a faster performing cPanel server.</p>
				<p>Engintron is both free &amp; open source.<br /><br /><a target="_blank" href="https://github.com/engintron/engintron/issues">Report issues/bugs</a> or <a target="_blank" href="https://github.com/engintron/engintron/pulls">help us improve it</a>.</p>
				<p><a class="github-button" href="https://github.com/engintron/engintron" data-count-href="/engintron/engintron/stargazers" data-count-api="/repos/engintron/engintron#stargazers_count" data-count-aria-label="# stargazers on GitHub" aria-label="Star engintron/engintron on GitHub">Star</a><span class="sep">&nbsp;</span><a href="https://twitter.com/intent/tweet?button_hashtag=engintron&text=Just%20installed%20Engintron%20for%20cPanel%2FWHM%20to%20improve%20my%20cPanel%20server's%20performance" class="twitter-hashtag-button" data-url="https://engintron.com">Tweet #engintron</a><span class="sep">&nbsp;</span><a id="cpAppsLink" target="_blank" href="https://applications.cpanel.com/listings/view/Engintron-Nginx-on-cPanel"><i class="icon-ng-cpanel"></i> Rate on cPApps</a>
</p>
				<p id="ngSocialIcons"><a target="_blank" href="https://engintron.com/"><i class="fa fa-globe"></i></a><a target="_blank" href="https://github.com/engintron/engintron"><i class="fa fa-github"></i></a><a target="_blank" href="https://www.facebook.com/engintron"><i class="fa fa-facebook"></i></a><a target="_blank" href="https://twitter.com/engintron_sh"><i class="fa fa-twitter"></i></a><a target="_blank" href="https://plus.google.com/117428375464020763682"><i class="fa fa-google-plus"></i></a><a target="_blank" href="https://applications.cpanel.com/listings/view/Engintron-Nginx-on-cPanel"><i class="icon-ng-cpanel"></i></a><a href="mailto:4asloy9a"><i class="fa fa-envelope"></i></a></p>
				<p id="commercialSupport"><b>Looking for commercial support?</b> <a href="mailto:4asloy9a">Get in touch with us</a>.
			</div>
			<div id="ngOutput">
				<h2>&gt; Output</h2>
				<div id="ngTerminalWindow">
				  <header>
					<div class="button green"></div>
					<div class="button yellow"></div>
					<div class="button red"></div>
					<span>$ engintron</span>
				  </header>
				  <div id="ngOutputWindow">
					<?php if($ret): ?>
					<pre><?php echo $ret; ?></pre>
					<?php endif; ?>
					<?php if($op=='edit'): ?>
					<form action="engintron.php?op=edit&f=<?php echo $f; ?>" method="post" id="fileEditor">
						<div id="ngAceEditor"></div>
						<textarea id="data" name="data"><?php echo file_get_contents($f); ?></textarea>
						<div class="editbox">
							<input type="checkbox" name="c" checked />Reload or restart related services (<?php echo (isset($_POST['s'])) ? $_POST['s'] : ucfirst($s); ?>)? <small>(recommended if you want changes to take effect immediately)</small>
							<br /><br />
							<input type="hidden" name="s" value="<?php echo ucfirst($s); ?>" />
							<input type="submit" value="Update <?php echo $f; ?>" onClick="ngSaveFile('fileEditor')" />
						</div>
					</form>
					<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="clr"></div>
		</div>
		<div id="ngFooter">
			<p><a target="_blank" href="https://engintron.com/"><?php echo PLG_NAME; ?> - v<?php echo PLG_VERSION; ?></a> | Copyright &copy; 2014-<?php echo date('Y'); ?> <a target="_blank" href="http://nuevvo.com/">Nuevvo Webware P.C.</a> Released under the <a target="_blank" href="http://www.gnu.org/licenses/gpl.html">GNU/GPL</a> license.</p>
		</div>
		<?php if($message): ?>
		<div id="ngMessage"><div class="ngMsgState"></div><?php echo $message; ?></div>
		<?php endif; ?>

		<!-- JS -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.2.3/ace.js"></script>
		<script src="https://squaresend.com/squaresend.js"></script>
		<script async defer id="github-bjs" src="https://buttons.github.io/buttons.js"></script>
		<script>

			// Ace
			if(document.getElementById('ngAceEditor')){
				var editor = ace.edit("ngAceEditor");
				editor.$blockScrolling = Infinity;
				editor.setTheme("ace/theme/twilight");
				editor.getSession().setMode("ace/mode/sh");
				editor.getSession().setUseWrapMode(true);
				editor.resize();
				var t = document.getElementById('data');
				var tVal = t.value;
				editor.getSession().setValue(tVal);
				editor.getSession().on('change', function(){
					t.value = editor.getSession().getValue();
				});
			}

			// Squaresend
			sqs_title = "Commercial Support for Engintron";
			sqs_placeholder_subject = "I'm interested in commercial support for Engintron";
			sqs_placeholder_message = "Please provide as much information as possible to help us understand how we can help you - there is no need to send us access credentials at this point."

			// Twitter
			!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');

			// Engintron
			var ENGINTRON_VERSION = '<?php echo PLG_VERSION; ?>';
			var CENTOS_VERSION = '<?php echo CENTOS_RELEASE; ?>';

			function ngSaveFile(el) {
				document.getElementById(el).submit();
				return false;
			}
			function ngUpdate(el){
				var updContainer = document.getElementById(el);
				if(updContainer){
					var updLink = updContainer.getElementsByTagName('a')[0];
					updLink.onclick = function(){
						updContainer.getElementsByTagName('span')[0].setAttribute('style', 'display:inline;');
						if(this.className != "clicked") {
							this.className = "clicked";
							return true;
						} else {
							return false;
						}
					}
				}
			}
			function ngUtils(){
				// Highlight menu
				var i = 0,
					menuItems = document.getElementById('ngOperations').getElementsByTagName('a');
				for(; i < menuItems.length; ++i){
					if(window.location.href === menuItems[i].href){
						if(menuItems[i].parentNode.nodeName.toLowerCase() == 'form'){
							menuItems[i].parentNode.parentNode.className = 'active';
						} else {
							menuItems[i].parentNode.className = 'active';
						}
					}
				}
				// Disable the update/re-install links when clicked
				ngUpdate('ngUpdateStable');
				ngUpdate('ngUpdateMainline');
				// Hide message after 3 seconds
				if(document.getElementById('ngMessage')){
					setTimeout(function(){
						document.getElementById('ngMessage').className += 'hidden';
					}, 3000);
				}
			}
			ngUtils();

		</script>
		<script src="https://engintron.com/app/js/services.js?t=<?php echo date('Ymd'); ?>"></script>
	</body>
</html>
