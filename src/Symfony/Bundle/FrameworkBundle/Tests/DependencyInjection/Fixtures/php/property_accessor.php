<?php

$container->loadFromExtension('framework', array(
    'property_access' => array(
        'magic_call' => true,
        'throw_exception_on_invalid_index' => true,
    ),
));
