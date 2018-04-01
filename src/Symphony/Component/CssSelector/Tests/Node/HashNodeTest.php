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

use Symphony\Component\CssSelector\Node\HashNode;
use Symphony\Component\CssSelector\Node\ElementNode;

class HashNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new HashNode(new ElementNode(), 'id'), 'Hash[Element[*]#id]'),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new HashNode(new ElementNode(), 'id'), 100),
            array(new HashNode(new ElementNode(null, 'id'), 'class'), 101),
        );
    }
}
