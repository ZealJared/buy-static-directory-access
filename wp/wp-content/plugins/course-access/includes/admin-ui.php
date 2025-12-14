<?php

// Register custom post type 'course'
add_action('init', function () {
	register_post_type('course', array(
		'labels' => array(
			'name' => 'Courses',
			'singular_name' => 'Course',
		),
		'public' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'exclude_from_search' => true,
		'publicly_queryable' => true, // allow front-end queries for router
		'supports' => array('title', 'slug'),
		'menu_icon' => 'dashicons-welcome-learn-more',
	));
});

// Add meta box for WooCommerce product and ZIP upload
add_action('add_meta_boxes', function () {
	add_meta_box('course_access_meta', 'Course Access', 'ca_course_meta_box', 'course', 'normal', 'default');
});

function ca_course_meta_box($post)
{
	// Nonce
	wp_nonce_field('ca_course_meta', 'ca_course_meta_nonce');

	// Get saved values
	$product_id = get_post_meta($post->ID, 'course_product_id', true);
	$course_slug = $post->post_name;

	// WooCommerce products
	if (!class_exists('WooCommerce')) {
		echo '<p style="color:red">WooCommerce is not active.</p>';
		return;
	}
	$products = wc_get_products(array('limit' => -1, 'post_status' => 'publish'));

	echo '<p><label for="ca_product_id">WooCommerce Product:</label><br />';
	echo '<select name="ca_product_id" id="ca_product_id">';
	echo '<option value="">-- Select Product --</option>';
	foreach ($products as $product) {
		$selected = ($product_id == $product->get_id()) ? 'selected' : '';
		printf('<option value="%d" %s>%s</option>', $product->get_id(), $selected, esc_html($product->get_name()));
	}
	echo '</select></p>';

	// Show ZIP upload UI only if course is saved and product is selected
	if ($post->ID && $product_id) {
		echo '<p><label for="ca_zip_file">Upload Course ZIP (AJAX, up to 10GB):</label><br />';
		echo '<input type="file" id="ca_zip_file" accept=".zip,application/zip" /> ';
		echo '<button type="button" id="ca_zip_upload_btn" class="button">Upload ZIP</button>';
		echo '<span id="ca_zip_upload_status"></span></p>';
		echo '<p style="color:#555;font-size:90%">If you have trouble uploading very large files, increase <code>upload_max_filesize</code>, <code>post_max_size</code>, and <code>max_execution_time</code> in your <code>php.ini</code>. For files over 2GB, consider uploading via SFTP and using a custom import script.</p>';
	} else {
		echo '<p style="color:#b00;"><strong>To upload course content:</strong><br>1. Choose a WooCommerce product.<br>2. Publish or update the course.<br>3. Then upload a ZIP and update again.</p>';
	}


	       // Default route field
	       $default_route = get_post_meta($post->ID, 'course_default_route', true);
	       if (!$default_route) {
		       $default_route = '/html/start.html';
	       }
	       echo '<p><label for="ca_default_route">Default Route (entry file):</label><br />';
	       echo '<input type="text" name="ca_default_route" id="ca_default_route" value="' . esc_attr($default_route) . '" style="width:300px" /> ';
	       echo '<span style="color:#555;font-size:90%">e.g. /html/start.html</span></p>';

	       // Show course slug
	       echo '<p><strong>Course Slug:</strong> <code>' . esc_html($course_slug) . '</code></p>';

	       // Show current extracted path
	       $course_path = get_post_meta($post->ID, 'course_path', true);
	       if ($course_path && is_dir($course_path)) {
		       echo '<p>Current course content: <code>' . esc_html($course_path) . '</code></p>';
	       }

	// Inline JS for AJAX upload
	?>
	<script>
		jQuery(document).ready(function ($) {
			$('#ca_zip_upload_btn').on('click', function () {
				var fileInput = document.getElementById('ca_zip_file');
				var file = fileInput.files[0];
				if (!file) {
					$('#ca_zip_upload_status').text('Please select a ZIP file.');
					return;
				}
				if (file.size > 10 * 1024 * 1024 * 1024) { // 10GB
					$('#ca_zip_upload_status').text('File is too large (max 10GB).');
					return;
				}
				var formData = new FormData();
				formData.append('action', 'ca_ajax_zip_upload');
				formData.append('ca_zip_file', file);
				formData.append('post_id', <?php echo (int) $post->ID; ?>);
				formData.append('_wpnonce', '<?php echo wp_create_nonce('ca_ajax_zip_upload'); ?>');
				$('#ca_zip_upload_status').text('Uploading... (do not close this tab)');
				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					timeout: 0, // No timeout for large files
					xhr: function () {
						var xhr = $.ajaxSettings.xhr();
						if (xhr.upload) {
							xhr.upload.addEventListener('progress', function (e) {
								if (e.lengthComputable) {
									var percent = Math.round((e.loaded / e.total) * 100);
									$('#ca_zip_upload_status').text('Uploading: ' + percent + '%');
								}
							}, false);
						}
						return xhr;
					},
					success: function (response) {
						if (response.success) {
							$('#ca_zip_upload_status').html('<span style="color:green">' + response.data + '</span>');
						} else {
							$('#ca_zip_upload_status').html('<span style="color:red">' + response.data + '</span>');
						}
					},
					error: function (xhr, status, error) {
						if (status === 'timeout') {
							$('#ca_zip_upload_status').html('<span style="color:red">Upload timed out. Try increasing max_execution_time in php.ini.</span>');
						} else {
							$('#ca_zip_upload_status').html('<span style="color:red">AJAX error: ' + error + '</span>');
						}
					}
				});
			});
		});
	</script>
	<?php
}


