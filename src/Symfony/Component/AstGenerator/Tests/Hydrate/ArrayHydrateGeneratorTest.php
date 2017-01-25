<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Tests\Hydrate;

use PhpParser\Node\Expr;
use PhpParser\PrettyPrinter\Standard;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Hydrate\ArrayHydrateGenerator;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\PropertyInfo\Type;

class ArrayHydrateGeneratorTest extends AbstractHydratorTest
{
    public function testHydrateGenerator()
    {
        $propertyInfoExtractor = $this->getPropertyInfoExtractor(Dummy::class);
        $hydrateGenerator = new ArrayHydrateGenerator($propertyInfoExtractor, new DummyTypeGenerator());

        $this->assertTrue($hydrateGenerator->supportsGeneration(Dummy::class));

        $dummyObject = new Dummy();
        $dummyObject->foo = 'test';

        eval($this->printer->prettyPrint($hydrateGenerator->generate(Dummy::class, [
            'input' => new Expr\Variable('dummyObject'),
            'output' => new Expr\Variable('dummyArray'),
        ])));

        $this->assertInternalType('array', $dummyArray);
        $this->assertArrayHasKey('foo', $dummyArray);
        $this->assertEquals('test', $dummyArray['foo']);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoInput()
    {
        $propertyInfoExtractor = $this->getMockBuilder(PropertyInfoExtractorInterface::class)->getMock();
        $hydrateGenerator = new ArrayHydrateGenerator($propertyInfoExtractor, new DummyTypeGenerator());
        $hydrateGenerator->generate(Dummy::class);
    }

    /**
     * @expectedException \Symfony\Component\AstGenerator\Exception\MissingContextException
     */
    public function testNoOutput()
    {
        $propertyInfoExtractor = $this->getMockBuilder(PropertyInfoExtractorInterface::class)->getMock();
        $hydrateGenerator = new ArrayHydrateGenerator($propertyInfoExtractor, new DummyTypeGenerator());
        $hydrateGenerator->generate(Dummy::class, ['input' => new Expr\Variable('test')]);
    }
}

class Dummy
{
    public $foo;

    public $bar;

    /**
     * @return mixed
     */
    public function getFoo()
    {
        return $this->foo;
    }

    /**
     * @param mixed $bar
     */
    public function setBar($bar)
    {
        $this->bar = $bar;
    }
}

class DummyTypeGenerator implements AstGeneratorInterface
{
    public function generate($object, array $context = [])
    {
        if (!isset($context['input'])) {
            throw new \Exception('no input');
        }

        if (!isset($context['output'])) {
            throw new \Exception('no output');
        }

        return [new Expr\Assign($context['output'], $context['input'])];
    }

    public function supportsGeneration($object)
    {
        return true;
    }
}
