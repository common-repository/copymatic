<?php
/*
Plugin Name: Copymatic
Plugin URI: https://copymatic.ai
Description: Your favorite AI-powered content writer. Generate engaging and quality content from blog articles to landing pages.
Version: 2.0
Author: Copymatic
Author URI: https://copymatic.ai
Text Domain: copymatic
*/
define( 'COPYMATIC_PLUGIN_DIR_URL', plugin_dir_url(__FILE__) );
define( 'COPYMATIC_PLUGIN_DIR_PATH', plugin_dir_path(__FILE__) );
define( 'COPYMATIC_API_URL', 'https://api.copymatic.ai');
define( 'COPYMATIC_ACTIONS_API_URL', 'https://wp-api.copymatic.ai/');

add_action('admin_menu', 'copymatic_admin_menu');
function copymatic_admin_menu() {
	add_menu_page(
		'Copymatic', 
		__('Copymatic', 'copymatic'), 
		'manage_options', 
		'copymatic-menu', 
		'copymatic_homepage',
		'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="20" height="20" viewBox="0 0 70 71.6" style="enable-background:new 0 0 70 71.6;" xml:space="preserve" fill="#f0f0f1"><g><path d="M59,0H30c-4.4,2.1-6.7,6-7,11.7h29C56.4,9.7,58.7,5.7,59,0L59,0z"/><path d="M36,29.9H7c-4.4,2.1-6.7,6-7,11.7h29C33.4,39.6,35.7,35.7,36,29.9L36,29.9z"/><path d="M58.1,59.9h-29c-4.4,2.1-6.7,6-7,11.7h29C55.5,69.6,57.8,65.6,58.1,59.9L58.1,59.9z"/><path d="M69,15H18.1c-4.4,2.1-6.7,6-7,11.7H62C66.4,24.7,68.7,20.7,69,15L69,15z"/><path d="M70,44.9H19.1c-4.4,2.1-6.7,6-7,11.7H63C67.4,54.6,69.7,50.7,70,44.9L70,44.9z"/></g></svg>')
	);
	
}

function copymatic_homepage() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized user');
	}
	if (isset($_POST['copymatic_nonce_field']) && !wp_verify_nonce($_POST['copymatic_nonce_field'], 'copymatic_nonce')) {
        wp_die('Security check failed; please try again.');
    }
	if(!empty($_POST)) {
		if (isset($_POST['copymatic_apikey'])) {
			update_option( 'copymatic_apikey', sanitize_text_field( $_POST['copymatic_apikey'] ) );
		}
	}
	if(!empty(get_option('copymatic_apikey'))){
		include(COPYMATIC_PLUGIN_DIR_PATH."inc/articles_dashboard.php");
	}else{
		include(COPYMATIC_PLUGIN_DIR_PATH."inc/home.php");
	}
}

function enqueuing_copymatic_admin_scripts() {
    wp_enqueue_style('copymatic-admin-css', COPYMATIC_PLUGIN_DIR_URL . '/css/admin.css?v=' . time());
    wp_enqueue_style('copymatic-css', COPYMATIC_PLUGIN_DIR_URL . '/css/main.css?v=' . time());
    wp_enqueue_script('sweet-alert', COPYMATIC_PLUGIN_DIR_URL . '/js/sweetalert.min.js?v=' . time());
    wp_enqueue_script('copymatic-js', COPYMATIC_PLUGIN_DIR_URL . '/js/main.js?v=' . time());
    $localize_array = array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('copymatic_nonce'),
        'posts_admin_url' => admin_url()
    );
    $api_key = get_option('copymatic_apikey');
    if (!empty($api_key)) {
        $localize_array['copymatic_api_key'] = $api_key;
    }
    wp_localize_script('copymatic-js', 'copymatic_ajax_object', $localize_array);
}
add_action('admin_enqueue_scripts', 'enqueuing_copymatic_admin_scripts');

add_action("wp_ajax_load_copymatic_articles", "load_copymatic_articles");

