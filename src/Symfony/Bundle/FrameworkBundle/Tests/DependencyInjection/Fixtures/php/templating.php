<?php

$container->loadFromExtension('framework', array(
    'templating' => array(
        'cache' => '/path/to/cache',
        'engines' => array('php', 'twig'),
        'loader' => array('loader.foo', 'loader.bar'),
        'form' => array(
            'resources' => array('theme1', 'theme2'),
        ),
        'hinclude_default_template' => 'global_hinclude_template',
    ),
    'assets' => null,
));
