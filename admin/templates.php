<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/3/18
 * Time: 3:11 PM
 */

class Brizy_Admin_Templates {

	const CP_TEMPLATE = 'brizy_template';

	public static function _init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}


	protected function __construct() {

		$this->registerCustomPostTemplate();

		if ( ! Brizy_Editor::is_user_allowed() ) {
			return;
		}

		// do other stuff here

	}


	private function registerCustomPostTemplate() {
		register_post_type( self::CP_TEMPLATE,
			array(
				'labels'              => array(
					'name'          => __( 'Templates' ),
					'singular_name' => __( 'Template' )
				),
				'public'              => false,
				'has_archive'         => false,
				'description'         => __( 'Brizy templates.' ),
				'publicly_queryable'  => Brizy_Editor::is_user_allowed(),
				'show_ui'             => true,
				'show_in_menu'        => Brizy_Admin_Settings::menu_slug(),
				'query_var'           => false,
				'rewrite'             => array( 'slug' => 'brizy-template' ),
				'capability_type'     => 'post',
				'hierarchical'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => false,
				'supports'            => array( 'title', 'author', 'thumbnail' )
			)
		);
	}


}