function load_copymatic_articles() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized user');
	}
	$api_key = get_option( 'copymatic_apikey');
	if(!empty($api_key)){
		$response = wp_remote_get(COPYMATIC_ACTIONS_API_URL.'?apikey='.$api_key.'&action=get_all_articles');
		$json_body = wp_remote_retrieve_body($response);
		wp_send_json_success($json_body); // returning JSON body content from API for Javascript
		wp_die();
	}
}

// Register REST API endpoint
add_action('rest_api_init', function () {
    register_rest_route('copymatic/v1', '/article/', array(
        'methods' => 'POST',
        'callback' => 'copymatic_receive_post',
        'permission_callback' => 'copymatic_permissions_check'
    ));
});

function copymatic_permissions_check(WP_REST_Request $request) {
    $data = json_decode($request->get_body(), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', 'Invalid JSON', array('status' => 400));
    }

    $saved_website_key = get_option('copymatic_website_key');
    $request_website_key = $data['website_key'] ?? null;

    $saved_api_key = get_option('copymatic_apikey');
    $request_api_key = $request->get_header('apikey');

    return !empty($request_api_key) && $request_api_key === $saved_api_key && 
           !empty($request_website_key) && $request_website_key === $saved_website_key;
}

function copymatic_receive_post(WP_REST_Request $request) {
    $data = json_decode($request->get_body(), true);
    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['title'], $data['content'])) {
        return new WP_Error('invalid_data', 'Invalid JSON or missing title/content', array('status' => 400));
    }
	$post_status = 'draft';
	if(!empty($data['publish_status'])){
		$post_status = $data['publish_status'];
	}
    // Create the post array
    $post_arr = array(
        'post_title'    => sanitize_text_field($data['title']),
        'post_content'  => wp_kses_post($data['content']),
        'post_status'   => $post_status,
        'post_author'   => get_current_user_id(),
        'post_type'     => 'post'
    );

    // Insert the post
    $post_id = wp_insert_post($post_arr, true);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    // Handle the featured image if provided
    if (!empty($data['featured_image'])) {
        $image_url = esc_url_raw($data['featured_image']);
        $image_id = copymatic_handle_featured_image($image_url, $post_id);
        if (is_wp_error($image_id)) {
            return $image_id;
        }
        set_post_thumbnail($post_id, $image_id);
    }
	
	if (class_exists('RankMath')) {
		if(!empty($post['meta_title'])){
			update_post_meta($post_id, 'rank_math_title', $post['meta_title']);
		}
		if(!empty($post['meta_description'])){
			update_post_meta($post_id, 'rank_math_description', $post['meta_description']);
		}
		if(!empty($post['keyword'])){
			update_post_meta($post_id, 'rank_math_focus_keyword', $post['keyword']);
		}
	}
	if (defined('WPSEO_VERSION')) {
		if(!empty($post['meta_title'])){
			update_post_meta($post_id, '_yoast_wpseo_title', $post['meta_title']);
		}
		if(!empty($post['meta_description'])){
			update_post_meta($post_id, '_yoast_wpseo_metadesc', $post['meta_description']);
		}
	}

    return new WP_REST_Response(array('success'=> true, 'post_id' => $post_id), 200);
}

// Function to handle setting the featured image from a URL
function copymatic_handle_featured_image($image_url, $post_id) {
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download the image and get the file path
    $tmp = download_url($image_url);
    if (is_wp_error($tmp)) {
        return $tmp;  // Return error
    }

    // Get file name and extension
    $file_array = array();
    preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $image_url, $matches);
    $file_array['name'] = basename($matches[0]);
    $file_array['tmp_name'] = $tmp;

    // Check for file type
    if (empty($file_array['name'])) {
        @unlink($file_array['tmp_name']);
        return new WP_Error('invalid_image', 'Invalid image URL');
    }

    // Do the validation and storage stuff
    $id = media_handle_sideload($file_array, $post_id);
    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
        return $id;
    }

    return $id;
}

add_action("wp_ajax_check_copymatic_api", "check_copymatic_api");

