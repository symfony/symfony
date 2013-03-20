<?php

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\Node\ElementNode;
use Symfony\Component\CssSelector\Node\PseudoNode;

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
