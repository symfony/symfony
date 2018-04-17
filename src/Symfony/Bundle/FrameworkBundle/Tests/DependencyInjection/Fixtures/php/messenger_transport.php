<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'serializer' => array(
            'format' => 'csv',
            'context' => array('enable_max_depth' => true),
        ),
    ),
));
