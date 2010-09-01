<?php

$loader->import('config_dev.php');

$container->loadFromExtension('kernel', 'config', array(
    'error_handler' => false,
));

$container->loadFromExtension('webprofiler', 'config', array(
    'toolbar' => false,
    'intercept-redirects' => false,
));

$container->loadFromExtension('zend', 'logger', array(
    'priority' => 'debug',
));

$container->loadFromExtension('kernel', 'test');
