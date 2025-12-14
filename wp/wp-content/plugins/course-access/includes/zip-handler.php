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

	// Check if the ZIP contains only a single top-level directory
	$top_dirs = [];
	for ($i = 0; $i < $zip->numFiles; $i++) {
		$entry = $zip->getNameIndex($i);
		$parts = explode('/', $entry);
		if (count($parts) > 1 && $parts[0] !== '') {
			$top_dirs[$parts[0]] = true;
		}
	}
	if (count($top_dirs) === 1) {
		// Only one top-level directory, extract its contents into $course_dir
		$top_dir = array_keys($top_dirs)[0];
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entry = $zip->getNameIndex($i);
			if (strpos($entry, $top_dir . '/') === 0) {
				$relative = substr($entry, strlen($top_dir) + 1);
				if ($relative === false) continue;
				$target = $course_dir . $relative;
				if (substr($entry, -1) === '/') {
					wp_mkdir_p($target);
				} elseif ($relative !== '') {
					$dir = dirname($target);
					if (!is_dir($dir)) {
						wp_mkdir_p($dir);
					}
					copy('zip://' . $tmp . '#' . $entry, $target);
				}
			}
		}
	} else {
		// Extract all files into $course_dir as before
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
