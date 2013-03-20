<?php

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\Node\ClassNode;
use Symfony\Component\CssSelector\Node\NegationNode;
use Symfony\Component\CssSelector\Node\ElementNode;

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
