<?php

$container->loadFromExtension('framework', array(
    'assets' => array(
        'enabled' => true,
    ),
    'templating' => array(
        'engines' => array('php', 'twig'),
    ),
));
