<?php if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access forbidden.' );
}

include_once 'functions.php';

add_action( 'wp_loaded', 'brizy_initialize_Brizy_Public_Api' );

function brizy_initialize_Brizy_Public_Api() {
	$pid  = brizy_get_current_post_id();
	$post = null;
	try {

		$project = Brizy_Editor_Project::get();

		if ( $pid ) {
			$post = Brizy_Editor_Post::get( $pid );
		}

	} catch ( Exception $e ) {
		return;
	}

	$api_instance = Brizy_Editor_API::instance( $project, $post );

	$api_instance->initialize();
}