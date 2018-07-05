<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/18/18
 * Time: 10:48 AM
 */


class Brizy_Admin_Rules_Manager {

	/**
	 * @param int $postId
	 *
	 * @return array
	 */
	public function getRules( $postId ) {
		$rules = array();

		$meta_value = get_post_meta( (int) $postId, 'brizy-template-rules', true );

		foreach ( $meta_value as $v ) {
			$rules[] = Brizy_Admin_Rule::createFromSerializedData( $v );
		}

		return $rules;
	}

	/**
	 * @param $postId
	 * @param Brizy_Admin_Rule[] $rules
	 */
	public function saveRules( $postId, $rules ) {

		$arrayRules = array();

		foreach ( $rules as $rule ) {
			$arrayRules[] = $rule->convertToOptionValue();
		}

		update_post_meta( (int) $postId, 'brizy-template-rules', $arrayRules );
	}

	/**
	 * @param $postId
	 * @param Brizy_Admin_Rule $rule
	 */
	public function addRule( $postId, $rule ) {
		$rules   = $this->getRules( $postId );
		$rules[] = $rule;
		$this->saveRules( $postId, $rules );

	}

	/**
	 * @param $postId
	 * @param $ruleId
	 */
	public function deleteRule( $postId, $ruleId ) {
		$rules = $this->getRules( $postId );
		foreach ( $rules as $i => $rule ) {
			if ( $rule->getId() == $ruleId ) {
				unset( $rules[ $i ] );
			}
		}

		$this->saveRules( $postId, $rules );
	}

	/**
	 * @param $postId
	 * @param Brizy_Admin_Rule[] $rules
	 */
	public function addRules( $postId, $rules ) {
		$current_rules = $this->getRuleSet( $postId );
		$result_rules  = array_merge( $current_rules, $rules );
		$this->saveRules( $postId, $result_rules );

	}

	/**
	 * @param int $postId
	 *
	 * @return Brizy_Admin_RuleSet
	 */
	public function getRuleSet( $postId ) {
		return new Brizy_Admin_RuleSet( $this->getRules( $postId ) );
	}

}