<?php
 /*
Plugin Name: Comment Authors Updater
Plugin URI: http://friedcellcollective.net/wp/comment_authors
Description: A plugin that updates comment author in comments table when users change their display name
Author: Marko Mrdjenovic
Version: 0.1
Author URI: http://friedcellcollective.net/outbreak
 */
?>
<?php

class FCC_CommentAuthors {
	function get_comment_authors() {
		global $wpdb;
		$sql = "SELECT user_id, comment_author, count(*) AS c FROM $wpdb->comments WHERE user_id > 0 GROUP BY user_id, comment_author ORDER BY user_id";
		$results = $wpdb->get_results($sql);
		$authors = array();
		foreach($results as $a) {
			$r = array($a->comment_author, $a->c);
			if (array_key_exists($a->user_id, $authors)) {
				array_push($authors[$a->user_id], $r);
			} else {
				$authors[$a->user_id] = array($r);
			}
		}
		return $authors;
	}
	function get_users($ids) {
		global $wpdb;
		$sql = "SELECT id, user_login, user_email, display_name FROM $wpdb->users WHERE id IN (".join(",", $ids).")";
		$results = $wpdb->get_results($sql);
		$users = array();
		foreach($results as $u) {
			$users[$u->id] = array($u->display_name, $u->user_login, $u->user_email);
		}
		return $users;
	}
	function display_interface() {
		if (isset($_POST['submit'])) {
			FCC_CommentAuthors::handle_response();
		}
		$authors = FCC_CommentAuthors::get_comment_authors();
		$uids = array();
		foreach ($authors as $aid => $a) {
			if (!array_key_exists($aid, $uids)) {
				array_push($uids, $aid);
			}
		}
		$users = FCC_CommentAuthors::get_users($uids);
		$changes = get_option('displayname_changes');
		// TODO: make this extend WP_List_Table or WP_Users_List_Table
		?><div class="wrap">
	<h2><?php _e('Comment Authors'); ?></h2>
	<form action="" method="post" style="margin:0;">
		<table class="wp-list-table widefat fixed users" cellspacing="0">
			<thead>
				<tr>
					<th class="manage-column column-cb check-column"><input type="checkbox" /></th>
					<th class="manage-column column-username"><?php _e('Username'); ?></th>
					<th class="manage-column column-name"><?php _e('Display Name'); ?></th>
					<th class="manage-column column-author" style="width:60%;"><?php _e('Comment Author Names'); ?></th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th class="manage-column column-cb check-column"><input type="checkbox" /></th>
					<th class="manage-column column-username"><?php _e('Username'); ?></th>
					<th class="manage-column column-name"><?php _e('Display Name'); ?></th>
					<th class="manage-column column-author" style="width:60%;"><?php _e('Comment Author Names'); ?></th>
				</tr>
			</tfoot>
			<tbody id="the-list"><?php
		$n = 0;
		foreach ($authors as $aid => $a) {
			$u = $users[$aid];
			$names = "";
			$c = false;
			foreach ($a as $author) {
				$n += 1;
				if ($names) {
					$names .= ", ";
				}
				$names .= $author[0]." (". $author[1] .")";
				if ($author[0] !== $u[0]) {
					$c = true;
				}
			} ?>
				<tr id="user-<?php print $aid; ?>">
					<th class="check-column"><input type="checkbox" name="users[]" value="<?php print $aid; ?>"<?php if (!$c) { ?> disabled="disabled"<?php } ?> /></th>
					<td class="username column-username">
						<?php print get_avatar($u[2], 32); ?>
						<strong><?php print $u[1]; ?></strong>
						<br />
					</td>
					<td><?php print $u[0]; ?></td>
					<td><?php print $names; ?></td>
				</tr><?php
		} ?>
			</tbody>
		</table>
		<div class="tablenav bottom">
			<div class="alignleft actions">
				<input class="button-primary action" type="submit" value="<?php _e('Update comments'); ?>" name="" />
			</div>
			<div class="tablenav-pages one-page">
				<span class="displaying-num"><?php printf(_n('1 item', '%s items', $n), number_format_i18n($n)) ?></span>
			</div>
		</div>
		<input type="hidden" name="submit" value="1" />
	</form>
</div>
		<?php
		// display table with checkboxes where author name does not equal user display name
	}
	function add_interface() {
		add_submenu_page('users.php', __('Comment Authors'), __('Comment Authors'), 'moderate_comments', 'comment_authors', "FCC_CommentAuthors::display_interface");
	}
	function handle_response() {
		global $wpdb;
		$uids = $_POST["users"];
		$users = FCC_CommentAuthors::get_users($uids);
		$n = 0;
		foreach ($users as $uid => $user) {
			$author = $user[0];
			$wpdb->update($wpdb->comments, array('comment_author' => $author), array('user_id' => $uid));
			$n += 1;
		}
		update_option('displayname_changes', 0);
		return $n;
	}
	function admin_warnings() {
		if ( !isset($_POST['submit']) ) {
			$changes = get_option('displayname_changes');
			if ($changes > 0) {
				function displayname_warning() { 
					$changes = get_option('displayname_changes'); ?>
					<div id="displayname-warning" class="updated fade">
						<p><?php printf(__('There have been <strong>%1$s</strong>. <a href="%2$s">Update comments</a>.'), sprintf(_n('1 display name change', '%s display name changes', $changes), number_format_i18n($changes)), "users.php?page=comment_authors"); ?></p>
					</div><?php
				}
				add_action('admin_notices', 'displayname_warning');
			}
		}
	}
	function update_displayname_changes($val) {
		$changes = get_option('displayname_changes') + 1;
		update_option('displayname_changes', $changes);
		return $val;
	}
	function FCC_CommentAuthors() {
		// Empty.
	}
}
add_action("admin_menu", "FCC_CommentAuthors::add_interface");
// TODO: add option to append the update to pre_user_display_name
add_filter("pre_user_display_name", "FCC_CommentAuthors::update_displayname_changes");
FCC_CommentAuthors::admin_warnings();
?>