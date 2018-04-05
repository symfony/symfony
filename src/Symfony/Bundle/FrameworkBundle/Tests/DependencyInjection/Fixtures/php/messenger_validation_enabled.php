<?php

$container->loadFromExtension('framework', array(
    'validation' => array('enabled' => true),
    'messenger' => array(
        'middlewares' => array(
            'validation' => array(
                'enabled' => true,
            ),
        ),
    ),
));
