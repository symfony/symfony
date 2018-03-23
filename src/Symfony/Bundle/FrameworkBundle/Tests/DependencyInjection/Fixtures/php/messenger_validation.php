<?php

$container->loadFromExtension('framework', array(
    'messenger' => array(
        'middlewares' => array(
            'validation' => array(
                'enabled' => false,
            ),
        ),
    ),
));
