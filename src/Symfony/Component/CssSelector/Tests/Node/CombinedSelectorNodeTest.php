<?php

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\Node\CombinedSelectorNode;
use Symfony\Component\CssSelector\Node\ElementNode;

class CombinedSelectorNodeTest extends AbstractNodeTest
{
    public function getToStringConversionTestData()
    {
        return array(
            array(new CombinedSelectorNode(new ElementNode(), '>', new ElementNode()), 'CombinedSelector[Element[*] > Element[*]]'),
            array(new CombinedSelectorNode(new ElementNode(), ' ', new ElementNode()), 'CombinedSelector[Element[*] <followed> Element[*]]'),
        );
    }

    public function getSpecificityValueTestData()
    {
        return array(
            array(new CombinedSelectorNode(new ElementNode(), '>', new ElementNode()), 0),
            array(new CombinedSelectorNode(new ElementNode(null, 'element'), '>', new ElementNode()), 1),
            array(new CombinedSelectorNode(new ElementNode(null, 'element'), '>', new ElementNode(null, 'element')), 2),
        );
    }
}
