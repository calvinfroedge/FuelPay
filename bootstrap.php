<?php
// Add as core namespace
Autoloader::add_core_namespace('Payment');

// And add the classes, this is usefull for:
// - optimization: no path searching is necessary
// - it's required to be able to use as a core namespace
// - if you want to break the autoloader's path search rules
Autoloader::add_classes(array(
    'Payment\\Payment' => __DIR__.'/classes/payment.php'
));