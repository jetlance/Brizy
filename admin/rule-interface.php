<?php
/**
 * Created by PhpStorm.
 * User: alex
 * Date: 7/4/18
 * Time: 10:46 AM
 */

interface Brizy_Admin_RuleInterface {
	public function isGranted( $applied_for, $id = null );
}