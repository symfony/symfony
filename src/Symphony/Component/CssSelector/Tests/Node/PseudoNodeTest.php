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
use Symphony\Component\CssSelector\Node\PseudoNode;

class PseudoNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new PseudoNode(new ElementNode(), 'pseudo'), 'Pseudo[Element[*]:pseudo]'),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new PseudoNode(new ElementNode(), 'pseudo'), 10),
        );
    }
}
