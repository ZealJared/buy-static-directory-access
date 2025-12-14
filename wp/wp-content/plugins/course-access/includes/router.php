<?php
// Disable canonical redirects for course file URLs to prevent trailing slash
add_filter('redirect_canonical', function($redirect_url, $requested_url) {
	// Only affect URLs that look like /courses/{id}/... with a file extension
	if (preg_match('#/courses/\d+/.+\.[a-zA-Z0-9]+$#', $requested_url)) {
		return false;
	}
	return $redirect_url;
}, 10, 2);
// Request handling and file serving for Course Access plugin

// Run router on template_redirect (pretty URLs) and on init (query string access)
add_action('template_redirect', function() {
	if (get_query_var('course')) {
		ca_course_router_handler();
	}
});
add_action('init', function () {
	if (isset($_GET['course'])) {
		ca_course_router_handler();
	}
});

function ca_course_router_handler()
{

	// Prepare debug info for error output only
	$debug_incoming = [
		'get_query_var(course)' => get_query_var('course'),
		'_GET[course]' => isset($_GET['course']) ? $_GET['course'] : '(not set)',
		'get_query_var(course_path)' => get_query_var('course_path'),
		'_GET[course_path]' => isset($_GET['course_path']) ? $_GET['course_path'] : '(not set)',
		'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
	];
	$debug_incoming_html = '<pre style="background:#eef;border:1px solid #00c;padding:1em;">';
	foreach ($debug_incoming as $k => $v) {
		$debug_incoming_html .= esc_html($k) . ': ' . esc_html($v) . "\n";
	}
	$debug_incoming_html .= '</pre>';

	// Support both pretty permalinks and query-string (?course=slug)
	$course_slug = get_query_var('course');
	if (!$course_slug && isset($_GET['course'])) {
		$course_slug = sanitize_title($_GET['course']);
	}
	       if (!$course_slug) {
		       $msg = 'No course_slug in query var or query string';
		       error_log('CA DEBUG: ' . $msg);
		       status_header(404);
		       echo '<h1>404 Not Found</h1><p>' . esc_html($msg) . '</p>' . $debug_incoming_html;
		       exit;
	       }

	       // Support course_path via query string as well
	       $req_path = get_query_var('course_path');
	       if (!$req_path && isset($_GET['course_path'])) {
		       $req_path = ltrim($_GET['course_path'], '/\\');
	       }

	       // Find course by slug
	       $course = get_page_by_path($course_slug, OBJECT, 'course');
	       if (!$course) {
		       $msg = 'No course found for slug: ' . $course_slug;
		       // ...existing code...
	       }
	       $course_id = $course->ID;

	       // If no req_path, use default route from post meta (or fallback)
		       if (!$req_path) {
			       $default_route = get_post_meta($course_id, 'course_default_route', true);
			       if (!$default_route) {
				       $default_route = '/html/start.html';
			       }
				   // Instead of serving the file as the root, redirect to the correct path (no trailing slash)
				   // Ensure the redirect uses /courses/ (plural) in the URL
				   $permalink = rtrim(get_permalink($course_id), '/');
				   $default_route_clean = ltrim($default_route, '/');
				   $redirect_path = preg_replace('#/course/#', '/courses/', $permalink) . '/' . $default_route_clean;
				   // Remove trailing slash if present (always, for file URLs)
				   $redirect_path = rtrim($redirect_path, '/');
				   // Prevent WordPress canonical redirect by removing the filter before redirect
				   remove_filter('template_redirect', 'redirect_canonical');
				   header('Location: ' . $redirect_path, true, 301);
				   exit;
		       }

	// Find course by slug
	$course = get_page_by_path($course_slug, OBJECT, 'course');
	if (!$course) {
		$msg = 'No course found for slug: ' . $course_slug;
		// Gather all published course slugs for debugging
		$all_courses = get_posts([
			'post_type' => 'course',
			'post_status' => 'publish',
			'numberposts' => -1,
			'fields' => 'ids',
		]);
		$slugs = [];
		foreach ($all_courses as $cid) {
			$post = get_post($cid);
			if ($post) {
				$slugs[] = esc_html($post->post_name);
			}
		}
		$slug_list = implode(', ', $slugs);
		$debug_html = '<pre style="background:#fee;border:1px solid #c00;padding:1em;">Available course slugs: ' . $slug_list . '</pre>';
		error_log('CA DEBUG: ' . $msg . ' | Available slugs: ' . $slug_list);
		status_header(404);
		echo '<h1>404 Not Found</h1><p>' . esc_html($msg) . '</p>' . $debug_html;
		exit;
	}
	$course_id = $course->ID;
	// Use 'course_product_id' as the meta key
	$product_id = get_post_meta($course_id, 'course_product_id', true);
	if (!$product_id) {
		$msg = 'No product_id found for course ' . $course_id;
		error_log('CA DEBUG: ' . $msg);
		status_header(404);
		echo '<h1>404 Not Found</h1><p>' . esc_html($msg) . '</p>';
		exit;
	}
	$base_dir = CA_CONTENT_DIR . $product_id . '/';

	// Sanitize and resolve path
	$decoded_req_path = urldecode($req_path);
	$fs_path = $base_dir . $decoded_req_path;
	$base_dir_real = realpath($base_dir);
	$fs_path_real = realpath($fs_path);

	// Debug output for key variables (always show on error)
	$debug_vars = [
		'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '',
		'course_id' => $course_id,
		'base_dir' => $base_dir,
		'base_dir_real' => $base_dir_real,
		'fs_path' => $fs_path,
		'fs_path_real' => $fs_path_real,
		'req_path' => $req_path,
	];
	$debug_html = '<pre style="background:#fee;border:1px solid #c00;padding:1em;">';
	foreach ($debug_vars as $k => $v) {
		$debug_html .= esc_html($k) . ': ' . esc_html($v) . "\n";
	}
	$debug_html .= '</pre>';

	// Path must be inside course dir and must exist
	if (!$fs_path_real || !$base_dir_real || strpos($fs_path_real, $base_dir_real) !== 0) {
		$msg = 'Invalid or missing file: ' . $fs_path . ' (base: ' . $base_dir_real . ')';
		error_log('CA DEBUG: ' . $msg);
		status_header(404);
		echo '<h1>404 Not Found</h1><p>' . esc_html($msg) . '</p>' . $debug_html;
		exit;
	}

	// If directory, serve index.html
	if (is_dir($fs_path_real)) {
		$index_path = rtrim($fs_path_real, '/\\') . '/index.html';
		if (!file_exists($index_path)) {
			$msg = 'index.html not found in dir: ' . $index_path;
			error_log('CA DEBUG: ' . $msg);
			status_header(404);
			echo '<h1>404 Not Found</h1><p>' . esc_html($msg) . '</p>' . $debug_html;
			exit;
		}
		$fs_path_real = $index_path;
	}

	// If not a file, 404
	if (!is_file($fs_path_real)) {
		$msg = 'Requested file does not exist or is not a file: ' . $fs_path_real;
		error_log('CA DEBUG: ' . $msg);
		status_header(404);
		echo '<h1>404 Not Found</h1><p>' . esc_html($msg) . '</p>' . $debug_html;
		exit;
	}

	// Access check
	require_once CA_PLUGIN_DIR . 'includes/access.php';
	if (!course_user_has_access($course_id, get_current_user_id())) {
		$msg = 'Access denied for user ' . get_current_user_id() . ' to course ' . $course_id;
		error_log('CA DEBUG: ' . $msg);
		status_header(403);
		echo '<h1>403 Forbidden</h1><p>' . esc_html($msg) . '</p>';
		exit;
	}

	// Serve file with correct MIME
	$mime = mime_content_type($fs_path_real);
	if (!$mime)
		$mime = 'application/octet-stream';
	header('Content-Type: ' . $mime);
	header('Content-Length: ' . filesize($fs_path_real));
	readfile($fs_path_real);
	exit;
}
