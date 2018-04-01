<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\CssSelector\Tests\Node;

use Symphony\Component\CssSelector\Node\ElementNode;

class ElementNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new ElementNode(), 'Element[*]'),
            array(new ElementNode(null, 'element'), 'Element[element]'),
            array(new ElementNode('namespace', 'element'), 'Element[namespace|element]'),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new ElementNode(), 0),
            array(new ElementNode(null, 'element'), 1),
            array(new ElementNode('namespace', 'element'), 1),
        );
    }
}
