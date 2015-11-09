<?php

$container->loadFromExtension('framework', array(
    'templating' => array(
        'engines' => array('php'),
        'assets_version' => 'SomeVersionScheme',
        'assets_base_urls' => 'http://cdn.example.com',
        'assets_version_format' => '%%s?version=%%s',
        'packages' => array(
            'images' => array(
                'version' => '1.0.0',
                'base_urls' => array('http://images1.example.com', 'http://images2.example.com'),
            ),
            'foo' => array(
                'version' => '1.0.0',
                'version_format' => '%%s-%%s',
            ),
            'bar' => array(
                'base_urls' => array('https://bar2.example.com'),
            ),
            'bar_null_version' => array(
                'version' => null,
                'base_urls' => array('https://bar3.example.com'),
            ),
        ),
    ),
));