function check_copymatic_api() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized user');
	}
	if(!check_ajax_referer('copymatic_nonce', '_ajax_nonce', false)){
		wp_die('Security check failed; please try again.');
	}
	$api_key = isset($_POST['apikey']) ? sanitize_text_field(trim($_POST['apikey'])) : '';
	if(!empty($api_key) && is_user_logged_in()){
		$website_name = get_bloginfo('name');
		$plugins_url = plugins_url();
		$home_url = home_url();
		$endpoint_url = $home_url.'/wp-json/copymatic/v1/article/';
		$response = wp_remote_get(COPYMATIC_ACTIONS_API_URL.'?apikey='.$api_key.'&website_name='.$website_name.'&endpoint_url='.urlencode($endpoint_url).'&action=check_key_save_endpoint');
		$json_body = wp_remote_retrieve_body($response);
		$json = json_decode($json_body, true);
		if(!empty($json['website_key'])){
			update_option('copymatic_website_key', $json['website_key']);
			wp_send_json_success(array('success'=>1));
		}
		wp_die();
	}
}

add_action("wp_ajax_copymatic_import_article", "copymatic_import_article");

function copymatic_import_article() {
	if (!current_user_can('edit_posts')) {
		wp_die('Unauthorized user');
	}
	$api_key = get_option( 'copymatic_apikey');
	if(!empty($api_key) && is_user_logged_in() && check_ajax_referer('copymatic_nonce', '_ajax_nonce', false)){
		if(!empty($_POST['id'])){
			$id = intval($_POST['id']);
			$response = wp_remote_get(COPYMATIC_ACTIONS_API_URL.'?apikey='.$api_key.'&action=get_article_content&id='.$id);
			$json_body = wp_remote_retrieve_body($response);

			$article = json_decode($json_body, true);
			$title = $content = '';
			foreach($article as $section){
				if(!empty($section['title'])){
					$title = trim($section['title']);
				}
				if(!empty($section['headline'])){
					$content .= '<h2>'.trim($section['headline']).'</h2>';
				}
				if(!empty($section['content'])){
					$line_breaks = explode(PHP_EOL, $section['content']);
					foreach($line_breaks as $ln){
						$content .= '<p>'.trim($ln).'</p>';
					}
				}
			}
			
			$user_ID = get_current_user_id();
			$new_post = array(
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'draft',
				'post_date' => gmdate('Y-m-d H:i:s'),
				'post_author' => $user_ID,
				'post_type' => 'post',
				'post_category' => array(0)
			);
			$post_id = wp_insert_post($new_post);
			
			update_post_meta($post_id, 'copymatic_post_id', $id);
			
			if(!empty($post_id)){
				wp_send_json_success(array('post_id'=>$post_id));
			}else{
				wp_send_json_error();
			}
		}
	}
   	wp_die();
}

add_action("wp_ajax_copymatic_import_generated_article", "copymatic_import_generated_article");

function copymatic_import_generated_article() {
	if (!current_user_can('edit_posts')) {
		wp_die('Unauthorized user');
	}
	$api_key = get_option( 'copymatic_apikey');
	if(!empty($api_key) && is_user_logged_in() && check_ajax_referer('copymatic_nonce', '_ajax_nonce', false)){
		if(!empty($_POST['id'])){
			$id = intval($_POST['id']);
			$response = wp_remote_get(COPYMATIC_ACTIONS_API_URL.'?apikey='.$api_key.'&action=get_article_content&id='.$id);
			$content = wp_remote_retrieve_body($response);
			$title = $_POST['title'];
			
			$user_ID = get_current_user_id();
			$new_post = array(
				'post_title' => $title,
				'post_content' => $content,
				'post_status' => 'draft',
				'post_date' => gmdate('Y-m-d H:i:s'),
				'post_author' => $user_ID,
				'post_type' => 'post',
				'post_category' => array(0)
			);
			$post_id = wp_insert_post($new_post);
			
			update_post_meta($post_id, 'copymatic_post_id', $id);
			
			if(!empty($post_id)){
				wp_send_json_success(array('post_id'=>$post_id));
			}else{
				wp_send_json_error();
			}
		}
	}
   	wp_die();
}

