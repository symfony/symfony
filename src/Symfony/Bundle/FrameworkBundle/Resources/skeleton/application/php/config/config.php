<?php

$container->setParameter('kernel.include_core_classes', false);

$container->getExtension('kernel')->load('config', array(
    'charset'             => 'UTF-8',
    'error_handler_level' => null,
), $container);

$container->getExtension('web')->load('config', array(
    'router' => array('resource' => '%kernel.root_dir%/config/routing.php'),
), $container);

$container->getExtension('web')->load('templating', array(
    'escaping'       => "htmlspecialchars",
    'assets_version' => "SomeVersionScheme",
), $container);

$container->getExtension('doctrine')->load('dbal', array(
    'dbname'   => 'xxxxxxxx',
    'user'     => 'root',
    'password' => '',
), $container);

$container->getExtension('doctrine')->load('orm', array(
), $container);

$container->getExtension('swift')->load('mailer', array(
    'transport' => "gmail",
    'username'  => "xxxxxxxx",
    'password'  => "xxxxxxxx",
), $container);
