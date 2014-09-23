<?php
/*
Plugin Name: Multidub
Description: Allows you to dublicate posts, pages and custom posts in a multisite environment.
Version: 1.0
Author: Johannes Fag
License: GPLv2 or later
Text Domain: multidub
*/

/*
* Load plugin translation files
*/
function le_multidub_action_init()
{
	load_plugin_textdomain('multidub', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}

add_action('init', 'le_multidub_action_init');


/**
* Copy post to selected multisite blog as draft
*
* @param int $post_id The id of the post
* @param int $target_blog_id The id of the target blog
*/
function le_multidub_copy_post_to_blog($post_id, $target_blog_id) {

	$post = get_post($post_id, ARRAY_A); // get the original post
	$meta = get_post_meta($post_id);
	$post['ID'] = ''; // empty id field
	$post['post_status'] = 'draft'; // Change to draft
	switch_to_blog($target_blog_id); // switch to target blog
	$inserted_post_id = wp_insert_post($post); // insert the post

	foreach($meta as $key=>$value) {
		update_post_meta($inserted_post_id,$key,$value[0]);
	}

	restore_current_blog(); // return to original blog

	$le_multidub_is_published = true;
}

/**
 * Adds a box to the main column on the Post and Page edit screens.
 */
function le_multidub_add_meta_box() {

	$screens = array( 'post', 'page' );

	foreach ( $screens as $screen ) {

		add_meta_box(
			'le_multidub_sectionid',
			__( 'Dublicate to draft on these sites', 'multidub' ),
			'le_multidub_meta_box_callback',
			$screen
		);
	}
}
add_action( 'add_meta_boxes', 'le_multidub_add_meta_box' );

/**
 * Prints the box content.
 * 
 * @param WP_Post $post The object for the current post/page.
 */
function le_multidub_meta_box_callback( $post ) {

	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'le_multidub_meta_box', 'le_multidub_meta_box_nonce' );

	$blog_list = get_blog_list( 0, 'all' );

    $current_blog_id = get_current_blog_id();

	foreach ($blog_list AS $blog) {
		if($current_blog_id != $blog['blog_id']) {
			
			echo '<p>';
			
			echo '<input type="checkbox" name="le_multidub_site[]" value="' . esc_attr( $blog['blog_id'] ) . '">';
		
			$current_blog_details = get_blog_details( array( 'blog_id' => $blog['blog_id'] ) );
			
			echo $current_blog_details->blogname;
			
			echo '</p>';

		}
	}

	
}

/**
 * Remove all publish actions to avoid infinite loop.
 */
function remove_actions() {
	remove_action('new_to_publish', 'le_multidub_save_meta_box_data', 10, 1 );
	remove_action('draft_to_publish', 'le_multidub_save_meta_box_data', 10, 1 );
	remove_action('future_to_publish', 'le_multidub_save_meta_box_data', 10, 1 );
}

/**
 * Reset all publish actions.
 */
function reset_actions() {
	add_action('new_to_publish', 'le_multidub_save_meta_box_data', 10, 1 );
	add_action('draft_to_publish', 'le_multidub_save_meta_box_data', 10, 1 );
	add_action('future_to_publish', 'le_multidub_save_meta_box_data', 10, 1 );
}

/**
 * When the post is saved, saves our custom data.
 *
 * @param WP_Post $post The object for the current post/page.
 */
function le_multidub_save_meta_box_data( $post ) {

	global $post;
    global $flag;

    //Following code makes sure it doesn't get executed twice
    if($flag ==0) $flag =1;  
    else return;

    //Next to temporarily disable this filter
    remove_actions();

	// Check if our nonce is set.
	if ( ! isset( $_POST['le_multidub_meta_box_nonce'] ) ) {
		reset_actions();
		return;
	}

	// Verify that the nonce is valid.
	if ( ! wp_verify_nonce( $_POST['le_multidub_meta_box_nonce'], 'le_multidub_meta_box' ) ) {
		reset_actions();
		return;
	}

	// If this is an autosave, our form has not been submitted, so we don't want to do anything.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		reset_actions();
		return;
	}

	// Check the user's permissions.
	if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) {

		if ( ! current_user_can( 'edit_page', $post->ID ) ) {
			reset_actions();
			return;
		}

	} else {

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			reset_actions();
			return;
		}
	}

	if($_POST["le_multidub_site"]) {

		foreach ($_POST["le_multidub_site"] as $site_id) {
			le_multidub_copy_post_to_blog($post->ID, $site_id);
		}

	}

	reset_actions();
}

reset_actions();

?>