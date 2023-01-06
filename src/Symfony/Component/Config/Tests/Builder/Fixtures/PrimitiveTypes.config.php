<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Config\PrimitiveTypesConfig;

return static function (PrimitiveTypesConfig $config) {
    $config->booleanNode(true);
    $config->enumNode('foo');
    $config->floatNode(47.11);
    $config->integerNode(1337);
    $config->scalarNode('foobar');
    $config->scalarNodeWithDefault(null);
};
