<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\NodeTraverser;

use Symfony\Component\Validator\Mapping\GenericMetadata;
use Symfony\Component\Validator\Node\GenericNode;
use Symfony\Component\Validator\NodeTraverser\NonRecursiveNodeTraverser;
use Symfony\Component\Validator\Tests\Fixtures\FakeMetadataFactory;

/**
 * @since  2.5
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class NonRecursiveNodeTraverserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var FakeMetadataFactory
     */
    private $metadataFactory;

    /**
     * @var NonRecursiveNodeTraverser
     */
    private $traverser;

    protected function setUp()
    {
        $this->metadataFactory = new FakeMetadataFactory();
        $this->traverser = new NonRecursiveNodeTraverser($this->metadataFactory);
    }

    public function testVisitorsMayPreventTraversal()
    {
        $nodes = array(new GenericNode('value', new GenericMetadata(), '', array('Default')));
        $context = $this->getMock('Symfony\Component\Validator\Context\ExecutionContextInterface');

        $visitor1 = $this->getMock('Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface');
        $visitor2 = $this->getMock('Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface');
        $visitor3 = $this->getMock('Symfony\Component\Validator\NodeVisitor\NodeVisitorInterface');

        $visitor1->expects($this->once())
            ->method('beforeTraversal')
            ->with($nodes, $context);

        // abort traversal
        $visitor2->expects($this->once())
            ->method('beforeTraversal')
            ->with($nodes, $context)
            ->will($this->returnValue(false));

        // never called
        $visitor3->expects($this->never())
            ->method('beforeTraversal');

        $visitor1->expects($this->never())
            ->method('visit');
        $visitor2->expects($this->never())
            ->method('visit');
        $visitor2->expects($this->never())
            ->method('visit');

        // called in order to clean up
        $visitor1->expects($this->once())
            ->method('afterTraversal')
            ->with($nodes, $context);

        // abort traversal
        $visitor2->expects($this->once())
            ->method('afterTraversal')
            ->with($nodes, $context);

        // never called, because beforeTraversal() wasn't called either
        $visitor3->expects($this->never())
            ->method('afterTraversal');

        $this->traverser->addVisitor($visitor1);
        $this->traverser->addVisitor($visitor2);
        $this->traverser->addVisitor($visitor3);

        $this->traverser->traverse($nodes, $context);
    }
}
