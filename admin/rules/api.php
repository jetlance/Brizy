<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/18/18
 * Time: 10:48 AM
 */


class Brizy_Admin_Rules_Api {

	const CREATE_RULE_ACTION = 'brizy_add_rule';
	const DELETE_RULE_ACTION = 'brizy_delete_rule';
	const LIST_RULE_ACTION = 'brizy_list_rules';

	/**
	 * @var Brizy_Admin_Rules_Manager
	 */
	private $manager;


	/**
	 * @return Brizy_Admin_Rules_Api
	 */
	public static function _init() {
		static $instance;

		if ( ! $instance ) {
			$instance = new self( new Brizy_Admin_Rules_Manager() );
		}

		return $instance;
	}

	/**
	 * Brizy_Admin_Rules_Api constructor.
	 *
	 * @param Brizy_Admin_Rules_Manager $manager
	 */
	public function __construct( $manager ) {
		$this->manager = $manager;
		add_action( 'wp_ajax_' . self::CREATE_RULE_ACTION, array( $this, 'actionCreateRule' ) );
		add_action( 'wp_ajax_' . self::DELETE_RULE_ACTION, array( $this, 'actionDeleteRule' ) );
		add_action( 'wp_ajax_' . self::LIST_RULE_ACTION, array( $this, 'actionGetRuleList' ) );
	}

	public function actionGetRuleList() {

		if ( ! wp_verify_nonce( $_REQUEST['hash'], Brizy_Editor_API::nonce ) ) {
			wp_send_json_error( (object) array(
				'message' => 'Invalid request',
			), 400 );
		}

		$postId = (int) $_GET['postId'];

		if ( ! $postId ) {
			return wp_send_json_error( (object) array( 'message' => 'Invalid template' ), 400 );
		}

		$rules = $this->manager->getRules( $postId );

		wp_send_json_success( $rules, 200 );
	}

	public function actionCreateRule() {

		if ( ! wp_verify_nonce( $_REQUEST['hash'], Brizy_Editor_API::nonce ) ) {
			wp_send_json_error( (object) array(
				'message' => 'Invalid request',
			), 400 );
		}

		$postId = (int) $_POST['postId'];

		if ( ! $postId ) {
			return wp_send_json_error( (object) array( 'message' => 'Invalid template' ), 400 );
		}

		$apply_for = explode( '_', $_POST['brizy-apply-to'] );

		if ( $apply_for[0] == Brizy_Admin_Rule::TEMPLATE ) {
			$rule = new Brizy_Admin_Rule( null, (int) $_POST['brizy-rule-type'], (int) $apply_for[0], null, array( $apply_for[1] ) );
		} else {
			$rule = new Brizy_Admin_Rule( null, (int) $_POST['brizy-rule-type'], (int) $apply_for[0], $apply_for[1], explode( ',', $_POST['brizy-entities'] ) );
		}

		// validate rule
		$ruleSet = $this->manager->getRuleSet( $postId );

		foreach ( $ruleSet->getRules() as $arule ) {
			if ( $rule->isOverriddenBy( $arule ) ) {
				wp_send_json_error( (object) array(
					'message' => 'The rule is overridden by an existing rule',
					'rule'    => $arule->getId()
				), 400 );
			}

			if ( $arule->isOverriddenBy( $rule ) ) {
				wp_send_json_error( (object) array(
					'message' => 'This rule will override an existing rule',
					'rule'    => $rule->getId()
				), 400 );
			}
		}

		$this->manager->addRule( $postId, $rule );

		wp_send_json_success( $rule, 200 );
	}

	public function actionDeleteRule() {

		if ( ! wp_verify_nonce( $_REQUEST['hash'], Brizy_Editor_API::nonce ) ) {
			wp_send_json_error( (object) array(
				'message' => 'Invalid request',
			), 400 );
		}

		$postId = (int) $_GET['postId'];
		$ruleId = $_GET['ruleId'];

		if ( ! $postId || ! $ruleId ) {
			wp_send_json_error( null, 400 );
		}

		$this->manager->deleteRule( $postId, $ruleId );

		wp_send_json_success( null, 200 );
	}

}