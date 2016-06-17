<?php

$container->loadFromExtension('framework', array(
    'validation' => array(
        'mapping' => array(
            'dirs' => array('%kernel.root_dir%/Fixtures/TestBundle/Resources/config'),
        ),
    ),
));
