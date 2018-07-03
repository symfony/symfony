<?php

$container->loadFromExtension('framework', array(
    'assets' => array(
        'version' => 'SomeVersionScheme',
        'base_urls' => 'http://cdn.example.com',
        'version_format' => '%%s?version=%%s',
        'packages' => array(
            'images_path' => array(
                'base_path' => '/foo',
            ),
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
            'bar_version_strategy' => array(
                'base_urls' => array('https://bar2.example.com'),
                'version_strategy' => 'assets.custom_version_strategy',
            ),
            'json_manifest_strategy' => array(
                'json_manifest_path' => '/path/to/manifest.json',
            ),
            'strict_protocol' => array(
                'is_strict_protocol' => true,
                'base_urls' => array('http://www.example.com', 'https://www.example.net'),
            ),
        ),
    ),
));
