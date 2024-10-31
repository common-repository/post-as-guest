<?php
/*
Plugin Name: Post As Guest
Plugin URI: https://powie.de/wordpress/post-as-guest/
Description: Post as Guest - Creates a form (shortcode) to a page to allow guests to post
Version: 0.9.9
License: GPLv2
Author: Thomas Ehrhardt
Author URI: https://powie.de
Text Domain: post-as-guest
Domain Path: /languages
*/

//Define some stuff
define( 'PAG_PLUGIN_DIR', dirname( plugin_basename( __FILE__ ) ) );
define( 'PAG_PLUGIN_URL', plugins_url( dirname( plugin_basename( __FILE__ ) ) ) );
load_plugin_textdomain( 'post-as-guest', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

//create custom plugin settings menu
add_action('admin_menu', 'pag_create_menu');
add_action('admin_init', 'pag_register_settings' );
//add_action('wp_head', 'plinks_websnapr_header');
//Shortcode
add_shortcode('post-as-guest', 'pag_shortcode');
//Hook for Activation
register_activation_hook( __FILE__, 'pag_activate' );
//Hook for Deactivation
register_deactivation_hook( __FILE__, 'pag_deactivate' );

//PAG Ajax Javascripts Admin
function pagjs(){
	wp_enqueue_script( 'pagjs', PAG_PLUGIN_URL.'/pag.js', array('jquery'), '1.0' );
}
add_action( 'admin_init', 'pagjs' );

//Nonces!!! nont nonsens :)
add_action('init','pagnonces_create');
function pagnonces_create(){
	$pagnonce = wp_create_nonce('pagnonce');
}

//PAG JS Frontend
// thanks to http://www.garyc40.com/2010/03/5-tips-for-using-ajax-in-wordpress/
// embed the javascript file that makes the AJAX request
function pagfejs(){
	wp_enqueue_script( 'pagajaxrequest', plugin_dir_url( __FILE__ ) . 'pagfe.js', array( 'jquery' ) );
	wp_localize_script( 'pagajaxrequest', 'PagAjax',
		array(  'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'enter_title' => __('Please enter title', 'post-as-guest'),
				'enter_content' => __('Please enter content', 'post-as-guest' )
		)
	);
	//reCaptcha:
	wp_enqueue_script( 'recaptchaapi', 'https://www.google.com/recaptcha/api.js', $deps = array(), $ver = false, $in_footer = false );
}
add_action( 'wp_enqueue_scripts', 'pagfejs' );
// declare the URL to the file that handles the AJAX request (wp-admin/admin-ajax.php)

//Create Menus
function pag_create_menu() {
	// create PAG menu page
	add_menu_page( __('Post as Guest','post-as-guest'), __('Post as Guest','post-as-guest'), 'manage_options', PAG_PLUGIN_DIR.'/pag_review.php','',PAG_PLUGIN_URL.'/images/pag.png');
	add_submenu_page( PAG_PLUGIN_DIR.'/pag_review.php', __('Settings','post-as-guest'), __('Settings','post-as-guest'), 'manage_options', PAG_PLUGIN_DIR.'/pag_settings.php');
}

function pag_register_settings() {
	//register settings
	register_setting( 'pag-settings', 'postfield-rows', 'intval' );				//Zeilen Textarea
	register_setting( 'pag-settings', 'postfield-legend');						//Bezeichnung
	register_setting( 'pag-settings', 'prepost-code');
	register_setting( 'pag-settings', 'afterpost-code' );
	register_setting( 'pag-settings', 'after-post-msg' );
	register_setting( 'pag-settings', 'category-select', 'intval' );			//Auswahl der Kategorie gestatten
	register_setting( 'pag-settings', 'default-categoryid', 'intval' );			//Auswahl der Standard Kategorie
	register_setting( 'pag-settings', 'notify-admin', 'intval' );				//Admin Benachrichtigung 1 / 0
	register_setting( 'pag-settings', 'notify-email' );							//Admin eMail Adresse
	register_setting( 'pag-settings', 'rc-site-key' );							//ReCpatcha Site Key
	register_setting( 'pag-settings', 'rc-secret-key' );						//ReCpatcha Secret Key
}

function pag_shortcode( $atts ) {
	//var_Dump($atts);
	/*
	extract( shortcode_atts( array(
		'foo' => 'something',
		'bar' => 'something else',
	), $atts ) );
	return "Hallo -> foo = {$foo}";
	*/
	$sc = '<!-- post-as-guest -->';
	$sc.= '<div id="pag_form"><form method="post" id="pag" action="">
			<input type="hidden" name="action" value="pag_post" />';
    $sc.= wp_nonce_field( 'pagnonce', 'post_nonce', true, false );
	$sc.= '<legend>'.__('Title', 'post-as-guest').'</legend>
        	 <input type="text" size="50" name="pagtitle" id="pagtitle" />
        	<legend>'.get_option('postfield-legend').'</legend>
        	 <textarea rows="'.get_option('postfield-rows').'" name="pagcontent" id="pagcontent" style="width:100%;"></textarea>';
	if (get_option('category-select') == 1) {
		$sc.='<legend>'.__('Category', 'post-as-guest').'</legend>
			   <select id="categoryid" name="categoryid">
					<option value="">'.__('-- Please Select Category','post-as-guest').'</option>';
						$categories = get_categories('hierarchical=0&hide_empty=0');
						foreach($categories as $category)	{
							//$selected = (is_category($category->cat_ID)) ? 'selected' : '';
							$selected = ( get_option('default-categoryid') == $category->cat_ID ) ? 'selected' : '';
							$sc.='<option '.$selected.' value="'.$category->cat_ID.'">'.$category->cat_name.'</option>';
						}
		$sc.='</select><br />';
	}
	if (get_option('rc-secret-key') != "") {
		$sc.='<br /><br /><div class="g-recaptcha" data-sitekey="'.get_option('rc-site-key').'"></div><br />';
	}
    $sc.='   <input type="submit" id="pagsubmit" name="pagsubmit" value="'.__("Send In", 'post-as-guest').'" /></form>
		   </div>';
	$sc.='<!-- /post-as-guest -->';
	return $sc;
}

//Activate
function pag_activate() {
	// do not generate any output here
	add_option('postfield-rows',5);
	add_option('after-post-msg', __('Thanks for your post. We will review your post befor publication.','post-as-guest'));
}

//Deactivate
function pag_deactivate() {
	// do not generate any output here
}

//Post as Guest - get count of pages waiting for review
function pag_get_posts_wait_count(){
	$args = array( 'post_type' => 'post', 'post_status' => 'review', 'numberposts' => 100 );
	$data =get_posts($args);
	return(count($data));
}

function pag_get_posts_wait(){
	$args = array( 'post_type' => 'post', 'post_status' => 'pending', 'orderby' => 'post_date', 'order' => 'DESC',
					'numberposts' => 100 );
	return get_posts($args);
}

//Ajax Functions Backend!
// Post Preview
add_action('wp_ajax_pag_post_preview', 'pag_post_preview');
function pag_post_preview(){
	$id = intval($_POST['id']);
	$post = get_post($id);
	$content =  apply_filters('the_content', esc_html($post->post_content));
	$output = "<tr class=\"pag_preview\" id=\"preview-{$post->ID}\">";
	$output.= "    <td colspan=\"7\">$content</td>\n";
	$output.= "</tr>";
	echo $output;
	die();
}
//Remove Post
add_action('wp_ajax_pag_post_remove', 'pag_post_remove');
function pag_post_remove(){
	$id = intval($_POST['id']);
	wp_delete_post($id);
	$response = json_encode( array( 'success' => true ) );
	header( "Content-Type: application/json" );
	echo $response;
	die();
}
//Approve Post
add_action('wp_ajax_pag_post_approve', 'pag_post_approve');
function pag_post_approve(){
	$id = intval($_POST['id']);
	wp_publish_post($id);
	$response = json_encode( array( 'success' => true ) );
	header( "Content-Type: application/json" );
	echo $response;
	die();
}

//Recaptcha Verify a Response Token
function pag_rcverify($response_token) {
	$is_human = false;
		$url = 'https://www.google.com/recaptcha/api/siteverify';
		$apiresponse = wp_safe_remote_post( $url, array(
				'body' => array(
				'secret' => get_option('rc-secret-key'),
				'response' => $response_token,
				'remoteip' => $_SERVER['REMOTE_ADDR'] ) ) );

		if ( 200 != wp_remote_retrieve_response_code( $apiresponse ) ) {
			return $is_human;
		}

		$apiresponse = wp_remote_retrieve_body( $apiresponse );
		$apiresponse = json_decode( $apiresponse, true );

		$is_human = isset( $apiresponse['success'] ) && true == $apiresponse['success'];
		return $is_human;
}

//Ajax Function Frontend
add_action('wp_ajax_pag_post', 'pag_post');
add_action('wp_ajax_nopriv_pag_post', 'pag_post' );
function pag_post(){
	$is_human = true;
	//ccheck nonce
	if (! wp_verify_nonce($_POST['post_nonce'], 'pagnonce') ) die('Security check');
	//check recpatcha
    if (get_option('rc-secret-key') != "") {
		$is_human = pag_rcverify($_POST['g-recaptcha-response']);
	}
	$content = trim(get_option('prepost-code')).trim($_POST['pagcontent']).trim(get_option('afterpost-code'));
	$content = sanitize_text_field($content);
	$title = sanitize_title($_POST['pagtitle']);
	if (get_option('category-select') == 1) {
		$post = array(  'post_title' => $title,
						'post_content' => $content ,
						'post_type' => 'post',
						'post_category' => array(intval($_POST['categoryid'])),
						'post_status' => 'pending');
	} else {
		$post = array(  'post_title' => $title,
						'post_content' => $content ,
						'post_type' => 'post',
						'post_status' => 'pending');
	}
	if ( $is_human ) {
		$id = wp_insert_post( $post, false );
		$response = json_encode( array( 'success' => true ,
								    'msg' => get_option('after-post-msg') ) );
	} else {
		$response = json_encode( array( 'error' => true ,
								    'msg' => __('Spam Check Error', 'post-as-guest') ) );		
	}
	header( "Content-Type: application/json" );
	echo $response;
	if (get_option('notify-admin') == 1 and $is_human) {
		pag_notify($title);
	}
	die();
}

function pag_notify($posttitle){
	//prepare
	$msg = __('A new guest post is added at ','post-as-guest').get_bloginfo()."<br /><br />";
	$msg.= __('Title', 'post-as-guest').': '.$posttitle."<br /><br />";
	$msg.= site_url();
	$title = get_bloginfo().' - '.__('New Guest Post','post-as-guest');
	//header
	$header1  = 'MIME-Version: 1.0' . "\r\n";
	$header1 .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";
	$header1 .= 'From: '.get_bloginfo().' <'.get_bloginfo('admin_email').'>'."\r\n";
	//send
	mail(get_option('notify-email'), $title, $msg, $header1);
	return true;
}
?>