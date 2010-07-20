<?php

$loader->import('config_dev.php');

$container->getExtension('web')->load('config', array(
    'toolbar' => false,
), $container);

$container->getExtension('kernel')->load('test', array(
), $container);
