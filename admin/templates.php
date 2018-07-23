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
			add_filter( 'post_updated_messages', array( $this, 'filterTemplateMessages' ) );
			add_action( 'add_meta_boxes', array( $this, 'registerTemplateMetaBox' ) );
			add_action( 'wp_ajax_' . self::RULE_LIST_VEIW, array( $this, 'getTemplateRuleBox' ) );
			add_filter( 'post_row_actions', array( $this, 'removeRowActions' ), 10, 1 );

		} else {
			add_action( 'wp', array( $this, 'templateFrontEnd' ) );
			add_action( 'template_include', array( $this, 'templateInclude' ) );
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

	/**
	 * @param $messages
	 *
	 * @return mixed
	 */
	function filterTemplateMessages( $messages ) {
		$post             = get_post();
		$post_type        = get_post_type( $post );
		$post_type_object = get_post_type_object( $post_type );

		$messages[ self::CP_TEMPLATE ] = array(
			0  => '', // Unused. Messages start at index 1.
			1  => __( 'Template updated.' ),
			2  => __( 'Custom field updated.' ),
			3  => __( 'Custom field deleted.' ),
			4  => __( 'Template updated.' ),
			/* translators: %s: date and time of the revision */
			5  => isset( $_GET['revision'] ) ? sprintf( __( 'Template restored to revision from %s' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6  => __( 'Template published.' ),
			7  => __( 'Template saved.' ),
			8  => __( 'Template submitted.' ),
			9  => sprintf(
				__( 'Template scheduled for: <strong>%1$s</strong>.' ),
				// translators: Publish box date format, see http://php.net/date
				date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) )
			),
			10 => __( 'Template draft updated.' )
		);

		if ( $post_type_object->publicly_queryable && 'Template' === $post_type ) {
			$permalink = get_permalink( $post->ID );

			$view_link                 = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View Template' ) );
			$messages[ $post_type ][1] .= $view_link;
			$messages[ $post_type ][6] .= $view_link;
			$messages[ $post_type ][9] .= $view_link;

			$preview_permalink          = add_query_arg( 'preview', 'true', $permalink );
			$preview_link               = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview Template' ) );
			$messages[ $post_type ][8]  .= $preview_link;
			$messages[ $post_type ][10] .= $preview_link;
		}

		return $messages;
	}

	public function registerCustomPostTemplate() {


		$labels = array(
			'name'               => _x( 'Templates', 'post type general name' ),
			'singular_name'      => _x( 'Template', 'post type singular name' ),
			'menu_name'          => _x( 'Templates', 'admin menu' ),
			'name_admin_bar'     => _x( 'Template', 'add new on admin bar' ),
			'add_new'            => _x( 'Add New', self::CP_TEMPLATE ),
			'add_new_item'       => __( 'Add New Template' ),
			'new_item'           => __( 'New Template' ),
			'edit_item'          => __( 'Edit Template' ),
			'view_item'          => __( 'View Template' ),
			'all_items'          => __( 'All Templates' ),
			'search_items'       => __( 'Search Templates' ),
			'parent_item_colon'  => __( 'Parent Templates:' ),
			'not_found'          => __( 'No Templates found.' ),
			'not_found_in_trash' => __( 'No Templates found in Trash.' )
		);

		register_post_type( self::CP_TEMPLATE,
			array(
				'labels'              => $labels,
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
				'supports'            => array( 'title', 'revisions' )
			)
		);
	}

	public function registerTemplateMetaBox() {
		add_meta_box( 'template-rules', __( 'Rules' ), array( $this, 'templateRulesBox' ), self::CP_TEMPLATE );
	}

	public function removeRowActions( $actions ) {
		if ( get_post_type() === self::CP_TEMPLATE ) {
			unset( $actions['view'] );
		}

		return $actions;
	}

	public function templateRulesBox() {
		try {

			$templateId = isset( $_REQUEST['post'] ) ? (int) $_REQUEST['post'] : get_the_ID();

			if ( ! $templateId ) {
				throw new Exception();
			}

			$rules = $this->ruleManager->getRules( $templateId );

			$nonce   = wp_create_nonce( Brizy_Editor_API::nonce );
			$context = array(
				'rules'         => $rules,
				'types'         => array(),
				'apply_for'     => array(),
				'templateId'    => $templateId,
				'reload_action' => admin_url( 'admin-ajax.php?action=' . self::RULE_LIST_VEIW . '&post=' . $templateId . '&hash=' . $nonce ),
				'submit_action' => admin_url( 'admin-ajax.php?action=' . Brizy_Admin_Rules_Api::CREATE_RULE_ACTION ),
				'delete_action' => admin_url( 'admin-ajax.php?action=' . Brizy_Admin_Rules_Api::DELETE_RULE_ACTION . '&postId=' . $templateId . '&hash=' . $nonce ),
				'nonce'         => $nonce
			);

			echo Brizy_TwigEngine::instance( path_join( BRIZY_PLUGIN_PATH, "admin/views" ) )
			                     ->render( 'rules-box.html.twig', $context );
		} catch ( Exception $e ) {
			Brizy_Logger::instance()->error( $e->getMessage(), array( 'exception' => $e ) );
			?>Unable to show the rule box.<?php
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
			$pid            = get_queried_object_id();
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
