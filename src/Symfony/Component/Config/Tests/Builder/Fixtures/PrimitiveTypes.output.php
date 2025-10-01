<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

return [
    'boolean_node' => true,
    'enum_node' => 'foo',
    'fqcn_enum_node' => 'bar',
    'fqcn_unit_enum_node' => \Symfony\Component\Config\Tests\Fixtures\TestEnum::Bar,
    'float_node' => 47.11,
    'integer_node' => 1337,
    'scalar_node' => 'foobar',
    'scalar_node_with_default' => null,
];
