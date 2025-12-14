<?php
// ZIP validation and extraction for Course Access plugin

// Handle ZIP upload, validate, extract, and update post meta
// $folder is now the WooCommerce product ID (string)
function ca_handle_zip_upload($file, $folder, $post_id) {
	// Check MIME and extension
	$allowed = array('application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream');
	$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
	$mime = $file['type'];
	if ($ext !== 'zip' || !in_array($mime, $allowed)) {
		return new WP_Error('invalid_zip', 'Invalid ZIP file.');
	}

	// Prepare target directory
	$base_dir = CA_CONTENT_DIR;
	if (!is_dir($base_dir)) {
		wp_mkdir_p($base_dir);
	}
	$course_dir = $base_dir . $folder . '/';

	// Prevent path traversal
	if (strpos(realpath($base_dir), realpath(WP_CONTENT_DIR)) !== 0) {
		return new WP_Error('invalid_path', 'Invalid course content directory.');
	}

	// Remove old content
	if (is_dir($course_dir)) {
		ca_rrmdir($course_dir);
	}
	wp_mkdir_p($course_dir);


	// Move uploaded file to temp
	$tmp = $file['tmp_name'];
	$zip = new ZipArchive();
	if ($zip->open($tmp) !== true) {
		return new WP_Error('zip_open', 'Failed to open ZIP file.');
	}

	// Extract and check for path traversal
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$entry = $zip->getNameIndex($i);
		if (strpos($entry, '../') !== false || strpos($entry, '..\\') !== false) {
			$zip->close();
			return new WP_Error('zip_traversal', 'ZIP contains invalid paths.');
		}
	}

	// Always extract to a subdirectory named after the product ID
	// If the ZIP contains a single top-level folder matching the product ID, extract as normal
	// If not, extract all contents into the $course_dir/{product_id}/
	$has_folder = false;
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$entry = $zip->getNameIndex($i);
		if (preg_match('#^' . preg_quote($folder, '#') . '/#', $entry)) {
			$has_folder = true;
			break;
		}
	}

	if ($has_folder) {
		// ZIP already contains a top-level folder matching the product ID, extract as normal
		if (!$zip->extractTo($base_dir)) {
			$zip->close();
			return new WP_Error('zip_extract', 'Failed to extract ZIP.');
		}
	} else {
		// Extract all files into $course_dir
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			$target = $course_dir . $entry;
			if (substr($entry, -1) === '/') {
				// Directory
				wp_mkdir_p($target);
			} else {
				// File
				$dir = dirname($target);
				if (!is_dir($dir)) {
					wp_mkdir_p($dir);
				}
				copy('zip://' . $tmp . '#' . $entry, $target);
			}
		}
	}
	$zip->close();

	// Save path in post meta
	update_post_meta($post_id, 'course_path', $course_dir);
	return true;
}

// Recursively remove directory
function ca_rrmdir($dir) {
	if (!is_dir($dir)) return;
	$files = array_diff(scandir($dir), array('.', '..'));
	foreach ($files as $file) {
		$path = "$dir/$file";
		if (is_dir($path)) {
			ca_rrmdir($path);
		} else {
			unlink($path);
		}
	}
	rmdir($dir);
}
