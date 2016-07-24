<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests;

use Symfony\Component\AstGenerator\AstGeneratorChain;
use Symfony\Component\AstGenerator\AstGeneratorInterface;

class AstGeneratorChainTest extends \PHPUnit_Framework_TestCase
{
    public function testEmpty()
    {
        $generator = new AstGeneratorChain();

        $this->assertFalse($generator->supportsGeneration('dummy'));
        $this->assertEmpty($generator->generate('dummy'));
    }

    public function testSupports()
    {
        $generatorSub = $this->getGeneratorMock(true, ['ast']);

        $generator = new AstGeneratorChain([$generatorSub]);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast'], $generator->generate('dummy'));
    }

    public function testMultiSupports()
    {
        $generatorSub1 = $this->getGeneratorMock(true, ['ast1']);
        $generatorSub2 = $this->getGeneratorMock(true, ['ast2']);

        $generator = new AstGeneratorChain([$generatorSub1, $generatorSub2]);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast1', 'ast2'], $generator->generate('dummy'));
    }

    public function testPartialSupports()
    {
        $generatorSub1 = $this->getGeneratorMock(true, ['ast1']);
        $generatorSub2 = $this->getGeneratorMock(false);

        $generator = new AstGeneratorChain([$generatorSub1, $generatorSub2]);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast1'], $generator->generate('dummy'));
    }

    public function testMultiSupportsWithFirstReturn()
    {
        $generatorSub1 = $this->getGeneratorMock(true, ['ast1']);
        $generatorSub2 = $this->getGeneratorMock(true, ['ast2']);

        $generator = new AstGeneratorChain([$generatorSub1, $generatorSub2], true);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast1'], $generator->generate('dummy'));
    }

    private function getGeneratorMock($support, $return = null)
    {
        $generatorSub = $this->getMockBuilder(AstGeneratorInterface::class)->getMock();
        $generatorSub
            ->expects($this->any())
            ->method('supportsGeneration')
            ->with('dummy')
            ->willReturn($support);
        if (null === $return) {
            $generatorSub
                ->expects($this->never())
                ->method('generate');
        } else {
            $generatorSub
                ->expects($this->any())
                ->method('generate')
                ->with('dummy', array())
                ->willReturn($return);
        }

        return $generatorSub;
    }
}
