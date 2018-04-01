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

use Symphony\Component\CssSelector\Node\ClassNode;
use Symphony\Component\CssSelector\Node\NegationNode;
use Symphony\Component\CssSelector\Node\ElementNode;

class NegationNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new NegationNode(new ElementNode(), new ClassNode(new ElementNode(), 'class')), 'Negation[Element[*]:not(Class[Element[*].class])]'),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new NegationNode(new ElementNode(), new ClassNode(new ElementNode(), 'class')), 10),
        );
    }
}
