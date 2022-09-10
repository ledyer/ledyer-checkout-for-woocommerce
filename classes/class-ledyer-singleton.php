<?php
/**
 * Trait Singleton
 *
 * @package Ledyer;
 * @since 1.0.0
 */
namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Trait Singleton
 *
 * Creates Singleton class
 */
trait Singleton {

	/** @var self */
	private static $instance = null;
	/** @var settings */
	private static $settings = array();

	/**
	 * Instanciate the class
	 *
	 * @return self
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct the class
	 *
	 * @return void
	 */
	private function __construct() {
		$this->set_settings();
		$this->actions();
		$this->filters();
	}

	/**
	 * Different add_actions is added here
	 *
	 * @return void
	 */
	public function actions() {
	}

	/**
	 * Different add_filters is added here
	 *
	 * @return void
	 */
	public function filters() {
	}
	/**
	 * Set settings
	 *
	 * @return void
	 */
	public function set_settings() {
	}
}
