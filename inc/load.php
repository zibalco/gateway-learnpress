<?php

/**
 * Plugin load class.
 *
 * @author   zibal team
 * @link 	 https://zibal.ir
 * @package  LearnPress/Zibal/Classes
 * @version  2.0.0
 */

// Prevent loading this file directly
defined('ABSPATH') || exit;

if (!class_exists('LP_Addon_Zibal_Payment')) {
	/**
	 * Class LP_Addon_Zibal_Payment
	 */
	class LP_Addon_Zibal_Payment extends LP_Addon
	{

		/**
		 * @var string
		 */
		public $version = LP_ADDON_ZIBAL_PAYMENT_VER;

		/**
		 * @var string
		 */
		public $require_version = LP_ADDON_ZIBAL_PAYMENT_REQUIRE_VER;

		/**
		 * LP_Addon_Zibal_Payment constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * Define Learnpress Zibal payment constants.
		 *
		 */
		protected function _define_constants()
		{
            define('LP_ADDON_ZIBAL_PAYMENT_PATH', dirname(LP_ADDON_ZIBAL_PAYMENT_FILE));
			define('LP_ADDON_ZIBAL_PAYMENT_INC', LP_ADDON_ZIBAL_PAYMENT_PATH . '/inc/');
			define('LP_ADDON_ZIBAL_PAYMENT_URL', plugin_dir_url(LP_ADDON_ZIBAL_PAYMENT_FILE));
			define('LP_ADDON_ZIBAL_PAYMENT_TEMPLATE', LP_ADDON_ZIBAL_PAYMENT_PATH . '/templates/');
		}

		/**
		 * Include required core files used in admin and on the frontend.
		 *
		 */
		protected function _includes()
		{
			include_once LP_ADDON_ZIBAL_PAYMENT_INC . 'class-lp-gateway-zibal.php';
		}

		/**
		 * Init hooks.
		 */
		protected function _init_hooks()
		{
			// add payment gateway class
			add_filter('learn_press_payment_method', array($this, 'add_payment'));
			add_filter('learn-press/payment-methods', array($this, 'add_payment'));
		}

		/**
		 * Enqueue assets.
		 *
		 */
		protected function _enqueue_assets()
		{
			return;

			if (LP()->settings->get('learn_press_zibal.enable') == 'yes') {
				$user = learn_press_get_current_user();

				learn_press_assets()->enqueue_script('learn-press-zibal-payment', $this->get_plugin_url('assets/js/script.js'), array());
				learn_press_assets()->enqueue_style('learn-press-zibal', $this->get_plugin_url('assets/css/style.css'), array());

				$data = array(
					'plugin_url'  => plugins_url('', LP_ADDON_ZIBAL_PAYMENT_FILE)
				);
				wp_localize_script('learn-press-zibal', 'learn_press_zibal_info', $data);
			}
		}

		/**
		 * Add Zibal to payment system.
		 *
		 * @param $methods
		 *
		 * @return mixed
		 */
		public function add_payment($methods)
		{
			$methods['zibal'] = 'LP_Gateway_Zibal';

			return $methods;
		}

		/**
		 * Plugin links.
		 *
		 * @return array
		 */
		public function plugin_links()
		{
			$links[] = '<a href="' . admin_url('admin.php?page=learn-press-settings&tab=payments&section=zibal') . '">' . __('Settings', 'learnpress-zibal') . '</a>';

			return $links;
		}
	}
}
