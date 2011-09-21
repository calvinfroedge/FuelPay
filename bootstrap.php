<?php
/**
 * Fuel is a fast, lightweight, community driven PHP5 framework.
 *
 * @package    Fuel
 * @version    1.0
 * @author     Fuel Development Team
 * @license    MIT License
 * @copyright  2010 - 2011 Fuel Development Team
 * @link       http://fuelphp.com
 */

Autoloader::add_core_namespace('Payments', __DIR__.'/classes/payments.php');

Autoloader::add_classes(array(
	'Payments\\Payments'           => __DIR__.'/classes/payments.php',
	'Payments\\Authorize_Net'	   => __DIR__.'/classes/payments/authorize_net.php',
));


/* End of file bootstrap.php */