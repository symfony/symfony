<?php

use Symfony\Config\PrimitiveTypesConfig;

return static function (PrimitiveTypesConfig $config) {
    $config->booleanNode(true);
    $config->enumNode('foo');
    $config->floatNode(47.11);
    $config->integerNode(1337);
    $config->scalarNode('foobar');
    $config->scalarNodeWithDefault(null);
};
