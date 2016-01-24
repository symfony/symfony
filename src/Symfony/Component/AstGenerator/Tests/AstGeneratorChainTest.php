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
        $generatorSub = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub->supportsGeneration('dummy')->willReturn(true);
        $generatorSub->generate('dummy', [])->willReturn(['ast']);

        $generator = new AstGeneratorChain([$generatorSub->reveal()]);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast'], $generator->generate('dummy'));
    }

    public function testMultiSupports()
    {
        $generatorSub1 = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub1->supportsGeneration('dummy')->willReturn(true);
        $generatorSub1->generate('dummy', [])->willReturn(['ast1']);

        $generatorSub2 = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub2->supportsGeneration('dummy')->willReturn(true);
        $generatorSub2->generate('dummy', [])->willReturn(['ast2']);

        $generator = new AstGeneratorChain([$generatorSub1->reveal(), $generatorSub2->reveal()]);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast1', 'ast2'], $generator->generate('dummy'));
    }

    public function testPartialSupports()
    {
        $generatorSub1 = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub1->supportsGeneration('dummy')->willReturn(true);
        $generatorSub1->generate('dummy', [])->willReturn(['ast1']);

        $generatorSub2 = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub2->supportsGeneration('dummy')->willReturn(false);

        $generator = new AstGeneratorChain([$generatorSub1->reveal(), $generatorSub2->reveal()]);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast1'], $generator->generate('dummy'));
    }

    public function testMultiSupportsWithFirstReturn()
    {
        $generatorSub1 = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub1->supportsGeneration('dummy')->willReturn(true);
        $generatorSub1->generate('dummy', [])->willReturn(['ast1']);

        $generatorSub2 = $this->prophesize(AstGeneratorInterface::class);
        $generatorSub2->supportsGeneration('dummy')->willReturn(true);
        $generatorSub2->generate('dummy', [])->willReturn(['ast2']);

        $generator = new AstGeneratorChain([$generatorSub1->reveal(), $generatorSub2->reveal()], true);
        $this->assertTrue($generator->supportsGeneration('dummy'));
        $this->assertEquals(['ast1'], $generator->generate('dummy'));
    }
}
