<?php
/*
Plugin Name: Course Access
Description: Gate static HTML/CSS/JS courses behind WooCommerce purchases.
Version: 1.0
Author: Your Name
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

// [my_courses] shortcode for user-accessible courses
add_shortcode('my_courses', function() {
    if (!is_user_logged_in()) {
        return '<p>' . __('You must be logged in to view your courses.', 'course-access') . '</p>';
    }
    $user_id = get_current_user_id();
    $courses = get_posts(array(
        'post_type' => 'course',
        'post_status' => 'publish',
        'numberposts' => -1,
    ));
    $has_courses = false;
    $out = '<h2>' . __('My Courses', 'course-access') . '</h2><ul>';
    foreach ($courses as $course) {
        $course_id = $course->ID;
        if (function_exists('course_user_has_access') && course_user_has_access($course_id, $user_id)) {
            $has_courses = true;
            $slug = $course->post_name;
            $title = get_the_title($course_id);
            $url = home_url('/courses/' . $slug . '/');
            $out .= '<li><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></li>';
        }
    }
    if (!$has_courses) {
        $out .= '<li>' . __('You do not have access to any courses yet.', 'course-access') . '</li>';
    }
    $out .= '</ul>';
    return $out;
});

// Auto-create 'My Courses' page with shortcode if not exists
add_action('init', function() {
    $page_title = 'My Courses';
    $slug = 'my-courses';
    if (!get_page_by_path($slug)) {
        wp_insert_post(array(
            'post_title' => $page_title,
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '<!-- wp:shortcode -->[my_courses]<!-- /wp:shortcode -->',
        ));
    }
});

// Add 'My Courses' tab to WooCommerce My Account
add_filter('woocommerce_account_menu_items', function($items) {
    // Insert 'My Courses' after 'Dashboard'
    $new_items = array();
    foreach ($items as $key => $label) {
        $new_items[$key] = $label;
        if ($key === 'dashboard') {
            $new_items['my-courses'] = __('My Courses', 'course-access');
        }
    }
    return $new_items;
});

// Register endpoint
add_action('init', function() {
    add_rewrite_endpoint('my-courses', EP_ROOT | EP_PAGES);
});

// Content for 'My Courses' tab
add_action('woocommerce_account_my-courses_endpoint', function() {
    $user_id = get_current_user_id();
    if (!$user_id) {
        echo '<p>' . __('You must be logged in to view your courses.', 'course-access') . '</p>';
        return;
    }
    // Query all courses
    $courses = get_posts(array(
        'post_type' => 'course',
        'post_status' => 'publish',
        'numberposts' => -1,
    ));
    $has_courses = false;
    echo '<h2>' . __('My Courses', 'course-access') . '</h2>';
    echo '<ul>';
    foreach ($courses as $course) {
        $course_id = $course->ID;
        if (function_exists('course_user_has_access') && course_user_has_access($course_id, $user_id)) {
            $has_courses = true;
            $slug = $course->post_name;
            $title = get_the_title($course_id);
            $url = home_url('/courses/' . $slug . '/');
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($title) . '</a></li>';
        }
    }
    if (!$has_courses) {
        echo '<li>' . __('You do not have access to any courses yet.', 'course-access') . '</li>';
    }
    echo '</ul>';
});

// Define constants
if ( ! defined( 'CA_PLUGIN_DIR' ) ) {
    define( 'CA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'CA_CONTENT_DIR' ) ) {
    define( 'CA_CONTENT_DIR', WP_CONTENT_DIR . '/course-content/' );
}

// Includes
require_once CA_PLUGIN_DIR . 'includes/admin-ui.php';
require_once CA_PLUGIN_DIR . 'includes/zip-handler.php';
require_once CA_PLUGIN_DIR . 'includes/rewrite.php';
require_once CA_PLUGIN_DIR . 'includes/router.php';
require_once CA_PLUGIN_DIR . 'includes/access.php';
