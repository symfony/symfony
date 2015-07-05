<?php

$container->loadFromExtension('framework', array(
    'property_access' => array(
        'magic_call' => true,
        'throw_exception_on_invalid_index' => true,
        'property_singularify' => '\Symfony\Component\PropertyAccess\Tests\Fixtures\TestSingularifyClass',
    ),
));
