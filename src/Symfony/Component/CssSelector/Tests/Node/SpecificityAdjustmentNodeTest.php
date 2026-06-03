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
use Symfony\Component\CssSelector\Node\HashNode;
use Symfony\Component\CssSelector\Node\SpecificityAdjustmentNode;

class SpecificityAdjustmentNodeTest extends AbstractNodeTestCase
{
    public static function getToStringConversionTestData()
    {
        return [
            [new SpecificityAdjustmentNode(new ElementNode(), [
                new ClassNode(new ElementNode(), 'class'),
                new HashNode(new ElementNode(), 'id'),
            ]), 'SpecificityAdjustment[Element[*]:where(Class[Element[*].class], Hash[Element[*]#id])]'],
        ];
    }

    public static function getSpecificityValueTestData()
    {
        return [
            [new SpecificityAdjustmentNode(new ElementNode(), [
                new ClassNode(new ElementNode(), 'class'),
                new HashNode(new ElementNode(), 'id'),
            ]), 0],
            [new SpecificityAdjustmentNode(new ClassNode(new ElementNode(), 'class'), [
                new ClassNode(new ElementNode(), 'class'),
                new HashNode(new ElementNode(), 'id'),
            ]), 10],
        ];
    }
}