add_action("wp_ajax_copymatic_delete_article", "copymatic_delete_article");

function copymatic_delete_article() {
	if (!current_user_can('edit_posts')) {
		wp_die('Unauthorized user');
	}
	$api_key = get_option( 'copymatic_apikey');
	if(!empty($api_key) && is_user_logged_in() && check_ajax_referer('copymatic_nonce', '_ajax_nonce', false)){
		if(!empty($_POST['id'])){
			$id = intval($_POST['id']);
			$response = wp_remote_get(COPYMATIC_ACTIONS_API_URL.'?apikey='.$api_key.'&action=delete_article&id='.$id);
			$json_body = wp_remote_retrieve_body($response);
			$return = json_decode($json_body, true);
			if($return['success']==1){
				wp_send_json_success();
			}else{
				wp_send_json_error();
			}
			wp_die();
		}
	}
}

add_action("wp_ajax_copymatic_api_request", "copymatic_api_request");

function copymatic_api_request() {
	if (!current_user_can('manage_options')) {
		wp_die('Unauthorized user');
	}
	if(!check_ajax_referer('copymatic_nonce', '_ajax_nonce', false)){
		wp_die('Security check failed; please try again.');
	}
    $api_key = get_option('copymatic_apikey');
    if (!empty($api_key)) {
        unset($_POST['action']);
        if (!empty($_POST['business_description'])) {
            update_option('copymatic_business_description', sanitize_text_field($_POST['business_description']));
        }
        if (!empty($_POST['audience'])) {
            update_option('copymatic_audience', sanitize_text_field($_POST['audience']));
        }
        
        $postfields = wp_json_encode($_POST);
        $headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        );

        $response = wp_remote_post(COPYMATIC_API_URL, array(
            'method'      => 'POST',
            'timeout'     => 30,
            'redirection' => 10,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => $headers,
            'body'        => $postfields,
            'cookies'     => array()
            )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            echo esc_html__("Something went wrong: ", "copymatic") . esc_html($error_message);
        } else {
            $r = wp_remote_retrieve_body($response);
            $return = $r ? $r : wp_json_encode(array('success' => false));
            echo esc_js($return); 
        }
    } else {
        echo wp_json_encode(array('success' => false));
    }
    wp_die();
}

register_deactivation_hook(__FILE__, 'copymatic_deactivate');
function copymatic_deactivate() {
    delete_option('copymatic_apikey');
	delete_option('copymatic_website_key');
}

function wpdocs_register_meta_boxes() {
	$api_key = get_option( 'copymatic_apikey');
	if(!empty($api_key)){
    	add_meta_box( 'copymatic-post-box', 'Copymatic AI', 'copymatic_post_page_callback', array('post','page'), 'side', 'core');
	}
}
add_action( 'add_meta_boxes', 'wpdocs_register_meta_boxes' );

