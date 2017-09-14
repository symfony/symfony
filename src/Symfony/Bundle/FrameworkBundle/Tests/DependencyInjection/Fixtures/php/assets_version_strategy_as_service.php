<?php

$container->loadFromExtension('framework', array(
    'assets' => array(
        'version_strategy' => 'assets.custom_version_strategy',
        'base_urls' => 'http://cdn.example.com',
    ),
));
