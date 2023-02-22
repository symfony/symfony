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

use Symfony\Component\CssSelector\Node\ClassNode;
use Symfony\Component\CssSelector\Node\ElementNode;
use Symfony\Component\CssSelector\Node\NegationNode;

class NegationNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new NegationNode(new ElementNode(), new ClassNode(new ElementNode(), 'class')), 'Negation[Element[*]:not(Class[Element[*].class])]'],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new NegationNode(new ElementNode(), new ClassNode(new ElementNode(), 'class')), 10],
        ];
    }
}
