<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'validation' => array(
        'enabled' => true,
        'static_method' => array('loadFoo', 'loadBar'),
    ),
));
