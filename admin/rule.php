<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/4/18
 * Time: 10:46 AM
 */

class Brizy_Admin_Rule extends Brizy_Admin_Serializable implements Brizy_Admin_RuleInterface {

	const TYPE_INCLUDE = 1;
	const TYPE_EXCLUDE = 2;

	const POSTS = 1;
	const TAXONOMY = 2;
	const TEMPLATE = 3;

	/**
	 * @var int
	 */
	private $id;

	/**
	 * @var int
	 */
	private $type;

	/**
	 * @var int
	 */
	private $applied_for;

	/**
	 * @var string
	 */
	private $entity_type;

	/**
	 * If null the rule will be applied on all entities
	 *
	 * @var int[]
	 */
	private $entities = null;

	/**
	 * @return array|mixed
	 */
	public function jsonSerialize() {
		return $this->convertToOptionValue();
	}

	/**
	 * Brizy_Admin_Rule constructor.
	 *
	 * @param int $id
	 * @param int $type
	 * @param int $applied_for
	 * @param int $entity_type
	 * @param array $entities
	 */
	public function __construct( $id, $type, $applied_for, $entity_type, $entities ) {
		$this->setType( $type );
		$this->setAppliedFor( $applied_for );
		$this->setEntityType( $entity_type );
		$this->setEntities( array_filter( (array)$entities, array( $this, 'filter' ) ) );
		$this->setId( $this->generateId( $type, $applied_for, $entity_type, $this->getEntitiesAsString() ) );
	}

	function filter( $v ) {
		return ! empty( $v );
	}

	/**
	 * Return true if the rule matches for the given parameters
	 *
	 * @param $applied_for
	 * @param null $entity
	 *
	 * @return bool
	 */
	public function isGranted( $applied_for, $entity = null ) {

		$applies = $this->getAppliedFor() == $applied_for;

		if ( empty( $this->entities ) ) {
			$applies_for_id = true;
		} else {
			$applies_for_id = ! is_null( $entity ) ? in_array( $entity, $this->getEntities() ) : true;
		}

		if ( ! ( $applies && $applies_for_id ) ) {
			return false;
		}

		if ( $this->getType() === self::TYPE_INCLUDE ) {
			return true;
		}

		return false;
	}

	/**
	 * @return int
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $id
	 *
	 * @return Brizy_Admin_Rule
	 */
	public function setId( $id ) {

		$this->id = $id;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * @param int $type
	 *
	 * @return Brizy_Admin_Rule
	 */
	public function setType( $type ) {
		$this->type = $type;

		return $this;
	}

	/**
	 * @return int
	 */
	public function getAppliedFor() {
		return $this->applied_for;
	}

	/**
	 * @param int $applied_for
	 *
	 * @return Brizy_Admin_Rule
	 */
	public function setAppliedFor( $applied_for ) {
		$this->applied_for = $applied_for;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getEntityType() {
		return $this->entity_type;
	}

	/**
	 * @param string $entity_type
	 *
	 * @return Brizy_Admin_Rule
	 */
	public function setEntityType( $entity_type ) {
		$this->entity_type = $entity_type;

		return $this;
	}

	/**
	 * @return int[]
	 */
	public function getEntities() {
		return is_null( $this->entities ) ? array() : $this->entities;
	}

	/**
	 * @param int[] $entities
	 *
	 * @return Brizy_Admin_Rule
	 */
	public function setEntities( $entities ) {

		if ( ! is_array( $entities ) ) {
			throw new InvalidArgumentException();
		}

		$this->entities = $entities;

		return $this;
	}

	/**
	 * @return array
	 */
	public function convertToOptionValue() {
		return array(
			'id'          => $this->getId(),
			'type'        => $this->getType(),
			'applied_for' => $this->getAppliedFor(),
			'entity_type' => $this->getEntityType(),
			'entities'    => $this->getEntities(),
		);
	}

	/**
	 * @param $data
	 *
	 * @return Brizy_Admin_Rule|void
	 */
	static public function createFromSerializedData( $data ) {
		return new self(
			isset( $data['id'] ) ? $data['id'] : null,
			isset( $data['type'] ) ? $data['type'] : null,
			isset( $data['applied_for'] ) ? $data['applied_for'] : null,
			isset( $data['entity_type'] ) ? $data['entity_type'] : null,
			isset( $data['entities'] ) ? $data['entities'] : null
		);
	}

	/**
	 * @param Brizy_Admin_Rule $rule
	 *
	 * @return bool
	 */
	public function isOverriddenBy( $rule ) {

		return $this->getType() == $rule->getType() &&
		       $this->getAppliedFor() == $rule->getAppliedFor() &&
		       $this->getEntityType() == $rule->getEntityType() &&
		       ( empty( $rule->getEntities() ) || count( array_diff( $rule->getEntities(), $this->getEntities() ) ) == 0 );
	}

	/**
	 * @param string $delimited
	 *
	 * @return string
	 */
	public function getEntitiesAsString( $delimited = ',' ) {
		return implode( $delimited, $this->getEntities() );
	}

	/**
	 * @return string
	 */
	private function generateId() {
		return md5( implode( '', func_get_args() ) );
	}

}