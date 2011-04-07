<?php

$container->loadFromExtension('twig', array(
    'form' => array(
        'resources' => array(
            'MyBundle::form.html.twig',
        )
     ),
     'extensions' => array(
         'twig.extension.debug',
         'twig.extension.text',
     ),
     'globals' => array(
         'foo' => '@bar',
         'pi'  => 3.14,
     ),
     'auto_reload'         => true,
     'autoescape'          => true,
     'base_template_class' => 'stdClass',
     'cache'               => '/tmp',
     'cache_warmer'        => true,
     'charset'             => 'ISO-8859-1',
     'debug'               => true,
     'strict_variables'    => true,
));
