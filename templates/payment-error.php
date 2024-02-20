<?php

/**
 * Template for displaying Zibal payment error message.
 *
 * This template can be overridden by copying it to yourtheme/learnpress/addons/zibal-payment/payment-error.php.
 *
 * @author   zibal team
 * @link 	 https://zibal.ir
 * @package  LearnPress/Zibal/Classes
 * @version  2.0.0
 */

/**
 * Prevent loading this file directly
 */
defined('ABSPATH') || exit();
?>

<?php $settings = LP()->settings; ?>

<div class="learn-press-message error ">
	<div><?php echo __('Transation failed', 'learnpress-zibal'); ?></div>
</div>