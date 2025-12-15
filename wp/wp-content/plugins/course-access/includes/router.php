<?php
// Prevent WordPress from redirecting course file URLs (e.g., .css, .js, .html) to avoid breaking static file access
add_filter('redirect_canonical', function ($redirect_url, $requested_url) {
       if (preg_match('#/courses/[^/]+/.+\.[a-zA-Z0-9]+$#', $requested_url)) {
	       return false; // Block canonical redirect for course file URLs
       }
       return $redirect_url;
}, 10, 2);

// Register the router handler for both pretty URLs and query-string access
add_action('template_redirect', 'ca_course_router_handler');
add_action('init', 'ca_course_router_handler');

// Main handler for serving static course files with access control
function ca_course_router_handler()
{
       // Get the course slug from query vars or GET params
	$course_slug = get_query_var('course');
	if (!$course_slug && isset($_GET['course'])) {
		$course_slug = sanitize_title($_GET['course']);
	}
	if (!$course_slug) {
		return;
	}
       // Get the requested file path within the course
       $req_path = get_query_var('course_path');
       if (!$req_path && isset($_GET['course_path'])) {
	       $req_path = ltrim($_GET['course_path'], '/');
       }

       // Look up the course post by slug
       $course = get_page_by_path($course_slug, OBJECT, 'course');
       if (!$course) {
	       status_header(404);
	       echo 'Course not found';
	       exit;
       }

       $course_id = $course->ID;

       // If no file path is requested, redirect to the course's default route (e.g., start page)
       if (!$req_path) {
	       $default = get_post_meta($course_id, 'course_default_route', true);
	       if (!$default) {
		       $default = 'html/start.html';
	       }
	       $permalink = rtrim(get_permalink($course_id), '/');
	       $redirect = preg_replace('#/course/#', '/courses/', $permalink) . '/' . ltrim($default, '/');
	       wp_redirect($redirect, 302);
	       exit;
       }

       // Get the WooCommerce product ID associated with the course
       $product_id = get_post_meta($course_id, 'course_product_id', true);
       if (!$product_id) {
	       status_header(404);
	       echo 'Course product not configured';
	       exit;
       }

       // Build the base directory path for course files
       $base_dir = CA_CONTENT_DIR . $product_id . '/';
       $base_real = realpath($base_dir);
       if (!$base_real) {
	       status_header(500);
	       echo 'Course storage missing';
	       exit;
       }

       // Resolve the requested file path safely (prevents directory traversal)
       $req_path = urldecode($req_path);
       $file_path = realpath($base_dir . $req_path);
       if (!$file_path || strpos($file_path, $base_real) !== 0) {
	       status_header(404);
	       echo 'File not found';
	       exit;
       }

       // If the path is a directory, serve index.html from that directory
       if (is_dir($file_path)) {
	       $file_path = rtrim($file_path, '/\\') . '/index.html';
	       if (!file_exists($file_path)) {
		       status_header(404);
		       echo 'index.html missing';
		       exit;
	       }
       }

       // Ensure the resolved path is a file
       if (!is_file($file_path)) {
	       status_header(404);
	       echo 'File missing';
	       exit;
       }

	       // Check if the current user has access to the course
	       require_once CA_PLUGIN_DIR . 'includes/access.php';
	       if (!course_user_has_access($course_id, get_current_user_id())) {
		       // Redirect to WooCommerce product page for purchase if no access
		       $product_url = get_permalink($product_id);
		       if ($product_url) {
			       wp_redirect($product_url, 302);
			       exit;
		       } else {
			       status_header(403);
			       echo 'Access denied';
			       exit;
		       }
	       }

       // Determine the correct MIME type for the file (ensures proper browser handling)
       $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
	       $mime_map = [
		       'html' => 'text/html; charset=utf-8',
		       'htm' => 'text/html; charset=utf-8',
		       'css' => 'text/css; charset=utf-8',
		       'js' => 'application/javascript; charset=utf-8',
		       'mjs' => 'application/javascript; charset=utf-8',
		       'json' => 'application/json; charset=utf-8',
		       'svg' => 'image/svg+xml',
		       'png' => 'image/png',
		       'jpg' => 'image/jpeg',
		       'jpeg' => 'image/jpeg',
		       'gif' => 'image/gif',
		       'webp' => 'image/webp',
		       'mp3' => 'audio/mpeg',
		       'mp4' => 'video/mp4',
		       'woff' => 'font/woff',
		       'woff2' => 'font/woff2',
		       'ttf' => 'font/ttf',
	       ];
	       $mime = $mime_map[$ext] ?? 'application/octet-stream';

	       // Output file with proper headers
	       header('X-Content-Type-Options: nosniff');
	       header('Content-Type: ' . $mime);
	       header('Content-Length: ' . filesize($file_path));
	       readfile($file_path);
	       exit;
}
