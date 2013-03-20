<?php

namespace Symfony\Component\CssSelector\Tests\Node;

use Symfony\Component\CssSelector\Node\NodeInterface;

abstract class AbstractNodeTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider getToStringConversionTestData */
    public function testToStringConversion(NodeInterface $node, $representation)
    {
        $this->assertEquals($representation, (string) $node);
    }

    /** @dataProvider getSpecificityValueTestData */
    public function testSpecificityValue(NodeInterface $node, $value)
    {
        $this->assertEquals($value, $node->getSpecificity()->getValue());
    }

    abstract public function getToStringConversionTestData();
    abstract public function getSpecificityValueTestData();
}