function copymatic_post_page_callback($post){
	$html = '';
	$api_key = get_option('copymatic_apikey');
	$business_description = '';
	$business_description = get_option('copymatic_business_description');
	$post_id = $post->ID;
	$description = '';
	$audience = '';
	$description = get_post_meta($post_id, 'copymatic_description', true);
	$audience = get_option('copymatic_audience');
	$languages = get_copymatic_languages();
	$html .= '<label for="language">'. esc_html__('Language', 'copymatic') .'</label>';
	$html .= '<select name="language" id="copymatic_language">';
	foreach($languages as $language){
		$html .= '<option value="'.$language['name'].'">'.$language['name'].'</option>';
	}
	$html .= '</select>';
	if($post->post_type == 'post'){
		$html .= '<label for="copymatic_description">'. esc_html__('Article Description', 'copymatic') .'<div></div></label>';
		$html .= '<textarea name="copymatic_description" id="copymatic_description" rows="3" maxlength="200" placeholder="'.esc_html__('Briefly explain what your article is about, i.e. a blog article about...', 'copymatic').'">'. esc_attr($description) .'</textarea>';
		$html .= '<label for="copymatic_model">'. esc_html__('What would you like to generate?', 'copymatic') .'</label>';
		$html .= '<select name="copymatic_model" id="copymatic_model">';
		$html .= '<option value="">-- Select --</option>';
		$html .= '<option value="blog-titles">Blog Titles</option>';
		$html .= '<option value="blog-titles-listicles">Blog Titles (Listicles)</option>';
		$html .= '<option value="blog-outline">Blog Outlines</option>';
		$html .= '<option value="blog-intros">Blog Introductions</option>';
		$html .= '<option value="subheading-paragraph">Paragraph</option>';
		$html .= '<option value="meta-descriptions">Meta Descriptions</option>';
		$html .= '</select>';
		$html .= '<div class="copymatic_additionals">';
		$html .= '<div class="copymatic_meta-descriptions" style="display:none">';
		$html .= '<label for="website_description">'. esc_html__('Website Description', 'copymatic') .'</label>';
		$html .= '<textarea name="business_description" maxlength="200" id="business_description" placeholder="Briefly describe what your website or business is about." rows="3">'.$business_description.'</textarea>';
		$html .= '<label for="keyword">'. esc_html__('Keyword', 'copymatic') .'</label>';
		$html .= '<input type="text" value="" placeholder="car insurance, nyc business lawyer,..." name="keyword" id="keyword">';
		$html .= '</div>';
		$html .= '<div class="copymatic_subheading-paragraph" style="display:none">';
		$html .= '<label for="subheading">'. esc_html__('Subheading / Bullet Point', 'copymatic') .'</label>';
		$html .= '<textarea name="subheading" maxlength="200" id="subheading" placeholder="I.e. Boost conversions by writing the best content" rows="2"></textarea>';
		$html .= '</div>';
		$html .= '</div>';
	}else if($post->post_type == 'page'){
		$html .= '<label for="audience">'. esc_html__('Audience', 'copymatic') .'</label>';
		$html .= '<input type="text" value="'.$audience.'" placeholder="women, fitness coaches, designers..." name="audience" id="audience">';
		$html .= '<label for="tone">'. esc_html__('Tone of voice', 'copymatic') .'</label>';
		$html .= '<select name="tone" id="tone"><option value="professional">Professional</option><option value="childish">Childish</option><option value="luxurious">Luxurious</option><option value="friendly">Friendly</option><option value="confident">Confident</option></select>';
		$html .= '<label for="description">'. esc_html__('Website or Business Description', 'copymatic') .'</label>';
		$html .= '<textarea name="description" maxlength="200" id="copymatic_description" placeholder="Briefly describe what your website or business is about." rows="3">'.$business_description.'</textarea>';
		$html .= '<label for="copymatic_model">'. esc_html__('What would you like to generate?', 'copymatic') .'</label>';
		$html .= '<select name="copymatic_model" id="copymatic_model">';
		$html .= '<option value="">-- Select --</option>';
		$html .= '<option value="meta-descriptions">Meta Descriptions</option>';
		$html .= '<option value="about-us">About Us</option>';
		$html .= '<option value="faq">FAQs</option>';
		$html .= '<option value="faq-answers">FAQ Answers</option>';
		$html .= '<option value="testimonials">Testimonials</option>';
		$html .= '<option value="attention-interest-desire-action">AIDA Formula</option>';
		$html .= '<option value="pain-agitate-solution">PAS Formula</option>';
		$html .= '<option value="quest">QUEST Formula</option>';
		$html .= '</select>';
		$html .= '<div class="copymatic_additionals">';
		$html .= '<div class="copymatic_meta-descriptions" style="display:none">';
		$html .= '<label for="keyword">'. esc_html__('Keyword', 'copymatic') .'</label>';
		$html .= '<input type="text" value="" placeholder="car insurance, nyc business lawyer,..." name="keyword" id="keyword">';
		$html .= '</div>';
		$html .= '<div class="copymatic_faq-answers" style="display:none">';
		$html .= '<label for="question">'. esc_html__('Question', 'copymatic') .'</label>';
		$html .= '<input type="text" value="" placeholder="Your question here..." name="question" id="question">';
		$html .= '</div>';
		$html .= '</div>';
	}
	$html .= '<div class="copymatic_actions">';
	$html .= '<button class="button button-primary button-large copymatic-btn copymatic-generate">Generate</button>';
	$html .= '</div>';
	if($post->post_type == 'post'){
		$page_type = 'blog article';
	}else if($post->post_type == 'page'){
		$page_type = 'landing page';
	}
	$html .= '<input type="hidden" name="copymatic_page_type" id="copymatic_page_type" value="'.$page_type.'">';
	$html .= '<input type="hidden" name="copymatic_website_name" id="copymatic_website_name" value="'.get_bloginfo('name').'">';
	if(!empty($api_key)){
		$html .= '<input type="hidden" name="copymatic_api_key" id="copymatic_api_key" value="'.$api_key.'">';
	}
	echo esc_html($html);
}

