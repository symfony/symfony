<?php

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\Node\ElementNode;
use Symfony\Component\CssSelector\Node\SelectorNode;

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
