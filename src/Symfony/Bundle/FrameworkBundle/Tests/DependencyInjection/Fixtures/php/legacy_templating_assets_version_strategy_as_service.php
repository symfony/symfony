<?php

$container->loadFromExtension('framework', array(
        'templating' => array(
            'engines' => array('php'),
            'assets_version_strategy' => 'assets.custom_version_strategy',
            'assets_base_urls' => 'http://cdn.example.com',
        ),
    ));
