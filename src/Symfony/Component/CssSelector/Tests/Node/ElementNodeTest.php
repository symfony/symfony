<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\Node\ElementNode;

class ElementNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new ElementNode(), 'Element[*]'],
            [new ElementNode(null, 'element'), 'Element[element]'],
            [new ElementNode('namespace', 'element'), 'Element[namespace|element]'],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new ElementNode(), 0],
            [new ElementNode(null, 'element'), 1],
            [new ElementNode('namespace', 'element'), 1],
        ];
    }
}
