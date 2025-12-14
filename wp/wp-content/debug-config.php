<?php
// Enable WP_DEBUG and logging
@ini_set('display_errors', 1);
if (!defined('WP_DEBUG')) {
	define('WP_DEBUG', true);
}
if (!defined('WP_DEBUG_LOG')) {
	define('WP_DEBUG_LOG', true);
}
if (!defined('WP_DEBUG_DISPLAY')) {
	define('WP_DEBUG_DISPLAY', false);
}
