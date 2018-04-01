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
use Symphony\Component\CssSelector\Node\SelectorNode;

class SelectorNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new SelectorNode(new ElementNode()), 'Selector[Element[*]]'),
            array(new SelectorNode(new ElementNode(), 'pseudo'), 'Selector[Element[*]::pseudo]'),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new SelectorNode(new ElementNode()), 0),
            array(new SelectorNode(new ElementNode(), 'pseudo'), 1),
        );
    }
}