function copymatic_modal_function() {
	$screen = get_current_screen();
	if ($screen->base == 'post'){
?>
<div class="copymatic-modal"><div class="copymatic-modal-header"><h1></h1><button class="button button-primary button-large copymatic-btn copymatic_regenerate" data-model=""><?php esc_html_e("Regenerate", "copymatic"); ?></button><button type="button" class="media-modal-close"><span class="media-modal-icon"><span class="screen-reader-text"><?php esc_html_e("Close", "copymatic"); ?></span></span></button></div><div class="copymatic-modal-ideas"></div></div>
<?php
	}
}
add_action('admin_footer', 'copymatic_modal_function');

function get_copymatic_languages(){
	$languages = array(
		"english-us"=>array("name"=>"English (US)", "flag"=>"🇺🇸"),
		"english-gb"=>array("name"=>"English (UK)", "flag"=>"🇬🇧"),
		"french"=>array("name"=>"French", "flag"=>"🇫🇷"),
		"spanish"=>array("name"=>"Spanish", "flag"=>"🇪🇸"),
		"german"=>array("name"=>"German", "flag"=>"🇩🇪"),
		"italian"=>array("name"=>"Italian", "flag"=>"🇮🇹"),
		"dutch"=>array("name"=>"Dutch", "flag"=>"🇳🇱"),
		"portuguese"=>array("name"=>"Portuguese", "flag"=>"🇵🇹"),
		"portuguese-br"=>array("name"=>"Portuguese (BR)", "flag"=>"🇧🇷"),
		"swedish"=>array("name"=>"Swedish", "flag"=>"🇸🇪"),
		"norwegian"=>array("name"=>"Norwegian", "flag"=>"🇩🇰"),
		"danish"=>array("name"=>"Danish", "flag"=>"🇩🇰"),
		"finnish"=>array("name"=>"Finnish", "flag"=>"🇫🇮"),
		"romanian"=>array("name"=>"Romanian", "flag"=>"🇷🇴"),
		"czech"=>array("name"=>"Czech", "flag"=>"🇨🇿"),
		"slovak"=>array("name"=>"Slovak", "flag"=>"🇸🇰"),
		"slovenian"=>array("name"=>"Slovenian", "flag"=>"🇸🇮"),
		"hungarian"=>array("name"=>"Hungarian", "flag"=>"🇭🇺"),
		"polish"=>array("name"=>"Polish", "flag"=>"🇵🇱"),
		"turkish"=>array("name"=>"Turkish", "flag"=>"🇹🇷"),
		"russian"=>array("name"=>"Russian", "flag"=>"🇷🇺"),
		"hindi"=>array("name"=>"Hindi", "flag"=>"🇮🇳"),
		"thai"=>array("name"=>"Thai", "flag"=>"🇹🇭"),
		"japonese"=>array("name"=>"Japonese", "flag"=>"🇯🇵"),
		"chinese"=>array("name"=>"Chinese (Simplified)", "flag"=>"🇨🇳"),
		"korean"=>array("name"=>"Korean", "flag"=>"🇰🇷"),
		"indonesian"=>array("name"=>"Indonesian", "flag"=>"🇮🇩")
	);
	return $languages;
}