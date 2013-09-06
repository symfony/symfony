<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'templating' => array(
        'assets_base_urls' => 'https://cdn.example.com',
        'engines'          => array('php', 'twig'),
        'packages'         => array(
            'images' => array(
                'base_urls' => 'https://images.example.com',
            ),
        ),
    ),
));
