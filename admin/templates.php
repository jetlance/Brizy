<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/3/18
 * Time: 3:11 PM
 */

add_filter( 'brizy_supported_post_types', 'brizy_addTemplateSupport' );

function brizy_addTemplateSupport( $posts ) {
	$posts[] = Brizy_Admin_Templates::CP_TEMPLATE;

	return $posts;
}

class Brizy_Admin_Templates {

	const CP_TEMPLATE = 'brizy_template';
	const RULE_LIST_VEIW = 'brizy_rule_list_view';

	/**
	 * @var Brizy_Editor_Post
	 */
	private $template;

	/**
	 * @var Brizy_Admin_Rules_Manager
	 */
	private $ruleManager;

	/**
	 * Brizy_Admin_Templates constructor.
	 */
	protected function __construct() {

		$this->registerCustomPostTemplate();

		if ( ! Brizy_Editor::is_user_allowed() ) {
			return;
		}

		// do other stuff here
		if ( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'registerTemplateMetaBox' ) );
			add_action( 'wp_ajax_' . self::RULE_LIST_VEIW, array( $this, 'getTemplateRuleBox' ) );
		} else {
			add_action( 'wp', array( $this, 'templateFrontEnd' ) );
		}

		$this->ruleManager = new Brizy_Admin_Rules_Manager();
	}

	/**
	 * @return Brizy_Admin_Templates
	 */
	public static function _init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function registerCustomPostTemplate() {
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

	public function registerTemplateMetaBox() {
		add_meta_box( 'template-rules', __( 'Rules' ), array( $this, 'templateRulesBox' ), self::CP_TEMPLATE );
	}

	public function templateRulesBox() {
		try {

			$templateId = null;

			$rules = array();
			if ( isset( $_GET['post'] ) ) {
				$templateId = (int) $_GET['post'];
				$rules      = $this->ruleManager->getRules( $templateId );
			}
			$nonce   = wp_create_nonce( Brizy_Editor_API::nonce );
			$context = array(
				'rules'         => $rules,
				'types'         => array(),
				'apply_for'     => array(),
				'templateId'    => $_GET['post'],
				'reload_action' => admin_url( 'admin-ajax.php?action=' . self::RULE_LIST_VEIW . '&post=' . $templateId . '&hash=' . $nonce ),
				'submit_action' => admin_url( 'admin-ajax.php?action=' . Brizy_Admin_Rules_Api::CREATE_RULE_ACTION ),
				'delete_action' => admin_url( 'admin-ajax.php?action=' . Brizy_Admin_Rules_Api::DELETE_RULE_ACTION . '&postId=' . $templateId . '&hash=' . $nonce ),
				'nonce'         => $nonce
			);

			echo Brizy_TwigEngine::instance( path_join( BRIZY_PLUGIN_PATH, "admin/views" ) )
			                     ->render( 'rules-box.html.twig', $context );
		} catch ( Exception $e ) {
			Brizy_Logger::instance()->error( $e->getMessage(), array( 'exception' => $e ) );
			echo $e->getMessage();
			?>Unable to show the rule box.<?
		}
	}

	public function getTemplateRuleBox() {
	    $this->templateRulesBox();
	    exit;
    }


	public function templateInclude( $template ) {

		if ( $this->template ) {
			return path_join( BRIZY_PLUGIN_PATH, 'public/views/templates/brizy-template.php' );
		}

		if ( get_post_type() == self::CP_TEMPLATE ) {
			return path_join( BRIZY_PLUGIN_PATH, 'public/views/templates/brizy-blank-template.php' );
		}

		return $template;
	}


	/**
	 * @return Brizy_Editor_Post|null
	 * @throws Brizy_Editor_Exceptions_NotFound
	 */
	public function getTemplateForCurrentPage() {

		$templates = get_posts( array(
			'post_type'      => self::CP_TEMPLATE,
			'numberposts'    => - 1,
			'posts_per_page' => - 1
		) );


		$applyFor = null;
		$id       = null;

		if ( is_tax() ) {
			$applyFor = Brizy_Admin_Rule::TAXONOMY;
		} elseif ( is_404() ) {
			$applyFor = Brizy_Admin_Rule::TEMPLATE;
			$id       = '404';
		} else {
			$applyFor = Brizy_Admin_Rule::POSTS;
			$id       = get_the_ID();
		};

		foreach ( $templates as $template ) {
			$ruleSet     = $this->ruleManager->getRuleSet( $template->ID );
			$currentPost = null;

			if ( $ruleSet->isGranted( $applyFor, $id ) ) {
				// use the template here
				return Brizy_Editor_Post::get( $template->ID );
			}
		}

		return null;
	}

	public function templateFrontEnd() {
		$pid = brizy_get_current_post_id();

		$is_using_brizy = false;
		try {
			$is_using_brizy = Brizy_Editor_Post::get( $pid )->uses_editor();
		} catch ( Exception $e ) {
		}

		try {

			if ( is_null( $pid ) || ! $is_using_brizy ) {
				$this->template = $this->getTemplateForCurrentPage();

				if ( ! $this->template ) {
					return;
				}

				$GLOBALS['post'] = $this->template->get_wp_post();

				remove_filter( 'the_content', 'wpautop' );

				// insert the compiled head and content
				add_filter( 'body_class', array( $this, 'bodyClassFrontend' ) );
				add_action( 'wp_head', array( $this, 'insertPageHead' ) );
				add_filter( 'the_content', array( $this, 'insertPageContent' ), - 10000 );
			}

			add_action( 'template_include', array( $this, 'templateInclude' ) );

		} catch ( Exception $e ) {
			//ignore
		}
	}


	public function bodyClassFrontend( $classes ) {

		$classes[] = 'brz';

		return $classes;
	}

	/**
	 *  Show the compiled page head content
	 */
	public function insertPageHead() {

		if ( ! $this->template ) {
			return;
		}

		$compiled_page = $this->template->get_compiled_page( Brizy_Editor_Project::get() );

		$compiled_page->addAssetProcessor( new Brizy_Editor_Asset_StripTagsProcessor( array( '<title>' ) ) );

		$head = $compiled_page->get_head();

		?>
        <!-- BRIZY HEAD -->
		<?php echo $head; ?>
        <!-- END BRIZY HEAD -->
		<?php

		return;
	}

	/**
	 * @param $content
	 *
	 * @return null|string|string[]
	 * @throws Exception
	 */
	public function insertPageContent( $content ) {

		if ( ! $this->template ) {
			return $content;
		}

		$compiled_page = $this->template->get_compiled_page( Brizy_Editor_Project::get() );

		$body = $compiled_page->get_body();

		return $body;
	}
}