// Save meta box data (product selection, no ZIP upload)
add_action('save_post_course', function ($post_id) {
	if (!isset($_POST['ca_course_meta_nonce']) || !wp_verify_nonce($_POST['ca_course_meta_nonce'], 'ca_course_meta'))
		return;
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
		return;
	if (!current_user_can('edit_post', $post_id))
		return;

	       // Save product ID
	       $product_id = isset($_POST['ca_product_id']) ? intval($_POST['ca_product_id']) : '';
	       if ($product_id && get_post_type($product_id) === 'product') {
		       update_post_meta($post_id, 'course_product_id', $product_id);

		       // Set post title to match product name + ' - Directory Access'
		       $product = wc_get_product($product_id);
		       if ($product) {
			       $new_title = $product->get_name() . ' - Directory Access';
			       // Only update if different to avoid unnecessary saves
			       if (get_post_field('post_title', $post_id) !== $new_title) {
				       remove_action('save_post_course', __FUNCTION__); // Prevent infinite loop
				       wp_update_post([
					       'ID' => $post_id,
					       'post_title' => $new_title,
				       ]);
				       add_action('save_post_course', __FUNCTION__); // Re-add
			       }
		       }
	       } else {
		       delete_post_meta($post_id, 'course_product_id');
	       }

	       // Save default route
	       if (isset($_POST['ca_default_route'])) {
		       $route = trim(sanitize_text_field($_POST['ca_default_route']));
		       if (!$route) {
			       $route = '/html/start.html';
		       }
		       update_post_meta($post_id, 'course_default_route', $route);
	       }
}, 10, 1);

// AJAX handler for ZIP upload
add_action('wp_ajax_ca_ajax_zip_upload', function () {
	check_ajax_referer('ca_ajax_zip_upload');
	if (!current_user_can('edit_posts')) {
		wp_send_json_error('No permission.');
	}
	$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
	if (!$post_id) {
		wp_send_json_error('No post ID.');
	}
	if (empty($_FILES['ca_zip_file'])) {
		wp_send_json_error('No file uploaded.');
	}
	require_once CA_PLUGIN_DIR . 'includes/zip-handler.php';
	// Use course_product_id as the extraction folder
	$product_id = get_post_meta($post_id, 'course_product_id', true);
	if (empty($product_id)) {
		wp_send_json_error('No product selected. Please select a WooCommerce product and save the course before uploading.');
	}
	$folder = (string) $product_id;
	$result = ca_handle_zip_upload($_FILES['ca_zip_file'], $folder, $post_id);
	if (is_wp_error($result)) {
		wp_send_json_error($result->get_error_message());
	}
	wp_send_json_success('Course ZIP extracted successfully.');
});

// Show ZIP errors
add_action('admin_notices', function () {
	if (isset($_GET['ca_zip_error'])) {
		echo '<div class="notice notice-error"><p>' . esc_html($_GET['ca_zip_error']) . '</p></div>';
	}
	if (isset($_GET['ca_zip_success'])) {
		echo '<div class="notice notice-success"><p>' . esc_html($_GET['ca_zip_success']) . '</p></div>';
	}
	if (isset($_GET['ca_zip_debug'])) {
		echo '<div class="notice notice-info"><p>' . esc_html($_GET['ca_zip_debug']) . '</p></div>';
	}
});
