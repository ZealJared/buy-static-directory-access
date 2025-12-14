<?php
// WooCommerce access checks for Course Access plugin

// Check if user has access to course (purchased product)
function course_user_has_access($course_id, $user_id) {
	if (!$user_id || !is_user_logged_in()) return false;
	$user = get_userdata($user_id);
	if (!$user) return false;
	if (in_array('administrator', (array) $user->roles)) return true;
	$product_id = get_post_meta($course_id, 'course_product_id', true);
	if (!$product_id) return false;
	if (!function_exists('wc_customer_bought_product')) return false;
	return wc_customer_bought_product($user->user_email, $user_id, $product_id);
}
