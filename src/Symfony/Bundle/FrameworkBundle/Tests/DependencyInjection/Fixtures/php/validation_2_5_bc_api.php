<?php

$container->loadFromExtension('framework', array(
    'secret' => 's3cr3t',
    'validation' => array(
        'enabled'            => true,
        'api'                => '2.5-bc',
    ),
));
