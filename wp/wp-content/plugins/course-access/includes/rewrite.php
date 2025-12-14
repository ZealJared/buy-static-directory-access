<?php
// Rewrite rules for Course Access plugin

// Add rewrite rule for /courses/{course-slug}/{path}
add_action('init', function () {
	       add_rewrite_rule(
		       '^courses/([^/]+)/(.*)$',
		       'index.php?course=$matches[1]&course_path=$matches[2]',
		       'top'
	       );
	       add_rewrite_rule(
		       '^courses/([^/]+)/?$',
		       'index.php?course=$matches[1]',
		       'top'
	       );
});

// Register query vars
add_filter('query_vars', function ($vars) {
	$vars[] = 'course';
	$vars[] = 'course_path';
	return $vars;
});

// Flush rewrite rules on plugin activation
register_activation_hook(CA_PLUGIN_DIR . '../course-access.php', function () {
	flush_rewrite_rules();
});
register_deactivation_hook(CA_PLUGIN_DIR . '../course-access.php', function () {
	flush_rewrite_rules();
});
